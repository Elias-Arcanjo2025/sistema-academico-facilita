<?php

namespace App\Services;

use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\Turma;
use App\Support\PautaResultadoTransicao;
use Illuminate\Support\Collection;

/**
 * Exame de Recuperação anual (10.ª e 11.ª) — Art.º 30.º DE 424/25.
 *
 * Uma única nota por disciplina/ano lectivo substitui a CFD (ou CA) para efeitos
 * de aproveitamento final. Alterações arquivam a nota anterior (histórico).
 *
 * Notas trimestrais antigas (trimestre 1–3) mantêm-se apenas para MT trimestral
 * via mtEfectiva() — não substituem CFD.
 */
class RecuperacaoService
{
    public const APROVACAO_MINIMA = 10;

    public const CLASSES_RECUPERACAO = [10, 11];

    public function configuracoes(): array
    {
        return [
            'nota_minima_aprovacao' => self::APROVACAO_MINIMA,
        ];
    }

    /**
     * Elegibilidade anual: alunos em TRANS. C/DEF. (1–3 negativas) na pauta T3.
     *
     * @return Collection<int, array{
     *   aluno_id:int,
     *   numero_ordem:?int,
     *   nome:string,
     *   elegivel:bool,
     *   motivo:?string,
     *   disciplinas_elegiveis:array<int, array{
     *     disciplina_id:int,
     *     nota_referencia:?float,
     *     disciplina_nome:string,
     *   }>,
     * }>
     */
    public function calcularElegibilidadeAnual(Turma $turma, ?string $anoLectivo = null): Collection
    {
        $classeNumero = $this->classeNumero($turma);
        if (! in_array($classeNumero, self::CLASSES_RECUPERACAO, true)) {
            return collect();
        }

        $transicao = app(RecuperacaoTransicaoService::class);
        $analises = $transicao->analisarTurma($turma, $anoLectivo);

        return $analises->map(function (array $a) {
            $terminais = collect($a['terminais_recuperacao'] ?? []);
            $continuas = collect($a['continuas_deficientes'] ?? []);
            $totalNeg = count($a['disciplinas_recuperacao'] ?? []);

            $disciplinas = $terminais
                ->map(fn ($d) => [
                    'disciplina_id' => $d['disciplina_id'],
                    'nota_referencia' => isset($d['ca']) ? (float) $d['ca'] : null,
                    'disciplina_nome' => $d['disciplina_nome'],
                ])
                ->values()
                ->all();

            $elegivel = PautaResultadoTransicao::elegivelRecuperacao(
                $totalNeg,
                $terminais->count(),
                $continuas->count(),
            ) && ! ($a['reprova_directamente'] ?? false);

            $motivo = match (true) {
                ($a['situacao'] ?? '') === 'incompleta' => 'Avaliação incompleta na pauta T3.',
                ($a['reprova_directamente'] ?? false) => 'Mais de 3 disciplinas negativas — reprovação directa.',
                $totalNeg === 0 => 'Sem disciplinas negativas (não está em recuperação).',
                ! $elegivel => 'Situação não elegível para exame de recuperação.',
                default => null,
            };

            return [
                'aluno_id' => $a['aluno_id'],
                'numero_ordem' => $a['numero_ordem'],
                'nome' => $a['nome'],
                'elegivel' => $elegivel,
                'motivo' => $motivo,
                'disciplinas_elegiveis' => $elegivel ? $disciplinas : [],
                'disciplinas_terminais' => $disciplinas,
            ];
        })->values();
    }

    /**
     * @deprecated Use calcularElegibilidadeAnual(). Mantido para MT trimestral legada.
     */
    public function calcularElegibilidade(Turma $turma, int $trimestre, ?string $anoLectivo = null): Collection
    {
        return $this->calcularElegibilidadeAnual($turma, $anoLectivo);
    }

