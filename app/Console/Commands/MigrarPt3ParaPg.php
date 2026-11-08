<?php

namespace App\Console\Commands;

use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\Turma;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Converte notas PT do 3.º trimestre em PG (Prova Global) no regime politécnico.
 *
 * Professores que lançaram a prova global no campo PT3 (lógica antiga) passam
 * a ter registos oficiais tipo PG (trimestre null). O PT3 original é arquivado.
 */
class MigrarPt3ParaPg extends Command
{
    protected $signature = 'notas:migrar-pt3-para-pg
                            {ano_lectivo? : Ano lectivo (ex: "2025-2026"). Por defeito usa o activo}
                            {--turma= : ID de turma específica. Por defeito processa todas}
                            {--dry-run : Não escreve nada — apenas mostra o que seria alterado}';

    protected $description = 'Migra PT3 → PG em turmas politécnicas (professores que lançaram PG no campo PT3).';

    public function handle(): int
    {
        $anoLectivoNome = $this->argument('ano_lectivo') ?? Configuracao::getAnoLectivoActual();
        $turmaOpt = $this->option('turma');
        $dryRun = (bool) $this->option('dry-run');

        $anoLectivo = AnoLectivo::where('nome', $anoLectivoNome)->first();
        if (! $anoLectivo) {
            $this->error("Ano lectivo '{$anoLectivoNome}' não encontrado.");

            return self::FAILURE;
        }

        $turmas = Turma::query()
            ->where('ano_lectivo_id', $anoLectivo->id)
            ->where('activo', true)
            ->when($turmaOpt, fn ($q) => $q->where('id', (int) $turmaOpt))
            ->with('curso')
            ->get()
            ->filter(fn (Turma $turma) => ! $turma->curso?->regime()->usaProvaTrimestral(3));

        if ($turmas->isEmpty()) {
            $this->warn('Nenhuma turma politécnica encontrada para os filtros indicados.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line(sprintf(
            '<fg=cyan>%s</> migração PT3 → PG | Ano: <fg=yellow>%s</> | Turmas: <fg=yellow>%d</>',
            $dryRun ? '[DRY-RUN]' : '[EXECUÇÃO]',
            $anoLectivo->nome,
            $turmas->count()
        ));
        $this->newLine();

        $stats = [
            'pg_criadas' => 0,
            'pg_existentes' => 0,
            'pt3_arquivadas' => 0,
            'pt3_sem_professor' => 0,
            'errors' => 0,
        ];

        DB::beginTransaction();

        try {
            foreach ($turmas as $turma) {
                $pt3Notas = Nota::query()
                    ->where('turma_id', $turma->id)
                    ->where('ano_lectivo_id', $anoLectivo->id)
                    ->where('tipo', Nota::TIPO_PT)
                    ->where('trimestre', 3)
                    ->whereNull('archived_at')
                    ->get();

                foreach ($pt3Notas as $pt3) {
                    try {
                        $this->processarNota($pt3, $anoLectivo, $dryRun, $stats);
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        $this->error(sprintf(
                            'Erro nota PT3 id=%d: %s',
                            $pt3->id,
                            $e->getMessage()
                        ));
                    }
                }
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Erro inesperado — transacção revertida: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Métrica', 'Total'],
            [
                ['PG criadas a partir de PT3', $stats['pg_criadas']],
                ['PT3 ignoradas (PG já existia)', $stats['pg_existentes']],
                ['PT3 arquivadas', $stats['pt3_arquivadas']],
                ['PT3 saltadas (sem professor_id)', $stats['pt3_sem_professor']],
                ['Erros', $stats['errors']],
            ]
        );

        if ($dryRun) {
            $this->warn('DRY-RUN: nenhuma alteração foi guardada. Volte a correr sem --dry-run para aplicar.');
        } else {
            $this->info('Migração concluída.');
        }

        return self::SUCCESS;
    }

    private function processarNota(Nota $pt3, AnoLectivo $anoLectivo, bool $dryRun, array &$stats): void
    {
        $pgExistente = Nota::query()
            ->where('aluno_id', $pt3->aluno_id)
            ->where('disciplina_id', $pt3->disciplina_id)
            ->where('turma_id', $pt3->turma_id)
            ->where('ano_lectivo_id', $anoLectivo->id)
            ->where('tipo', Nota::TIPO_PG)
            ->whereNull('trimestre')
            ->whereNull('archived_at')
            ->first();

        if ($pgExistente) {
            $stats['pg_existentes']++;
        } else {
            if (! $pt3->professor_id) {
                $stats['pt3_sem_professor']++;

                return;
            }

            if (! $dryRun) {
                Nota::create([
                    'aluno_id' => $pt3->aluno_id,
                    'disciplina_id' => $pt3->disciplina_id,
                    'turma_id' => $pt3->turma_id,
                    'professor_id' => $pt3->professor_id,
                    'tipo' => Nota::TIPO_PG,
                    'valor' => $pt3->valor,
                    'trimestre' => null,
                    'ano_lectivo' => $anoLectivo->nome,
                    'ano_lectivo_id' => $anoLectivo->id,
                    'estado_conselho' => $pt3->estado_conselho,
                    'observacoes' => trim(
                        ($pt3->observacoes ?? '') . "\nMigrada de PT3 por notas:migrar-pt3-para-pg em " . now()->format('d/m/Y H:i')
                    ),
                ]);
            }
            $stats['pg_criadas']++;
        }

        if (! $dryRun) {
            $pt3->archive('Migrada para PG (notas:migrar-pt3-para-pg)', null);
        }
        $stats['pt3_arquivadas']++;
    }
}
