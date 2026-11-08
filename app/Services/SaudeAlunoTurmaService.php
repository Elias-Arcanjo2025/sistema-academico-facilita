<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\Nota;
use App\Support\PautaResultadoTransicao;
use Illuminate\Support\Facades\Cache;

/**
 * Saúde académica da turma do aluno — visão individual + contexto da turma.
 */
class SaudeAlunoTurmaService
{
    public function __construct(
        private RecuperacaoTransicaoService $transicaoService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indicadores(Aluno $aluno, ?string $anoLectivo = null): array
    {
        $anoLectivo = $anoLectivo ?? Configuracao::getAnoLectivoActual();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);
        $turma = $aluno->turma;

        if (! $turma) {
            return ['titulo' => 'Saúde da minha turma', 'vazio' => true];
        }

        return Cache::remember(
            'saude_aluno_turma.'.$aluno->id.'.'.$anoLectivoId,
            300,
            function () use ($aluno, $turma, $anoLectivo, $anoLectivoId) {
                $notaMinima = (float) (Configuracao::get('nota_minima_aprovacao') ?: 10);
                $trimestre = Configuracao::getTrimestreActual();

                $negativasAluno = $aluno->notas()
                    ->whereNull('archived_at')
                    ->where('ano_lectivo_id', $anoLectivoId)
                    ->whereIn('tipo', [Nota::TIPO_MT, Nota::TIPO_CFD, Nota::TIPO_CA10, Nota::TIPO_CA11, Nota::TIPO_CA12])
                    ->where('valor', '<', $notaMinima)
                    ->count();

                $totalAlunosTurma = $turma->alunos()->where('activo', true)->count();
                $notasTurma = Nota::where('turma_id', $turma->id)
                    ->where('ano_lectivo_id', $anoLectivoId)
                    ->where('trimestre', $trimestre)
                    ->whereIn('tipo', [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT])
                    ->count();
                $cobertura = min(100, round(($notasTurma / max(1, $totalAlunosTurma * 3)) * 100, 1));

                $resultadoTransicao = null;
                $classe = $this->transicaoService->classeNumero($turma);
                if (in_array($classe, [10, 11, 12], true)) {
                    $pauta = app(PautaService::class)->gerarPautaTrimestralCompleta($turma->id, 3, $anoLectivo);
                    $linha = collect($pauta['alunos'])->first(fn ($a) => ($a['aluno']->id ?? $a['aluno_id'] ?? 0) == $aluno->id);
                    if ($linha) {
                        $analise = $this->transicaoService->analisarAluno(
                            $linha,
                            (int) $turma->curso_id,
                            $classe,
                            $pauta['meta_disciplinas_transicao'] ?? [],
                            $this->transicaoService->blindadasParaCurso((int) $turma->curso_id),
                        );
                        $resultadoTransicao = $analise['resultado'] ?? null;
                    }
                }

                $score = 80;
                $score -= min(40, $negativasAluno * 8);
                if ($resultadoTransicao === PautaResultadoTransicao::RESULTADO_N_TRANSITA) {
                    $score -= 25;
                } elseif (PautaResultadoTransicao::ehTransitaComDeficiencia($resultadoTransicao ?? '')) {
                    $score -= 10;
                }
                $score = max(0, min(100, $score));

                return [
                    'titulo' => 'Saúde da minha turma',
                    'turma' => $turma->nome,
                    'curso' => $turma->curso?->nome,
                    'classe' => $turma->classe?->nome,
                    'total_colegas' => max(0, $totalAlunosTurma - 1),
                    'negativas_aluno' => $negativasAluno,
                    'resultado_transicao' => $resultadoTransicao,
                    'cobertura_turma_pct' => $cobertura,
                    'trimestre_actual' => $trimestre,
                    'score' => $score,
                    'score_label' => match (true) {
                        $score >= 80 => 'Bom',
                        $score >= 60 => 'Atenção',
                        default => 'Precisa melhorar',
                    },
                    'ano_lectivo' => $anoLectivo,
                ];
            }
        );
    }
}