    /**
     * Lista plana — recuperação anual (época T3).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listarElegiveisPlano(
        ?int $turmaId = null,
        ?int $trimestre = null,
        ?string $anoLectivo = null,
    ): Collection {
        if ($trimestre !== null && $trimestre !== 3) {
            return collect();
        }

        return app(RecuperacaoTransicaoService::class)
            ->listarRecuperacaoTerminalPlano($turmaId, $anoLectivo)
            ->map(fn (array $l) => array_merge($l, [
                'tipo_exame' => 'Recuperação anual',
                'trimestre' => 3,
            ]));
    }

    /**
     * MT efectiva — só aplica recuperação trimestral legada (não a anual).
     */
    public static function mtEfectiva(?float $mt, ?float $recuperacaoTrimestral): ?float
    {
        if ($recuperacaoTrimestral !== null) {
            return $recuperacaoTrimestral;
        }

        return $mt;
    }

    /**
     * Nota de recuperação anual activa (trimestre null, não arquivada).
     */
    public function recuperacaoAnualAtiva(
        int $alunoId,
        int $disciplinaId,
        int $turmaId,
        int $anoLectivoId,
    ): ?float {
        $nota = $this->queryRecuperacaoAnualAtiva($alunoId, $disciplinaId, $turmaId, $anoLectivoId)->first();

        return $nota ? (float) $nota->valor : null;
    }

    /**
     * @return Collection<int, Nota>
     */
    public function recuperacoesAnuaisTurma(Turma $turma, ?string $anoLectivo = null): Collection
    {
        $anoLectivo = $anoLectivo
            ?? $turma->anoLectivo?->nome
            ?? $turma->ano_lectivo
            ?? Configuracao::getAnoLectivoActual();
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);

        return Nota::query()
            ->whereIn('aluno_id', $turma->alunos()->pluck('id'))
            ->where('turma_id', $turma->id)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('tipo', Nota::TIPO_RECUPERACAO)
            ->whereNull('trimestre')
            ->whereNull('archived_at')
            ->get();
    }

    /**
     * Grava recuperação anual — arquiva a anterior se o valor mudar.
     */
    public function gravarRecuperacao(
        int $alunoId,
        int $disciplinaId,
        int $turmaId,
        ?float $valor,
        ?string $anoLectivo = null,
        bool $foraElegibilidade = false,
    ): void {
        $anoLectivo = $anoLectivo ?? Configuracao::getAnoLectivoActual();
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);

        $existente = $this->queryRecuperacaoAnualAtiva($alunoId, $disciplinaId, $turmaId, $anoLectivoId)->first();

        if ($valor === null || $valor === '') {
            if ($existente) {
                $existente->archive('Recuperação anual removida');
            }

            return;
        }

        $valor = (float) $valor;

        if ($existente) {
            if ((float) $existente->valor === $valor) {
                return;
            }

            $existente->archive('Substituída por nova nota de recuperação anual');
        }

        $observacoes = 'Recuperação anual — '.now()->format('d/m/Y H:i');
        if ($foraElegibilidade) {
            $observacoes .= ' [Fora da elegibilidade formal — permitido pela configuração da escola]';
        }

        $nota = Nota::create([
            'aluno_id' => $alunoId,
            'disciplina_id' => $disciplinaId,
            'turma_id' => $turmaId,
            'ano_lectivo' => AnoLectivo::find($anoLectivoId)?->nome,
            'ano_lectivo_id' => $anoLectivoId,
            'trimestre' => null,
            'tipo' => Nota::TIPO_RECUPERACAO,
            'valor' => $valor,
            'professor_id' => auth()->id(),
            'observacoes' => $observacoes,
        ]);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($nota)
            ->withProperties([
                'aluno_id' => $alunoId,
                'disciplina_id' => $disciplinaId,
                'turma_id' => $turmaId,
                'valor' => $valor,
                'tipo' => Nota::TIPO_RECUPERACAO,
                'fora_elegibilidade' => $foraElegibilidade,
            ])
            ->log($foraElegibilidade
                ? 'Nota de recuperação anual lançada (fora da elegibilidade formal)'
                : 'Nota de recuperação anual lançada');
    }

    private function queryRecuperacaoAnualAtiva(
        int $alunoId,
        int $disciplinaId,
        int $turmaId,
        int $anoLectivoId,
    ) {
        return Nota::query()
            ->where('aluno_id', $alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('tipo', Nota::TIPO_RECUPERACAO)
            ->whereNull('trimestre')
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereNull('archived_at');
    }

    public function classeNumero(Turma $turma): int
    {
        return (int) preg_replace('/\D/', '', $turma->classe?->codigo ?? $turma->classe?->nome ?? '');
    }
}
