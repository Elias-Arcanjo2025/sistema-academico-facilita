<?php

namespace App\Services\Pedagogico;

use App\Models\Nota;
use App\Models\Pedagogico\PedAula;
use App\Models\Pedagogico\PedPlanificacao;
use App\Models\Pedagogico\PedProva;
use App\Models\User;
use App\Services\AI\AIServiceInterface;
use App\Services\AI\PromptBuilder;
use Illuminate\Support\Facades\DB;

class ProvaGeneratorService
{
    public function __construct(
        protected AIServiceInterface $aiService,
        protected PromptBuilder $promptBuilder,
        protected IaGenerationLogger $iaLogger,
        protected MatrizProvaService $matrizProvaService,
        protected ChaveCorrecaoService $chaveCorrecaoService,
    ) {}

    public function gerar(
        PedPlanificacao $planificacao,
        string $tipo,
        ?User $user = null,
        array $config = [],
    ): PedProva {
        if (! in_array($tipo, [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT], true)) {
            throw new \InvalidArgumentException('Tipo de prova inválido. Use MAC, PP ou PT.');
        }

        $user = $user ?? auth()->user();
        $planificacao->loadMissing(['turma.classe', 'disciplina']);

        $tempoMinutos = (int) ($config['tempo_minutos'] ?? 90);
        $cotacaoTotal = (float) ($config['cotacao_total'] ?? 20);

        [$conteudo, $geracao] = $this->gerarConteudoIa(
            $planificacao,
            $tipo,
            $user,
            $tempoMinutos,
            $cotacaoTotal,
        );

        return DB::transaction(function () use ($planificacao, $tipo, $tempoMinutos, $cotacaoTotal, $config, $geracao, $conteudo) {
            $prova = PedProva::create([
                'tipo' => $tipo,
                'planificacao_id' => $planificacao->id,
                'turma_id' => $planificacao->turma_id,
                'disciplina_id' => $planificacao->disciplina_id,
                'config_json' => $config,
                'conteudo_json' => $conteudo,
                'tempo_minutos' => $tempoMinutos,
                'cotacao_total' => $conteudo['cotacao_total'] ?? $cotacaoTotal,
                'estado' => 'rascunho',
                'geracao_ia_id' => $geracao->id,
            ]);

            $this->matrizProvaService->construir($prova);
            $this->chaveCorrecaoService->construir($prova);

            return $prova->fresh(['matriz', 'chaveCorrecao']);
        });
    }

    public function regenerar(PedProva $prova, ?User $user = null): PedProva
    {
        $user = $user ?? auth()->user();
        $prova->loadMissing(['planificacao.turma.classe', 'planificacao.disciplina']);
        $planificacao = $prova->planificacao;

        if (! $planificacao) {
            throw new \Exception('Planificação associada à prova não encontrada.');
        }

        $config = $prova->config_json ?? [];
        $tempoMinutos = (int) ($config['tempo_minutos'] ?? $prova->tempo_minutos ?? 90);
        $cotacaoTotal = (float) ($config['cotacao_total'] ?? $prova->cotacao_total ?? 20);

        [$conteudo, $geracao] = $this->gerarConteudoIa(
            $planificacao,
            $prova->tipo,
            $user,
            $tempoMinutos,
            $cotacaoTotal,
        );

        return DB::transaction(function () use ($prova, $tempoMinutos, $cotacaoTotal, $geracao, $conteudo) {
            $prova->update([
                'conteudo_json' => $conteudo,
                'tempo_minutos' => $tempoMinutos,
                'cotacao_total' => $conteudo['cotacao_total'] ?? $cotacaoTotal,
                'geracao_ia_id' => $geracao->id,
                'estado' => 'rascunho',
            ]);

            $this->matrizProvaService->construir($prova);
            $this->chaveCorrecaoService->construir($prova);

            return $prova->fresh(['matriz', 'chaveCorrecao']);
        });
    }

    /**
     * @return array{0: array<string, mixed>, 1: object}
     */
    protected function gerarConteudoIa(
        PedPlanificacao $planificacao,
        string $tipo,
        User $user,
        int $tempoMinutos,
        float $cotacaoTotal,
    ): array {
        $conteudosMinistrados = $this->obterConteudosMinistrados($planificacao);
        if ($conteudosMinistrados === '') {
            throw new \Exception('Não há conteúdos na planificação para gerar a prova. Defina temas/aulas ou marque planos como dados.');
        }

        $contexto = [
            'tipo_prova' => $tipo,
            'disciplina' => $planificacao->disciplina?->nome ?? '',
            'classe' => $planificacao->turma?->classe?->nome ?? $planificacao->turma?->nome ?? '',
            'tempo_minutos' => $tempoMinutos,
            'cotacao_total' => $cotacaoTotal,
            'conteudos_ministrados' => $conteudosMinistrados,
        ];

        $geracao = $this->iaLogger->iniciar($user, 'prova', $contexto);

        try {
            $prompt = $this->promptBuilder->render('prova', $contexto);
            $resultado = $this->aiService->chatJson(
                [
                    ['role' => 'system', 'content' => $this->promptBuilder->systemPedagogico()],
                    ['role' => 'user', 'content' => $prompt],
                ],
                [],
                [
                    'model' => config('pedagogico.models.prova'),
                    'max_tokens' => (int) config('pedagogico.prova_max_tokens', 4096),
                    'temperature' => 0.3,
                ]
            );

            $conteudo = $resultado['data'] ?? $resultado;
            $this->iaLogger->concluir($geracao, $resultado['meta'] ?? [], $conteudo);

            return [$conteudo, $geracao];
        } catch (\Throwable $e) {
            $this->iaLogger->falhar($geracao, $e->getMessage());
            throw $e;
        }
    }

    protected function obterConteudosMinistrados(PedPlanificacao $planificacao): string
    {
        $ministradas = $planificacao->aulas()
            ->where('estado', PedAula::ESTADO_MINISTRADA)
            ->where('data', '<=', now()->toDateString())
            ->get()
            ->map(fn (PedAula $aula) => trim(($aula->tema_ministrado ?? $aula->tema_planeado ?? '').': '.($aula->conteudo_ministrado ?? '')))
            ->filter()
            ->implode("\n");

        if ($ministradas !== '') {
            return $ministradas;
        }

        $planificacao->loadMissing(['aulas', 'quinzenas.temas', 'planosAula']);

        $dePlanos = $planificacao->planosAula
            ->whereIn('estado', ['dado', 'aprovado'])
            ->map(function ($plano) {
                $c = $plano->conteudo_json ?? [];

                return trim(($c['tema'] ?? '')."\n".($c['conteudo_ditar'] ?? ''));
            })
            ->filter()
            ->implode("\n\n");

        if ($dePlanos !== '') {
            return $dePlanos;
        }

        $deAulas = $planificacao->aulas
            ->sortBy('data')
            ->map(fn (PedAula $aula) => trim(($aula->tema_planeado ?? '').' '.($aula->objetivos_geral ?? '')))
            ->filter()
            ->implode("\n");

        if ($deAulas !== '') {
            return $deAulas;
        }

        return $planificacao->quinzenas
            ->flatMap(fn ($q) => $q->listaTemas())
            ->filter()
            ->implode("\n");
    }
}
