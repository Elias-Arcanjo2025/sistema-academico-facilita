<?php

namespace App\Services\Pedagogico;

use App\Models\Pedagogico\PedChaveCorrecao;
use App\Models\Pedagogico\PedProva;
use App\Support\ProvaQuestaoHelper;
use Illuminate\Support\Str;

class ChaveCorrecaoService
{
    public function construir(PedProva $prova): PedChaveCorrecao
    {
        $conteudo = $prova->conteudo_json ?? [];
        $questoes = $conteudo['questoes'] ?? [];

        $respostas = [];
        $criterios = [];
        $pontuacaoTotal = 0.0;

        foreach ($questoes as $questao) {
            if (! is_array($questao)) {
                continue;
            }

            $subitens = $questao['subitens'] ?? [];
            if ($subitens !== []) {
                foreach ($subitens as $idx => $sub) {
                    if (! is_array($sub)) {
                        continue;
                    }
                    $ref = ProvaQuestaoHelper::refSubitem($questao, $sub, $idx);
                    $cotacao = (float) ($sub['cotacao'] ?? 0);
                    $pontuacaoTotal += $cotacao;

                    $resolvida = $this->resolverRespostaSubitem($sub, $questao);

                    $respostas[$ref] = [
                        'resposta' => $resolvida['texto'],
                        'resposta_letra' => $resolvida['letra'],
                        'cotacao' => $cotacao,
                        'tipo' => 'dissertativa',
                        'questao_pai' => (string) ($questao['numero'] ?? ''),
                    ];
                }
                continue;
            }

            $numero = (string) ($questao['numero'] ?? count($respostas) + 1);
            $cotacao = (float) ($questao['cotacao'] ?? $questao['cotacao_total'] ?? 0);
            $pontuacaoTotal += $cotacao;
            $tipo = $questao['tipo'] ?? 'aberta';
            $resolvida = $this->resolverRespostaEsperada($questao);

            $respostas[$numero] = [
                'resposta' => $resolvida['texto'],
                'resposta_letra' => $resolvida['letra'],
                'cotacao' => $cotacao,
                'tipo' => $tipo,
            ];

            if (! empty($questao['criterios'])) {
                $criterios[$numero] = $questao['criterios'];
            } elseif ($tipo === 'objectiva' && ! empty($questao['opcoes'])) {
                $criterios[$numero] = [
                    'tipo' => 'objectiva',
                    'resposta_correcta' => $resolvida['letra'] ?? 'A',
                    'opcoes' => $questao['opcoes'],
                ];
            }
        }

        $prova->chaveCorrecao()?->delete();

        return PedChaveCorrecao::create([
            'prova_id' => $prova->id,
            'respostas_json' => $respostas,
            'criterios_json' => $criterios ?: null,
            'pontuacao_total' => $prova->cotacao_total ?? $pontuacaoTotal,
            'geracao_ia_id' => $prova->geracao_ia_id,
        ]);
    }

    /**
     * @return array{texto:?string, letra:?string}
     */
    public function resolverRespostaSubitem(array $sub, array $questao): array
    {
        $itensAninhados = $sub['itens_subitens'] ?? [];

        if ($itensAninhados !== []) {
            return [
                'texto' => ProvaQuestaoHelper::textoRespostasAninhadas($itensAninhados),
                'letra' => null,
            ];
        }

        return $this->resolverRespostaEsperada(array_merge($questao, [
            'resposta_sugerida' => $sub['resposta_sugerida'] ?? $sub['resposta'] ?? null,
            'cotacao' => $sub['cotacao'] ?? 0,
        ]));
    }

    /**
     * @return array{texto:?string, letra:?string}
     */
    public function resolverRespostaEsperada(array $questao): array
    {
        $tipo = Str::lower((string) ($questao['tipo'] ?? 'aberta'));
        $opcoes = $questao['opcoes'] ?? [];

        if (! empty($questao['resposta_sugerida'])) {
            return [
                'texto' => trim((string) $questao['resposta_sugerida']),
                'letra' => $this->normalizarLetra($questao['resposta_correcta'] ?? null),
            ];
        }

        if (! empty($questao['resposta'])) {
            return [
                'texto' => trim((string) $questao['resposta']),
                'letra' => $this->normalizarLetra($questao['resposta_correcta'] ?? null),
            ];
        }

        if ($tipo === 'objectiva' && is_array($opcoes) && $opcoes !== []) {
            $letra = $this->normalizarLetra($questao['resposta_correcta'] ?? 'A') ?? 'A';
            $textoOpcao = ProvaQuestaoHelper::textoOpcaoPorLetra($opcoes, $letra);

            return [
                'texto' => $textoOpcao ?? trim((string) (array_values($opcoes)[0] ?? '')),
                'letra' => $letra,
            ];
        }

        if (in_array($tipo, ['dissertativa', 'aberta', 'desenvolvimento'], true)) {
            $modelo = trim((string) ($questao['objetivo'] ?? $questao['texto'] ?? ''));

            return [
                'texto' => $modelo !== '' ? $modelo : null,
                'letra' => null,
            ];
        }

        return ['texto' => null, 'letra' => null];
    }

    protected function normalizarLetra(mixed $letra): ?string
    {
        if ($letra === null || $letra === '') {
            return null;
        }

        $letra = Str::upper(Str::ascii(trim((string) $letra)));

        return preg_match('/^[A-D]$/', $letra) ? $letra : null;
    }

}
