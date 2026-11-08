<?php

namespace App\Console\Commands;

use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Services\PautaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula as Médias Trimestrais (MT) com a regra actual:
 *   MT = (MAC + PP + PT) / 3, com notas em falta a contar como 0
 *   — divisor sempre 3 em qualquer trimestre.
 *
 * Garantias de segurança:
 *   - NÃO apaga notas (nunca executa DELETE).
 *   - NÃO toca em notas pós-conselho (apenas pré-conselho).
 *   - NÃO toca em MAC, PP, PT, CFD ou qualquer outro tipo — só MT.
 *   - Tudo corre dentro de uma transacção: se algo falhar, faz rollback.
 *   - --dry-run mostra o impacto sem escrever nada na BD.
 */
class RecalcularMTs extends Command
{
    protected $signature = 'mt:recalcular
                            {ano_lectivo? : Ano lectivo (ex: "2025-2026"). Por defeito usa o activo}
                            {--trimestre= : Trimestre 1, 2 ou 3. Por defeito processa os três}
                            {--turma= : ID de turma específica. Por defeito processa todas}
                            {--dry-run : Não escreve nada — apenas mostra o que seria alterado}';

    protected $description = 'Recalcula MTs com a regra divisor=3. NÃO apaga nada e NÃO toca em notas pós-conselho.';

