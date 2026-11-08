<?php

namespace App\Services\Pedagogico;

use App\Models\Pedagogico\PedAula;
use App\Models\Pedagogico\PedExecucaoAnalise;
use App\Models\Pedagogico\PedPlanificacao;
use App\Models\User;
use App\Services\AI\AIServiceInterface;
use App\Services\AI\PromptBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ExecucaoPlanificacaoService
{
    public function __construct(
        protected AulaMinistradaService $aulaMinistradaService,
        protected AIServiceInterface $aiService,
        protected PromptBuilder $promptBuilder,
        protected IaGenerationLogger $iaLogger,
    ) {}

    public function analisar(PedPlanificacao $planificacao, ?User $user = null, bool $comIa = true): PedExecucaoAnalise
    {
        $user = $user ?? auth()->user();
        $planificacao->loadMissing(['turma.classe', 'disciplina', 'quinzenas', 'aulas.quinzena']);

        $stats = $this->calcularEstatisticas($planificacao);
        $recomendacoes = [];
        $previsaoConclusao = null;
        $geracaoId = null;

        if ($comIa) {
            $geracao = $this->iaLogger->iniciar($user, 'execucao', $stats, $planificacao);

            try {
                $prompt = $this->promptBuilder->render('execucao', [
                    'disciplina' => $planificacao->disciplina?->nome ?? '',
                    'classe' => $planificacao->turma?->classe?->nome ?? $planificacao->turma?->nome ?? '',
                    'trimestre' => $planificacao->trimestre,
                    'percentagem' => $stats['percentagem'],
                    'planeadas' => $stats['total_planeadas'],
                    'ministradas' => $stats['total_ministradas'],
                    'atrasos' => json_encode($stats['atrasos_por_quinzena'], JSON_UNESCAPED_UNICODE),
                    'adiantados' => json_encode($stats['adiantados'], JSON_UNESCAPED_UNICODE),
                ]);

                $resultado = $this->aiService->chatJson(
                    [
                        ['role' => 'system', 'content' => $this->promptBuilder->systemPedagogico()],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    [],
                    ['model' => config('pedagogico.models.execucao')]
                );

                $data = $resultado['data'] ?? $resultado;
                $recomendacoes = $data['recomendacoes'] ?? [];
                $previsaoConclusao = $data['previsao_conclusao'] ?? null;
                $geracaoId = $geracao->id;

                $this->iaLogger->concluir($geracao, $resultado['meta'] ?? [], $data);
            } catch (\Throwable $e) {
                $this->iaLogger->falhar($geracao, $e->getMessage());
            }
        }

        return DB::transaction(function () use ($planificacao, $stats, $recomendacoes, $previsaoConclusao, $geracaoId) {
            return PedExecucaoAnalise::create([
                'planificacao_id' => $planificacao->id,
                'percentagem_execucao' => $stats['percentagem'],
                'conteudos_atraso_json' => $stats['atrasos_por_quinzena'],
                'conteudos_adiantados_json' => $stats['adiantados'],
                'previsao_conclusao' => $previsaoConclusao,
                'recomendacoes_json' => $recomendacoes,
                'geracao_ia_id' => $geracaoId,
                'calculado_em' => now(),
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function calcularEstatisticas(PedPlanificacao $planificacao): array
    {
        $hoje = Carbon::today();
        $aulas = $planificacao->aulas;

        $totalPlaneadas = $aulas->where('estado', '!=', PedAula::ESTADO_CANCELADA)->count();
        $totalMinistradas = $aulas->where('estado', PedAula::ESTADO_MINISTRADA)->count();
        $percentagem = $this->aulaMinistradaService->percentagemExecucao($planificacao);

        $atrasosPorQuinzena = [];
        foreach ($planificacao->quinzenas as $quinzena) {
            $atrasadas = $aulas->filter(function (PedAula $aula) use ($hoje, $quinzena) {
                return (int) $aula->quinzena_id === (int) $quinzena->id
                    && $aula->data->lte($hoje)
                    && in_array($aula->estado, [PedAula::ESTADO_PLANEADA, PedAula::ESTADO_ADIADA], true);
            });

            if ($atrasadas->isNotEmpty()) {
                $atrasosPorQuinzena[] = [
                    'quinzena' => $quinzena->numero,
                    'tema' => $quinzena->tema,
                    'aulas_atrasadas' => $atrasadas->count(),
                    'datas' => $atrasadas->pluck('data')->map->format('Y-m-d')->values()->all(),
                ];
            }
        }

        $adiantados = $aulas->filter(function (PedAula $aula) use ($hoje) {
            return $aula->estado === PedAula::ESTADO_MINISTRADA
                && $aula->data->gt($hoje);
        })->map(fn (PedAula $aula) => [
            'data' => $aula->data->format('Y-m-d'),
            'tema' => $aula->tema_ministrado,
        ])->values()->all();

        return [
            'percentagem' => $percentagem,
            'total_planeadas' => $totalPlaneadas,
            'total_ministradas' => $totalMinistradas,
            'atrasos_por_quinzena' => $atrasosPorQuinzena,
            'adiantados' => $adiantados,
        ];
    }
}
