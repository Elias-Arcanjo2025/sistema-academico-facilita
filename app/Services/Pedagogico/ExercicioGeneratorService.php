<?php

namespace App\Services\Pedagogico;

use App\Models\Pedagogico\PedAula;
use App\Models\Pedagogico\PedExercicio;
use App\Models\Pedagogico\PedPlanificacao;
use App\Models\Pedagogico\PedQuinzena;
use App\Models\User;
use App\Services\AI\AIServiceInterface;
use App\Services\AI\PromptBuilder;
use Illuminate\Support\Facades\DB;

class ExercicioGeneratorService
{
    public function __construct(
        protected AIServiceInterface $aiService,
        protected PromptBuilder $promptBuilder,
        protected IaGenerationLogger $iaLogger,
    ) {}

    public function gerar(
        PedPlanificacao $planificacao,
        string $tipo,
        string $dificuldade = 'medio',
        ?PedQuinzena $quinzena = null,
        ?User $user = null,
    ): PedExercicio {
        $user = $user ?? auth()->user();
        $planificacao->loadMissing(['turma.classe', 'disciplina']);
        $quinzena ??= $planificacao->quinzenas()->orderBy('numero')->first();

        $conteudosMinistrados = $this->obterConteudosMinistrados($planificacao, $quinzena);

        $contexto = [
            'tipo' => $tipo,
            'dificuldade' => $dificuldade,
            'disciplina' => $planificacao->disciplina?->nome ?? '',
            'classe' => $planificacao->turma?->classe?->nome ?? $planificacao->turma?->nome ?? '',
            'tema' => $quinzena?->tema ?? '',
            'conteudos_ministrados' => $conteudosMinistrados,
        ];

        $geracao = $this->iaLogger->iniciar($user, 'exercicio', $contexto);

        try {
            $prompt = $this->promptBuilder->render('exercicio', $contexto);
            $resultado = $this->aiService->chatJson(
                [
                    ['role' => 'system', 'content' => $this->promptBuilder->systemPedagogico()],
                    ['role' => 'user', 'content' => $prompt],
                ],
                [],
                ['model' => config('pedagogico.models.exercicio')]
            );

            $conteudo = $resultado['data'] ?? $resultado;
            $this->iaLogger->concluir($geracao, $resultado['meta'] ?? [], $conteudo);

            return DB::transaction(function () use ($planificacao, $quinzena, $tipo, $dificuldade, $geracao, $conteudo) {
                return PedExercicio::create([
                    'planificacao_id' => $planificacao->id,
                    'quinzena_id' => $quinzena?->id,
                    'tipo' => $tipo,
                    'dificuldade' => $dificuldade,
                    'conteudo_json' => $conteudo,
                    'geracao_ia_id' => $geracao->id,
                ]);
            });
        } catch (\Throwable $e) {
            $this->iaLogger->falhar($geracao, $e->getMessage());
            throw $e;
        }
    }

    protected function obterConteudosMinistrados(PedPlanificacao $planificacao, ?PedQuinzena $quinzena): string
    {
        $query = $planificacao->aulas()
            ->where('estado', PedAula::ESTADO_MINISTRADA)
            ->where('data', '<=', now()->toDateString());

        if ($quinzena) {
            $query->where('quinzena_id', $quinzena->id);
        }

        return $query->get()
            ->map(fn (PedAula $aula) => trim(($aula->tema_ministrado ?? '').': '.($aula->conteudo_ministrado ?? '')))
            ->filter()
            ->implode("\n");
    }
}
