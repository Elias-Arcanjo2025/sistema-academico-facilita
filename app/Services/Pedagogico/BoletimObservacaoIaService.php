<?php

namespace App\Services\Pedagogico;

use App\Models\User;
use App\Services\AI\AIServiceInterface;
use App\Services\AI\PromptBuilder;
use Illuminate\Support\Facades\Log;

/**
 * Gera observação textual para boletim via IA, com fallback por regras.
 */
class BoletimObservacaoIaService
{
    public function __construct(
        protected AIServiceInterface $aiService,
        protected PromptBuilder $promptBuilder,
        protected IaGenerationLogger $iaLogger,
    ) {}

    /**
     * @param  array{
     *   situacao?:string,
     *   media?:float|int|null,
     *   negativas?:int,
     *   trimestre?:int|null,
     *   aluno?:string,
     *   disciplinas_negativas?:string
     * }  $dados
     */
    public function gerar(array $dados, ?User $user = null, ?callable $fallback = null): string
    {
        $fallbackTexto = $fallback ? (string) $fallback() : $this->fallbackLocal($dados);

        if (! config('pedagogico.boletim_obs_ia', true)) {
            return $fallbackTexto;
        }

        $user = $user ?? auth()->user();
        if (! $user) {
            return $fallbackTexto;
        }

        $contexto = [
            'aluno' => $dados['aluno'] ?? 'Aluno',
            'situacao' => $dados['situacao'] ?? 'pendente',
            'media' => $dados['media'] ?? '—',
            'negativas' => (int) ($dados['negativas'] ?? 0),
            'trimestre' => $dados['trimestre'] ? $dados['trimestre'].'.º trimestre' : 'boletim anual',
            'disciplinas_negativas' => $dados['disciplinas_negativas'] ?? 'nenhuma',
        ];

        $geracao = $this->iaLogger->iniciar($user, 'boletim_observacao', $contexto);

        try {
            $prompt = $this->promptBuilder->render('boletim_observacao', $contexto);
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

            $texto = trim((string) ($conteudo['observacao'] ?? $conteudo['texto'] ?? ''));
            if ($texto === '') {
                return $fallbackTexto;
            }

            return mb_substr($texto, 0, 600);
        } catch (\Throwable $e) {
            $this->iaLogger->falhar($geracao, $e->getMessage());
            Log::info('BoletimObservacaoIa fallback: '.$e->getMessage());

            return $fallbackTexto;
        }
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    protected function fallbackLocal(array $dados): string
    {
        $situacao = $dados['situacao'] ?? 'pendente';
        $media = $dados['media'] ?? null;
        $negativas = (int) ($dados['negativas'] ?? 0);
        $trimestre = $dados['trimestre'] ?? null;

        if ($trimestre) {
            $obs = "Este boletim refere-se exclusivamente ao {$trimestre}.º Trimestre. ";
            $obs .= $negativas === 0
                ? 'O(A) aluno(a) não apresenta disciplinas com nota negativa neste trimestre.'
                : "O(A) aluno(a) apresenta {$negativas} disciplina(s) com nota negativa neste trimestre.";

            return $obs.' A situação final será determinada após a conclusão de todos os trimestres.';
        }

        if ($situacao === 'aprovado') {
            $obs = 'O(A) aluno(a) foi APROVADO(A)';
            if ($media !== null) {
                $obs .= ' com média geral de '.number_format((float) $media, 0).' valores';
            }
            $obs .= '.';
            $obs .= $negativas > 0
                ? " Apresenta {$negativas} disciplina(s) com nota negativa."
                : ' Não apresenta disciplinas com nota negativa.';

            return $obs;
        }

        if ($situacao === 'reprovado') {
            return 'O(A) aluno(a) foi REPROVADO(A)'
                .($media !== null ? ' com média geral de '.number_format((float) $media, 0).' valores' : '')
                .". Apresenta {$negativas} disciplina(s) com nota negativa.";
        }

        return 'Situação pendente de avaliação final.';
    }
}