    public function handle(PautaService $pauta): int
    {
        $anoLectivoNome = $this->argument('ano_lectivo') ?? Configuracao::getAnoLectivoActual();
        $trimestreOpt   = $this->option('trimestre');
        $turmaOpt       = $this->option('turma');
        $dryRun         = (bool) $this->option('dry-run');

        $anoLectivo = AnoLectivo::where('nome', $anoLectivoNome)->first();
        if (!$anoLectivo) {
            $this->error("Ano lectivo '{$anoLectivoNome}' não encontrado.");
            return self::FAILURE;
        }

        if ($trimestreOpt !== null && !in_array((int) $trimestreOpt, [1, 2, 3], true)) {
            $this->error('--trimestre deve ser 1, 2 ou 3.');
            return self::FAILURE;
        }

        $trimestres = $trimestreOpt !== null ? [(int) $trimestreOpt] : [1, 2, 3];

        $turmas = Turma::query()
            ->where('ano_lectivo_id', $anoLectivo->id)
            ->where('activo', true)
            ->when($turmaOpt, fn($q) => $q->where('id', (int) $turmaOpt))
            ->with(['alunos' => fn($q) => $q->where('activo', true)])
            ->get();

        if ($turmas->isEmpty()) {
            $this->warn('Nenhuma turma encontrada para os filtros indicados.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line(sprintf(
            '<fg=cyan>%s</> recálculo de MTs | Ano: <fg=yellow>%s</> | Trimestres: <fg=yellow>%s</> | Turmas: <fg=yellow>%d</>',
            $dryRun ? '[DRY-RUN]' : '[EXECUÇÃO]',
            $anoLectivo->nome,
            implode(',', $trimestres),
            $turmas->count()
        ));
        $this->line('Regra: MT = (MAC + PP + PT) / 3 — divisor sempre 3, notas em falta = 0');
        $this->line('Garantia: <fg=green>nada é apagado</> | notas pós-conselho intactas | só toca em MT pré-conselho');
        $this->newLine();

        $stats = [
            'created'         => 0,
            'updated'         => 0,
            'unchanged'       => 0,
            'skipped_no_data' => 0,
            'skipped_no_prof' => 0,
            'errors'          => 0,
        ];

        $bar = $this->output->createProgressBar($turmas->count());
        $bar->setFormat(' %current%/%max% turmas [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('a iniciar...');
        $bar->start();

        DB::beginTransaction();

        try {
            foreach ($turmas as $turma) {
                $bar->setMessage("Turma {$turma->nome}");

                $disciplinas = Disciplina::query()
                    ->whereHas('cursos', fn($q) => $q->where('cursos.id', $turma->curso_id))
                    ->whereHas('classes', fn($q) => $q->where('classes.id', $turma->classe_id))
                    ->where('activo', true)
                    ->get();

                foreach ($turma->alunos as $aluno) {
                    foreach ($disciplinas as $disciplina) {
                        foreach ($trimestres as $trimestre) {
                            try {
                                $this->processarMT(
                                    pauta: $pauta,
                                    alunoId: $aluno->id,
                                    discId: $disciplina->id,
                                    turmaId: $turma->id,
                                    trimestre: $trimestre,
                                    anoLectivo: $anoLectivo,
                                    dryRun: $dryRun,
                                    stats: $stats,
                                );
                            } catch (\Throwable $e) {
                                $stats['errors']++;
                                $this->newLine();
                                $this->error(sprintf(
                                    'Erro aluno=%d disc=%d trim=%d: %s',
                                    $aluno->id, $disciplina->id, $trimestre, $e->getMessage()
                                ));
                            }
                        }
                    }
                }

                $bar->advance();
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $bar->finish();
            $this->newLine(2);
            $this->error('Erro inesperado — transacção revertida: ' . $e->getMessage());
            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Métrica', 'Total'],
            [
                ['MT criadas',                     $stats['created']],
                ['MT actualizadas',                $stats['updated']],
                ['MT iguais (sem alteração)',      $stats['unchanged']],
                ['Saltadas (sem MAC/PP/PT)',       $stats['skipped_no_data']],
                ['Saltadas (sem professor_id)',    $stats['skipped_no_prof']],
                ['Erros',                          $stats['errors']],
            ]
        );

        if ($dryRun) {
            $this->warn('DRY-RUN: nenhuma alteração foi guardada. Volte a correr sem --dry-run para aplicar.');
        } else {
            $this->info('Recálculo concluído com sucesso.');
        }

        return self::SUCCESS;
    }

    /**
     * Processa MT para (aluno, disciplina, trimestre):
     *   - se MT pré-conselho existe: UPDATE só se valor for diferente
     *   - se não existe: CREATE
     *   - nunca toca em MT pós-conselho
     *   - nunca apaga
     */
    private function processarMT(
        PautaService $pauta,
        int $alunoId,
        int $discId,
        int $turmaId,
        int $trimestre,
        AnoLectivo $anoLectivo,
        bool $dryRun,
        array &$stats,
    ): void {
        $media = $pauta->calcularMediaTrimestral(
            $alunoId, $discId, $turmaId, $trimestre, $anoLectivo->nome
        );

        if ($media === null) {
            $stats['skipped_no_data']++;
            return;
        }

        // Procura MT PRÉ-conselho activa (não toca pós-conselho nem arquivadas)
        $mtExistente = Nota::where('aluno_id', $alunoId)
            ->where('disciplina_id', $discId)
            ->where('turma_id', $turmaId)
            ->where('trimestre', $trimestre)
            ->where('ano_lectivo_id', $anoLectivo->id)
            ->where('tipo', Nota::TIPO_MT)
            ->where('estado_conselho', Nota::ESTADO_PRE_CONSELHO)
            ->whereNull('archived_at')
            ->first();

        if ($mtExistente) {
            if ((float) $mtExistente->valor === (float) $media) {
                $stats['unchanged']++;
                return;
            }

            if (!$dryRun) {
                $mtExistente->update([
                    'valor'       => $media,
                    'observacoes' => trim(($mtExistente->observacoes ?? '') . "\nRecalculada por mt:recalcular em " . now()->format('d/m/Y H:i')),
                ]);
            }
            $stats['updated']++;
            return;
        }

        // Criar MT — precisa de professor_id (NOT NULL no schema)
        $professorId = $this->resolverProfessorId($alunoId, $discId, $turmaId, $trimestre, $anoLectivo->nome);

        if (!$professorId) {
            $stats['skipped_no_prof']++;
            return;
        }

        if (!$dryRun) {
            Nota::create([
                'aluno_id'        => $alunoId,
                'disciplina_id'   => $discId,
                'turma_id'        => $turmaId,
                'professor_id'    => $professorId,
                'tipo'            => Nota::TIPO_MT,
                'valor'           => $media,
                'trimestre'       => $trimestre,
                'ano_lectivo'     => $anoLectivo->nome,
                'ano_lectivo_id'  => $anoLectivo->id,
                'estado_conselho' => Nota::ESTADO_PRE_CONSELHO,
                'observacoes'     => 'Criada por mt:recalcular em ' . now()->format('d/m/Y H:i'),
            ]);
        }
        $stats['created']++;
    }

    /**
     * Tenta encontrar professor_id válido:
     *   1. Associação activa em professor_disciplina_turma
     *   2. professor_id de qualquer MAC/PP/PT existente
     */
    private function resolverProfessorId(
        int $alunoId,
        int $discId,
        int $turmaId,
        int $trimestre,
        string $anoLectivoNome,
    ): ?int {
        $profId = ProfessorDisciplinaTurma::query()
            ->where('disciplina_id', $discId)
            ->where('turma_id', $turmaId)
            ->where('ano_lectivo', $anoLectivoNome)
            ->where('activo', true)
            ->value('user_id');

        if ($profId) {
            return (int) $profId;
        }

        // Fallback: usar professor das próprias notas MAC/PP/PT
        return Nota::query()
            ->whereIn('tipo', [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT])
            ->where('aluno_id', $alunoId)
            ->where('disciplina_id', $discId)
            ->where('turma_id', $turmaId)
            ->where('trimestre', $trimestre)
            ->whereNotNull('professor_id')
            ->value('professor_id');
    }
}
