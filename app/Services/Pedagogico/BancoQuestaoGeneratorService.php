<?php

namespace App\Services\Pedagogico;

use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Pedagogico\PedBancoQuestao;
use App\Models\User;
use App\Services\AI\AIServiceInterface;
use App\Services\AI\PromptBuilder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class BancoQuestaoGeneratorService
{
    public function __construct(
        protected AIServiceInterface $aiService,
        protected PromptBuilder $promptBuilder,
        protected IaGenerationLogger $iaLogger,
        protected BancoQuestaoService $banco,
    ) {}

    /**
     * @param  array{disciplina_id:int,tipo:string,dificuldade:string,quantidade?:int,tema?:string,cotacao?:float,classe_id?:int|null}  $config
     * @return Collection<int, PedBancoQuestao>
     */
    public function gerar(User $user, array $config): Collection
    {
        $disciplinaId = (int) $config['disciplina_id'];
        if (! $this->banco->podeCriarNaDisciplina($user, $disciplinaId)) {
            abort(403, 'Não pode criar questões nesta disciplina.');
        }

        $disciplina = Disciplina::findOrFail($disciplinaId);
        $quantidade = max(1, min(10, (int) ($config['quantidade'] ?? 3)));
        $tipo = (string) $config['tipo'];
        $dificuldade = (string) $config['dificuldade'];
        $cotacao = (float) ($config['cotacao'] ?? 1);
        $tema = trim((string) ($config['tema'] ?? ''));
        $classeNome = '';
        if (! empty($config['classe_id'])) {
            $classeNome = Classe::find((int) $config['classe_id'])?->nome ?? '';
        }

        $tiposValidos = array_keys(PedBancoQuestao::tipos());
        if (! in_array($tipo, $tiposValidos, true)) {
            throw new InvalidArgumentException('Tipo de questão inválido.');
        }

        $contexto = [
            'quantidade' => (string) $quantidade,
            'disciplina' => $disciplina->nome,
            'classe' => $classeNome !== '' ? $classeNome : 'não especificada',
            'tipo' => $tipo,
            'dificuldade' => $dificuldade,
            'tema' => $tema !== '' ? $tema : 'conteúdos gerais da disciplina',
            'cotacao' => (string) $cotacao,
        ];

        $geracao = $this->iaLogger->iniciar($user, 'banco_questao', $contexto);

        try {
            $prompt = $this->promptBuilder->render('banco_questao', $contexto);
            $resultado = $this->aiService->chatJson(
                [
                    ['role' => 'system', 'content' => $this->promptBuilder->systemPedagogico()],
                    ['role' => 'user', 'content' => $prompt],
                ],
                [],
                [
                    'model' => config('pedagogico.models.banco_questao'),
                    'max_tokens' => (int) config('pedagogico.banco_questao_max_tokens', 4096),
                    'temperature' => 0.35,
                ]
            );

            $payload = $resultado['data'] ?? $resultado;
            $this->iaLogger->concluir($geracao, $resultado['meta'] ?? [], is_array($payload) ? $payload : null);

            $itens = $this->extrairQuestoes($payload);
            if ($itens === []) {
                throw new InvalidArgumentException('A IA não devolveu questões válidas.');
            }

            $criadas = collect();
            foreach (array_slice($itens, 0, $quantidade) as $item) {
                $dados = $this->mapearQuestao($item, $disciplinaId, $tipo, $dificuldade, $cotacao, $tema);
                $criadas->push($this->banco->criar($user, $dados));
            }

            return $criadas;
        } catch (\Throwable $e) {
            $this->iaLogger->falhar($geracao, $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param  mixed  $payload
     * @return array<int, array<string, mixed>>
     */
    protected function extrairQuestoes(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        if (isset($payload['questoes']) && is_array($payload['questoes'])) {
            return array_values(array_filter($payload['questoes'], 'is_array'));
        }

        // Resposta com uma única questão no root
        if (isset($payload['enunciado']) || isset($payload['texto'])) {
            return [$payload];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function mapearQuestao(
        array $item,
        int $disciplinaId,
        string $tipoPedido,
        string $dificuldadePedido,
        float $cotacaoPedido,
        string $tema,
    ): array {
        $tipo = $this->normalizarTipo((string) ($item['tipo'] ?? $tipoPedido));
        $enunciado = trim((string) ($item['enunciado'] ?? $item['texto'] ?? ''));
        if ($enunciado === '') {
            throw new InvalidArgumentException('Questão gerada sem enunciado.');
        }

        $dificuldade = $this->normalizarDificuldade((string) ($item['dificuldade'] ?? $dificuldadePedido));
        $cotacao = (float) ($item['cotacao'] ?? $cotacaoPedido);
        if ($cotacao < 0.5) {
            $cotacao = $cotacaoPedido;
        }

        $opcoes = null;
        if ($tipo === PedBancoQuestao::TIPO_ESCOLHA) {
            $raw = $item['opcoes'] ?? $item['opcoes_json'] ?? null;
            $opcoes = $this->normalizarOpcoes(is_array($raw) ? $raw : []);
            if (count($opcoes) < 2) {
                throw new InvalidArgumentException('Escolha múltipla sem opções suficientes.');
            }
        }

        $resposta = $item['resposta'] ?? $item['resposta_correcta'] ?? $item['resposta_sugerida'] ?? null;
        if (is_array($resposta)) {
            $resposta = $resposta['resposta'] ?? reset($resposta);
        }
        $resposta = is_scalar($resposta) ? trim((string) $resposta) : '';

        $tags = trim((string) ($item['tags'] ?? $tema));

        return [
            'disciplina_id' => $disciplinaId,
            'tipo' => $tipo,
            'enunciado' => $enunciado,
            'opcoes_json' => $opcoes,
            'resposta_json' => $resposta !== '' ? ['resposta' => $resposta] : null,
            'cotacao' => $cotacao,
            'dificuldade' => $dificuldade,
            'tags' => $tags !== '' ? $tags : null,
            'estado' => PedBancoQuestao::ESTADO_ACTIVA,
        ];
    }

    protected function normalizarTipo(string $tipo): string
    {
        $tipo = strtolower(trim($tipo));

        return match ($tipo) {
            'objectiva', 'multipla', 'múltipla', 'escolha', 'escolha_multipla', 'mcq' => PedBancoQuestao::TIPO_ESCOLHA,
            'vf', 'v/f', 'verdadeiro_falso', 'verdadeiro/falso' => PedBancoQuestao::TIPO_VF,
            'curta', 'resposta_curta', 'curta_resposta' => PedBancoQuestao::TIPO_CURTA,
            'desenvolvimento', 'dissertativa', 'aberta' => PedBancoQuestao::TIPO_DESENV,
            default => in_array($tipo, array_keys(PedBancoQuestao::tipos()), true)
                ? $tipo
                : PedBancoQuestao::TIPO_ESCOLHA,
        };
    }

    protected function normalizarDificuldade(string $diff): string
    {
        $diff = strtolower(trim($diff));

        return match ($diff) {
            'facil', 'fácil', 'easy' => PedBancoQuestao::DIFF_FACIL,
            'dificil', 'difícil', 'hard' => PedBancoQuestao::DIFF_DIFICIL,
            'medio', 'médio', 'media', 'média', 'medium' => PedBancoQuestao::DIFF_MEDIA,
            default => PedBancoQuestao::DIFF_MEDIA,
        };
    }

    /**
     * @param  array<mixed>  $raw
     * @return array<string, string>
     */
    protected function normalizarOpcoes(array $raw): array
    {
        $out = [];
        $letras = ['A', 'B', 'C', 'D'];

        // Já em formato A=>texto
        foreach ($letras as $letra) {
            if (array_key_exists($letra, $raw) || array_key_exists(strtolower($letra), $raw)) {
                $val = $raw[$letra] ?? $raw[strtolower($letra)] ?? null;
                if (filled($val)) {
                    $out[$letra] = trim((string) $val);
                }
            }
        }

        if ($out !== []) {
            return $out;
        }

        // Lista indexada
        $i = 0;
        foreach (array_values($raw) as $val) {
            if ($i >= 4) {
                break;
            }
            if (filled($val)) {
                $out[$letras[$i]] = trim((string) $val);
                $i++;
            }
        }

        return $out;
    }
}
