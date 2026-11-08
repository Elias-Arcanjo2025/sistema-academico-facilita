<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\Turma;
use App\Support\PautaResultadoTransicao;

/**
 * Simula o impacto de uma nota de recuperação/recurso no resultado de transição T3.
 */
class SimuladorTransicaoService
{
    public function __construct(
        private RecuperacaoTransicaoService $transicaoService,
        private PautaService $pautaService,
    ) {}

    /**
     * @param  array<int, float|null>  $notasSimuladas  disciplina_id => nota (null remove)
     * @return array<string, mixed>
     */
    public function simular(
        Turma $turma,
        int $alunoId,
        string $tipoExame,
        array $notasSimuladas,
        ?string $anoLectivo = null,
    ): array {
        $classeNumero = $this->transicaoService->classeNumero($turma);

        if (! in_array($classeNumero, [10, 11, 12], true)) {
            return ['erro' => 'Simulador disponível apenas para 10.ª, 11.ª e 12.ª classes.'];
        }

        $anoLectivo = $anoLectivo
            ?? $turma->anoLectivo?->nome
            ?? $turma->ano_lectivo
            ?? Configuracao::getAnoLectivoActual();

        $pauta = $this->pautaService->gerarPautaTrimestralCompleta($turma->id, 3, $anoLectivo);
        $alunoLinha = collect($pauta['alunos'])->first(function (array $a) use ($alunoId) {
            $id = $a['aluno']->id ?? ($a['aluno_id'] ?? 0);

            return (int) $id === $alunoId;
        });

        if (! $alunoLinha) {
            return ['erro' => 'Aluno não encontrado na pauta T3.'];
        }

        $meta = $pauta['meta_disciplinas_transicao'] ?? $this->transicaoService->metaParaTurma(
            (int) $turma->curso_id,
            $pauta['disciplinas']->pluck('id')->all(),
            $classeNumero,
        );
        $blindadas = $this->transicaoService->blindadasParaCurso((int) $turma->curso_id);

        $antes = $this->transicaoService->analisarAluno(
            $alunoLinha,
            (int) $turma->curso_id,
            $classeNumero,
            $meta,
            $blindadas,
        );

        $simulado = $this->aplicarNotasSimuladas($alunoLinha, $tipoExame, $notasSimuladas);

        $depois = $this->transicaoService->analisarAluno(
            $simulado,
            (int) $turma->curso_id,
            $classeNumero,
            $meta,
            $blindadas,
        );

        $melhoria = $this->classificarMudanca($antes['resultado'] ?? '', $depois['resultado'] ?? '');

        return [
            'aluno_id' => $alunoId,
            'tipo_exame' => $tipoExame,
            'antes' => [
                'resultado' => $antes['resultado'] ?? '',
                'situacao' => $antes['situacao'] ?? '',
                'negativas' => count($antes['disciplinas_recuperacao'] ?? []),
                'observacao' => $antes['observacao'] ?? '',
            ],
            'depois' => [
                'resultado' => $depois['resultado'] ?? '',
                'situacao' => $depois['situacao'] ?? '',
                'negativas' => count($depois['disciplinas_recuperacao'] ?? []),
                'observacao' => $depois['observacao'] ?? '',
            ],
            'mudou' => ($antes['resultado'] ?? '') !== ($depois['resultado'] ?? ''),
            'classificacao' => $melhoria,
            'transita' => ($depois['resultado'] ?? '') === PautaResultadoTransicao::RESULTADO_TRANSITA,
        ];
    }

    /**
     * @param  array<string, mixed>  $alunoLinha
     * @param  array<int, float|null>  $notasSimuladas
     * @return array<string, mixed>
     */
    private function aplicarNotasSimuladas(array $alunoLinha, string $tipoExame, array $notasSimuladas): array
    {
        $copia = $alunoLinha;
        $campo = $tipoExame === 'recurso' ? 'recurso' : 'recuperacao';

        foreach ($notasSimuladas as $discId => $nota) {
            $discId = (int) $discId;
            if (! isset($copia['disciplinas'][$discId])) {
                $copia['disciplinas'][$discId] = [];
            }
            if ($nota === null || $nota === '') {
                unset($copia['disciplinas'][$discId][$campo]);
            } else {
                $copia['disciplinas'][$discId][$campo] = (float) $nota;
            }
        }

        return $copia;
    }

    private function classificarMudanca(string $antes, string $depois): string
    {
        if ($antes === $depois) {
            return 'igual';
        }

        $pesos = [
            PautaResultadoTransicao::RESULTADO_N_TRANSITA => 0,
            PautaResultadoTransicao::RESULTADO_TRANSITA_COM_DEFICIENCIA => 1,
            PautaResultadoTransicao::RESULTADO_TRANSITA => 2,
        ];

        $pAntes = $pesos[$antes] ?? -1;
        $pDepois = $pesos[$depois] ?? -1;

        if ($pDepois > $pAntes) {
            return 'melhoria';
        }

        return 'piora';
    }
}
