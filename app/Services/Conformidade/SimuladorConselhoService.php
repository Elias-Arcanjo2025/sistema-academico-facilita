<?php

namespace App\Services\Conformidade;

use App\Models\Turma;
use App\Services\PautaService;
use App\Services\RecuperacaoTransicaoService;
use App\Support\PautaMomentoConselho;

/**
 * Simulador de conselho: "se baixar/subir X valores nesta avaliação, quem muda de situação?"
 */
class SimuladorConselhoService
{
    public function __construct(
        private readonly PautaService $pautaService,
        private readonly RecuperacaoTransicaoService $transicaoService,
    ) {}

    /**
     * @param  array<int, array{disciplina_id: int, tipo: string, delta: float}>  $ajustes
     * @return array<string, mixed>
     */
    public function simularTurma(
        Turma $turma,
        int $trimestre,
        array $ajustes,
        ?int $alunoId = null,
        ?string $anoLectivo = null,
    ): array {
        $anoLectivo = $anoLectivo
            ?? $turma->anoLectivo?->nome
            ?? $turma->ano_lectivo
            ?? \App\Models\Configuracao::getAnoLectivoActual();

        $pauta = $this->pautaService->gerarPautaTrimestralCompleta(
            $turma->id,
            $trimestre,
            $anoLectivo,
            PautaMomentoConselho::PRE
        );

        $classeNumero = (int) ($turma->classe?->numero ?? 0);
        $meta = $pauta['meta_disciplinas_transicao'] ?? [];
        $blindadas = $this->transicaoService->blindadasParaCurso((int) $turma->curso_id);
        $mudancas = [];

        foreach ($pauta['alunos'] ?? [] as $linha) {
            $id = (int) ($linha['aluno']->id ?? $linha['aluno_id'] ?? 0);
            if ($alunoId && $id !== $alunoId) {
                continue;
            }

            $antesSituacao = $this->situacaoLinha($linha, $turma, $trimestre, $classeNumero, $meta, $blindadas);
            $simulada = $this->aplicarDeltas($linha, $ajustes, $id);
            $depoisSituacao = $this->situacaoLinha($simulada, $turma, $trimestre, $classeNumero, $meta, $blindadas);

            if (($antesSituacao['resultado'] ?? '') !== ($depoisSituacao['resultado'] ?? '')
                || ($antesSituacao['resumo'] ?? '') !== ($depoisSituacao['resumo'] ?? '')) {
                $mudancas[] = [
                    'aluno_id' => $id,
                    'nome' => $linha['aluno']->nome ?? ($linha['nome'] ?? ''),
                    'numero_processo' => $linha['aluno']->numero_processo ?? null,
                    'antes' => $antesSituacao,
                    'depois' => $depoisSituacao,
                ];
            }
        }

        return [
            'turma' => $turma->nome ?? $turma->codigo,
            'trimestre' => $trimestre,
            'ano_lectivo' => $anoLectivo,
            'ajustes' => $ajustes,
            'total_alunos' => count($pauta['alunos'] ?? []),
            'total_mudancas' => count($mudancas),
            'mudancas' => $mudancas,
        ];
    }

    /**
     * Atalho: baixar 1 valor numa avaliação de um aluno/disciplina.
     */
    public function simularBaixarUm(
        Turma $turma,
        int $trimestre,
        int $alunoId,
        int $disciplinaId,
        string $tipo = 'MAC',
        ?string $anoLectivo = null,
    ): array {
        return $this->simularTurma($turma, $trimestre, [
            [
                'aluno_id' => $alunoId,
                'disciplina_id' => $disciplinaId,
                'tipo' => strtoupper($tipo),
                'delta' => -1.0,
            ],
        ], $alunoId, $anoLectivo);
    }

    private function situacaoLinha(
        array $linha,
        Turma $turma,
        int $trimestre,
        int $classeNumero,
        array $meta,
        array $blindadas,
    ): array {
        if ($trimestre === 3 && in_array($classeNumero, [10, 11, 12], true) && $meta !== []) {
            $analise = $this->transicaoService->analisarAluno(
                $linha,
                (int) $turma->curso_id,
                $classeNumero,
                $meta,
                $blindadas,
                (int) ($turma->curso?->classe_max ?? 12),
            );

            return [
                'resultado' => $analise['resultado'] ?? '—',
                'resumo' => $analise['observacao'] ?? ($analise['situacao'] ?? ''),
                'total_negativas' => $analise['total_negativas'] ?? null,
            ];
        }

        $negativas = 0;
        $disciplinas = $linha['disciplinas'] ?? [];
        if (is_array($disciplinas)) {
            foreach ($disciplinas as $dados) {
                $valor = is_array($dados)
                    ? ($dados['MT'] ?? $dados['mt'] ?? $dados['valor'] ?? null)
                    : null;
                if ($valor !== null && (float) $valor < 10) {
                    $negativas++;
                }
            }
        }

        $resultado = $negativas > 2 ? 'NÃO APTO' : 'APTO';

        return [
            'resultado' => $resultado,
            'resumo' => "{$negativas} negativa(s)",
            'total_negativas' => $negativas,
        ];
    }

    /**
     * @param  list<array{aluno_id?: int, disciplina_id: int, tipo: string, delta: float}>  $ajustes
     */
    private function aplicarDeltas(array $linha, array $ajustes, int $alunoId): array
    {
        $clone = $linha;
        foreach ($ajustes as $ajuste) {
            if (isset($ajuste['aluno_id']) && (int) $ajuste['aluno_id'] !== $alunoId) {
                continue;
            }

            $discId = (int) $ajuste['disciplina_id'];
            $tipo = strtoupper((string) $ajuste['tipo']);
            $delta = (float) $ajuste['delta'];

            if (! isset($clone['disciplinas'][$discId]) || ! is_array($clone['disciplinas'][$discId])) {
                // tentar estrutura alternativa
                if (isset($clone['notas'][$discId]) && is_array($clone['notas'][$discId])) {
                    $valor = (float) ($clone['notas'][$discId][$tipo] ?? $clone['notas'][$discId][strtolower($tipo)] ?? 0);
                    $novo = max(0, min(20, $valor + $delta));
                    $clone['notas'][$discId][$tipo] = $novo;
                }

                continue;
            }

            $bucket = &$clone['disciplinas'][$discId];
            $chave = array_key_exists($tipo, $bucket) ? $tipo : strtolower($tipo);
            if (! array_key_exists($chave, $bucket) && array_key_exists(strtoupper($tipo), $bucket)) {
                $chave = strtoupper($tipo);
            }
            $valor = (float) ($bucket[$chave] ?? 0);
            $bucket[$chave] = max(0, min(20, $valor + $delta));
        }

        return $clone;
    }
}
