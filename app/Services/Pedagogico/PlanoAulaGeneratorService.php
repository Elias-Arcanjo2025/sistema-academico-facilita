<?php

namespace App\Services\Pedagogico;

use App\Models\Pedagogico\PedAula;
use App\Models\Pedagogico\PedPlanoAula;
use App\Models\Pedagogico\PedPlanificacao;
use App\Models\Pedagogico\PedQuinzena;
use App\Models\User;
use App\Services\AI\AIServiceInterface;
use App\Services\AI\PromptBuilder;
use Illuminate\Support\Facades\DB;

class PlanoAulaGeneratorService
{
    public function __construct(
        protected AIServiceInterface $aiService,
        protected PromptBuilder $promptBuilder,
        protected IaGenerationLogger $iaLogger,
    ) {}

    public function gerar(
        PedPlanificacao $planificacao,
        ?PedQuinzena $quinzena = null,
        ?PedAula $aula = null,
        ?User $user = null,
        int $tempos = 1,
    ): PedPlanoAula {
        $user = $user ?? auth()->user();
        $planificacao->loadMissing(['turma.classe', 'disciplina']);
        $quinzena ??= $aula?->quinzena ?? $planificacao->quinzenas()->with('temas')->orderBy('numero')->first();

        if (! $quinzena) {
            throw new \Exception('É necessária uma quinzena para gerar o plano de aula.');
        }

        $quinzena->loadMissing('temas');
        $listaTemas = $quinzena->listaTemas();
        $numTemas = max(1, count($listaTemas));

        $tempos = max(1, min((int) config('pedagogico.tempos_max', 8), $tempos));
        if ($aula?->tempos) {
            $tempos = (int) $aula->tempos;
        }

        $escala = TempoAulaHelper::escalaConteudo($tempos, $numTemas);
        $duracaoMinutos = TempoAulaHelper::duracaoMinutos($tempos);

        $contexto = [
            'classe' => $planificacao->turma?->classe?->nome ?? $planificacao->turma?->nome ?? '',
            'disciplina' => $planificacao->disciplina?->nome ?? '',
            'trimestre' => $planificacao->trimestre,
            'tema' => $aula?->tema_planeado ?? ($listaTemas[0] ?? $quinzena->tema),
            'tema_dia' => $aula?->tema_planeado ?? $quinzena->tema,
            'temas_periodo' => $listaTemas,
            'subtemas' => $listaTemas,
            'competencias' => $quinzena->competencias ?? [],
            'objetivos' => $quinzena->objetivos ?? [],
            'objetivos_geral' => $aula?->objetivos_geral ?? $quinzena->objetivos_geral,
            'objetivos_especificos' => $aula?->objetivos_especificos ?? $quinzena->objetivos_especificos,
            'data_aula' => $aula?->data?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'tempos' => $tempos,
            'duracao_minutos' => $duracaoMinutos,
            'num_temas_periodo' => $numTemas,
            'paragrafos_ditar' => $escala['paragrafos'],
            'palavras_ditar' => $escala['palavras_ditar'],
            'blocos_aula' => $escala['blocos_aula'],
            'minutos_por_bloco' => $escala['minutos_por_bloco'],
            'exercicios_qtd' => $escala['exercicios'],
            'tarefas_qtd' => $escala['tarefas'],
            'conceitos_qtd' => $escala['conceitos'],
        ];

        $geracao = $this->iaLogger->iniciar($user, 'plano_aula', $contexto);

        try {
            $prompt = $this->promptBuilder->render('plano_aula', $contexto);
            $modelOptions = [
                'model' => config('pedagogico.models.plano_aula'),
                'max_tokens' => TempoAulaHelper::maxTokensGeracao($tempos, $numTemas),
                'temperature' => 0.3,
            ];

            try {
                $resultado = $this->chamarIaPlano($prompt, $modelOptions);
            } catch (\RuntimeException $e) {
                if (! str_contains($e->getMessage(), 'JSON válido')) {
                    throw $e;
                }
                $fallback = config('pedagogico.models.plano_aula_fallback');
                if ($fallback && $fallback !== $modelOptions['model']) {
                    $modelOptions['model'] = $fallback;
                    $resultado = $this->chamarIaPlano($prompt, $modelOptions);
                } else {
                    throw $e;
                }
            }

            $conteudo = $this->normalizarConteudo($resultado['data'] ?? $resultado);
            $conteudo['_meta'] = [
                'tempos' => $tempos,
                'duracao_minutos' => $duracaoMinutos,
                'palavras_alvo' => $escala['palavras_ditar'],
                'exercicios_alvo' => $escala['exercicios'],
                'tarefas_alvo' => $escala['tarefas'],
                'conceitos_alvo' => $escala['conceitos'],
                'blocos_aula' => $escala['blocos_aula'],
                'num_temas_periodo' => $numTemas,
            ];
            $this->iaLogger->concluir($geracao, $resultado['meta'] ?? [], $conteudo);

            return DB::transaction(function () use ($planificacao, $quinzena, $aula, $geracao, $conteudo, $tempos) {
                $versao = (int) PedPlanoAula::where('planificacao_id', $planificacao->id)
                    ->where('quinzena_id', $quinzena->id)
                    ->when($aula, fn ($q) => $q->where('aula_id', $aula->id))
                    ->max('versao') + 1;

                return PedPlanoAula::create([
                    'planificacao_id' => $planificacao->id,
                    'quinzena_id' => $quinzena->id,
                    'aula_id' => $aula?->id,
                    'tempos' => $tempos,
                    'conteudo_json' => $conteudo,
                    'versao' => $versao,
                    'estado' => 'rascunho',
                    'geracao_ia_id' => $geracao->id,
                ]);
            });
        } catch (\Throwable $e) {
            $this->iaLogger->falhar($geracao, $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizarConteudo(array $data): array
    {
        $chaves = [
            'tema', 'objetivos', 'competencias', 'introducao', 'desenvolvimento',
            'conteudo_ditar', 'conceitos', 'exemplos', 'perguntas_interacao', 'exercicios_sala',
            'exercicios_resolvidos', 'tarefa_casa', 'conclusao',
        ];

        $conteudo = [];
        foreach ($chaves as $chave) {
            $conteudo[$chave] = $data[$chave] ?? (str_ends_with($chave, 's') ? [] : '');
        }

        if (is_string($conteudo['tarefa_casa'])) {
            $conteudo['tarefa_casa'] = array_filter([$conteudo['tarefa_casa']]);
        }

        if (! is_array($conteudo['conceitos'])) {
            $conteudo['conceitos'] = [];
        }

        return $conteudo;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function chamarIaPlano(string $prompt, array $options): array
    {
        return $this->aiService->chatJson(
            [
                ['role' => 'system', 'content' => $this->promptBuilder->systemPedagogico()],
                ['role' => 'user', 'content' => $prompt],
            ],
            [],
            $options
        );
    }
}
