<?php

namespace App\Services;

use App\Events\NotaAlterada;
use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\User;
use App\Notifications\NotaLancadaNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotaService
{
    /**
     * Lança uma nova nota para um aluno
     */
    public function lancar(array $data, User $professor): Nota
    {
        $this->validarValorNota($data['valor']);

        $nota = Nota::create([
            'aluno_id' => $data['aluno_id'],
            'disciplina_id' => $data['disciplina_id'],
            'turma_id' => $data['turma_id'],
            'professor_id' => $professor->id,
            'tipo' => $data['tipo'],
            'valor' => $data['valor'],
            'ano_lectivo' => $data['ano_lectivo'] ?? Configuracao::getAnoLectivoActual(),
            'ano_lectivo_id' => $data['ano_lectivo_id'] ?? Configuracao::getAnoLectivoActualId(),
            'trimestre' => $data['trimestre'] ?? null,
            'observacoes' => $data['observacoes'] ?? null,
        ]);

        activity()
            ->causedBy($professor)
            ->performedOn($nota)
            ->withProperties([
                'aluno_id'     => $nota->aluno_id,
                'disciplina_id' => $nota->disciplina_id,
                'tipo'         => $nota->tipo,
                'valor'        => $nota->valor,
                'trimestre'    => $nota->trimestre,
            ])
            ->log('Nota lançada');

        // Disparar evento para recalculo automatico
        NotaAlterada::dispatch($nota, $professor->id);

        // Notificar o professor (via canal database) — nunca bloqueia o fluxo principal
        try {
            $nota->load(['disciplina', 'turma', 'aluno']);
            $trimestreLabel = $nota->trimestre ? $nota->trimestre . '.º Trimestre' : 'Sem trimestre';

            $professor->notify(new NotaLancadaNotification(
                $nota,
                $nota->disciplina->nome ?? '-',
                $nota->turma->nome ?? '-',
                $trimestreLabel
            ));
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar NotaLancadaNotification: ' . $e->getMessage(), [
                'nota_id'      => $nota->id,
                'professor_id' => $professor->id,
            ]);
        }

        $this->notificarAlunoNotaPublicada($nota);

        return $nota;
    }

    /**
     * Grava ou remove (arquiva) uma nota de lançamento pelo professor.
     *
     * Campo vazio remove a nota pré-conselho existente — aluno fica sem avaliação
     * nesse componente (útil para desistentes ou lançamentos por engano).
     *
     * @return 'criada'|'actualizada'|'removida'|'ignorada'|'bloqueada'
     */
    public function gravarOuRemoverLancamento(
        mixed $valor,
        int $alunoId,
        int $disciplinaId,
        int $turmaId,
        string $tipo,
        ?int $trimestre,
        User $professor,
        ?int $anoLectivoId = null,
    ): string {
        $anoLectivoId ??= Configuracao::getAnoLectivoActualId();
        $anoLectivoNome = AnoLectivo::getNomeById($anoLectivoId);

        $criterios = [
            'aluno_id' => $alunoId,
            'disciplina_id' => $disciplinaId,
            'turma_id' => $turmaId,
            'tipo' => $tipo,
            'ano_lectivo_id' => $anoLectivoId,
        ];

        if ($trimestre === null) {
            $criterios['trimestre'] = null;
        } else {
            $criterios['trimestre'] = $trimestre;
        }

        if ($valor === null || $valor === '') {
            if ($this->removerLancamentoProfessor(
                $alunoId,
                $disciplinaId,
                $turmaId,
                $tipo,
                $trimestre,
                $anoLectivoId,
                $professor,
            )) {
                return 'removida';
            }

            if ($this->existeNotaPosConselhoActiva($criterios)) {
                return 'bloqueada';
            }

            return 'ignorada';
        }

        $this->validarValorNota($valor);

        $criterios['ano_lectivo'] = $anoLectivoNome;

        $notaExistente = $this->buscarNotaPreConselhoLancamento($criterios);

        if (! $notaExistente) {
            $notaExistente = $this->buscarNotaPreConselhoLancamento(
                collect($criterios)->except('ano_lectivo_id')->all()
            );
        }

        if ($notaExistente) {
            if ($notaExistente->isArchived()) {
                $notaExistente->restore();
            }

            if ((float) $notaExistente->valor === (float) $valor) {
                return 'ignorada';
            }

            $valorAnterior = $notaExistente->valor;
            $notaExistente->update([
                'valor' => $valor,
                'professor_id' => $professor->id,
                'ano_lectivo' => $anoLectivoNome,
                'ano_lectivo_id' => $anoLectivoId,
            ]);

            activity()
                ->causedBy($professor)
                ->performedOn($notaExistente)
                ->withProperties([
                    'valor_anterior' => $valorAnterior,
                    'valor_novo' => $valor,
                    'tipo' => $tipo,
                    'trimestre' => $trimestre,
                ])
                ->log('Nota actualizada em lote');

            NotaAlterada::dispatch($notaExistente, $professor->id);
            $this->notificarAlunoNotaPublicada($notaExistente);

            return 'actualizada';
        }

        $posConselho = Nota::query()
            ->where($criterios)
            ->whereNull('archived_at')
            ->where('estado_conselho', Nota::ESTADO_POS_CONSELHO)
            ->exists();

        if ($posConselho) {
            return 'bloqueada';
        }

        $nota = Nota::create([
            ...$criterios,
            'professor_id' => $professor->id,
            'valor' => $valor,
            'ano_lectivo' => $anoLectivoNome,
            'estado_conselho' => Nota::ESTADO_PRE_CONSELHO,
        ]);

        activity()
            ->causedBy($professor)
            ->performedOn($nota)
            ->withProperties([
                'aluno_id' => $alunoId,
                'disciplina_id' => $disciplinaId,
                'tipo' => $tipo,
                'valor' => $valor,
                'trimestre' => $trimestre,
            ])
            ->log('Nota lançada');

        NotaAlterada::dispatch($nota, $professor->id);
        $this->notificarAlunoNotaPublicada($nota);

        return 'criada';
    }

    private function notificarAlunoNotaPublicada(Nota $nota): void
    {
        try {
            app(\App\Services\Portal\PortalNotificacaoService::class)->notificarNotaPublicada($nota);
        } catch (\Throwable $e) {
            Log::warning('Falha ao notificar aluno sobre nota: '.$e->getMessage(), [
                'nota_id' => $nota->id,
            ]);
        }
    }

    /**
     * Arquiva nota pré-conselho quando o professor deixa o campo vazio.
     */
    public function removerLancamentoProfessor(
        int $alunoId,
        int $disciplinaId,
        int $turmaId,
        string $tipo,
        ?int $trimestre,
        int $anoLectivoId,
        User $professor,
        string $motivo = 'Campo vazio — aluno sem avaliação neste componente',
    ): bool {
        $query = Nota::query()
            ->where('aluno_id', $alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('tipo', $tipo)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereNull('archived_at')
            ->preConselhoLancamento();

        if ($trimestre === null) {
            $query->whereNull('trimestre');
        } else {
            $query->where('trimestre', $trimestre);
        }

        $nota = $query->first();

        if (! $nota) {
            return false;
        }

        $nota->archive($motivo, $professor->id);
        NotaAlterada::dispatch($nota, $professor->id);

        activity()
            ->causedBy($professor)
            ->performedOn($nota)
            ->withProperties([
                'aluno_id' => $alunoId,
                'disciplina_id' => $disciplinaId,
                'tipo' => $tipo,
                'trimestre' => $trimestre,
                'valor_removido' => $nota->valor,
            ])
            ->log('Nota removida (campo vazio)');

        return true;
    }

    /**
     * Localiza nota pré-conselho existente (activa ou arquivada) para evitar duplicados na BD.
     *
     * @param  array<string, mixed>  $criterios
     */
    private function buscarNotaPreConselhoLancamento(array $criterios): ?Nota
    {
        return Nota::query()
            ->where($criterios)
            ->preConselhoLancamento()
            ->orderByRaw('archived_at IS NULL DESC')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $criterios
     */
    private function existeNotaPosConselhoActiva(array $criterios): bool
    {
        return Nota::query()
            ->where(collect($criterios)->except(['ano_lectivo'])->all())
            ->whereNull('archived_at')
            ->where('estado_conselho', Nota::ESTADO_POS_CONSELHO)
            ->exists();
    }

    /**
     * @param  array<int|string, mixed>  $notas
     * @return array<int, mixed>
     */
    private function normalizarNotasSubmetidas(array $notas): array
    {
        $normalizadas = [];

        foreach ($notas as $alunoId => $valor) {
            $id = (int) $alunoId;

            if ($valor === '' || $valor === null) {
                $normalizadas[$id] = null;

                continue;
            }

            if (is_string($valor)) {
                $valor = str_replace(',', '.', trim($valor));
            }

            $normalizadas[$id] = $valor;
        }

        return $normalizadas;
    }

    /**
     * Lança notas em lote para uma turma/disciplina
     */
    public function lancarEmLote(array $notas, int $disciplinaId, int $turmaId, string $tipo, int $trimestre, User $professor): array
    {
        $notas = $this->normalizarNotasSubmetidas($notas);

        $resultado = [
            'sucesso' => 0,
            'removidas' => 0,
            'bloqueadas' => 0,
            'erros' => [],
        ];

        // Campos number vazios no HTML não vêm no POST — incluir todos os alunos activos.
        $alunoIds = Aluno::where('turma_id', $turmaId)
            ->activo()
            ->pluck('id');

        DB::beginTransaction();

        try {
            foreach ($alunoIds as $alunoId) {
                $alunoId = (int) $alunoId;
                $valor = array_key_exists($alunoId, $notas) ? $notas[$alunoId] : null;

                try {
                    $accao = $this->gravarOuRemoverLancamento(
                        $valor,
                        (int) $alunoId,
                        $disciplinaId,
                        $turmaId,
                        $tipo,
                        $trimestre,
                        $professor,
                    );

                    if (in_array($accao, ['criada', 'actualizada'], true)) {
                        $resultado['sucesso']++;
                    } elseif ($accao === 'removida') {
                        $resultado['removidas']++;
                    } elseif ($accao === 'bloqueada') {
                        $resultado['bloqueadas']++;
                    }
                } catch (\Exception $e) {
                    $aluno = Aluno::find($alunoId);
                    $resultado['erros'][] = [
                        'aluno' => $aluno ? $aluno->nome : "ID: {$alunoId}",
                        'erro' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $resultado;
    }

    /**
     * Altera uma nota existente com justificação obrigatória
     */
    public function alterar(Nota $nota, float $novoValor, string $justificacao, User $professor): Nota
    {
        $this->validarValorNota($novoValor);

        if (empty(trim($justificacao))) {
            throw new \InvalidArgumentException('A justificação é obrigatória para alterar uma nota.');
        }

        $valorAnterior = $nota->valor;

        $nota->update([
            'valor' => $novoValor,
            'observacoes' => $nota->observacoes . "\n\n[" . now()->format('d/m/Y H:i') . "] Alteração: {$justificacao}",
        ]);

        activity()
            ->causedBy($professor)
            ->performedOn($nota)
            ->withProperties([
                'valor_anterior' => $valorAnterior,
                'valor_novo' => $novoValor,
                'justificacao' => $justificacao,
                'aluno_id' => $nota->aluno_id,
                'disciplina_id' => $nota->disciplina_id,
            ])
            ->log('Nota alterada com justificação');

        // Disparar evento para recalculo automatico
        NotaAlterada::dispatch($nota, $professor->id);

        return $nota;
    }

    /**
     * Calcula a média trimestral de um aluno numa disciplina
     */
    public function calcularMediaTrimestral(int $alunoId, int $disciplinaId, int $turmaId, int $trimestre): ?float
    {
        $notas = Nota::where('aluno_id', $alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('trimestre', $trimestre)
            ->where('ano_lectivo_id', Configuracao::getAnoLectivoActualId())
            ->whereIn('tipo', [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT])
            ->get();

        if ($notas->isEmpty()) {
            return null;
        }

        $soma = $notas->sum('valor');
        $count = $notas->count();

        return round($soma / $count, 2);
    }

    /**
     * Calcula a média final anual de um aluno numa disciplina
     */
    public function calcularMediaFinal(int $alunoId, int $disciplinaId, int $turmaId): ?float
    {
        $mediasTrimestre = Nota::where('aluno_id', $alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('ano_lectivo_id', Configuracao::getAnoLectivoActualId())
            ->where('tipo', Nota::TIPO_MT)
            ->get();

        if ($mediasTrimestre->count() < 3) {
            return null;
        }

        $soma = $mediasTrimestre->sum('valor');
        return round($soma / $mediasTrimestre->count(), 2);
    }

    /**
     * Obtém as notas de um aluno numa disciplina para o ano actual
     */
    public function getNotasAlunoDisciplina(int $alunoId, int $disciplinaId, int $turmaId): array
    {
        $notas = Nota::where('aluno_id', $alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('ano_lectivo_id', Configuracao::getAnoLectivoActualId())
            ->get()
            ->groupBy('trimestre');

        return $notas->toArray();
    }

    /**
     * Obtém todas as notas de uma turma/disciplina para o ano actual
     */
    public function getNotasTurmaDisciplina(int $turmaId, int $disciplinaId, ?int $trimestre = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = Nota::with('aluno')
            ->where('turma_id', $turmaId)
            ->where('disciplina_id', $disciplinaId)
            ->where('ano_lectivo_id', Configuracao::getAnoLectivoActualId());

        if ($trimestre) {
            $query->where('trimestre', $trimestre);
        }

        return $query->get();
    }

    private function validarValorNota($valor): void
    {
        if (!is_numeric($valor)) {
            throw new \InvalidArgumentException('O valor da nota deve ser numerico.');
        }

        $valor = (float) $valor;

        if ($valor < 0 || $valor > 20) {
            throw new \InvalidArgumentException('A nota deve estar entre 0 e 20.');
        }
    }
}
