<?php

namespace App\Services\Pedagogico;

use App\Models\Pedagogico\PedMatrizProva;
use App\Models\Pedagogico\PedProva;
use App\Support\ProvaQuestaoHelper;

class MatrizProvaService
{
    public function construir(PedProva $prova): PedMatrizProva
    {
        $conteudo = $prova->conteudo_json ?? [];
        $questoes = $conteudo['questoes'] ?? [];
        $linhas = [];

        foreach ($questoes as $questao) {
            if (! is_array($questao)) {
                continue;
            }

            $competencia = $questao['competencia'] ?? $questao['titulo_grupo'] ?? null;
            $subitens = $questao['subitens'] ?? [];

            if ($subitens !== []) {
                foreach ($subitens as $idx => $sub) {
                    if (! is_array($sub)) {
                        continue;
                    }

                    $ref = ProvaQuestaoHelper::refSubitem($questao, $sub, $idx);
                    $itensAninhados = $sub['itens_subitens'] ?? [];

                    if ($itensAninhados !== []) {
                        foreach ($itensAninhados as $nIdx => $item) {
                            if (! is_array($item)) {
                                continue;
                            }

                            $linhas[] = $this->linha(
                                ProvaQuestaoHelper::refItemAninhado($ref, $item, $nIdx),
                                (string) ($item['texto'] ?? ''),
                                $competencia,
                                (float) ($item['cotacao'] ?? 0),
                            );
                        }

                        continue;
                    }

                    $linhas[] = $this->linha(
                        $ref,
                        (string) ($sub['texto'] ?? ''),
                        $competencia,
                        (float) ($sub['cotacao'] ?? 0),
                    );
                }

                continue;
            }

            $numero = (string) ($questao['numero'] ?? count($linhas) + 1);
            $linhas[] = $this->linha(
                $numero,
                (string) ($questao['texto'] ?? $questao['enunciado'] ?? ''),
                $competencia,
                (float) ($questao['cotacao'] ?? $questao['cotacao_total'] ?? 0),
            );
        }

        $prova->matriz()?->delete();

        return PedMatrizProva::create([
            'prova_id' => $prova->id,
            'linhas_json' => $linhas,
            'geracao_ia_id' => $prova->geracao_ia_id,
        ]);
    }

    /**
     * @return array{questao_ref: string, conteudo: string, competencia: ?string, cotacao: float, numero: string, texto: string}
     */
    protected function linha(string $ref, string $conteudo, ?string $competencia, float $cotacao): array
    {
        $conteudo = trim($conteudo);

        return [
            'questao_ref' => $ref,
            'ref' => $ref,
            'conteudo' => $conteudo,
            'texto' => $conteudo,
            'numero' => $ref,
            'competencia' => $competencia,
            'tema' => $competencia,
            'cotacao' => $cotacao,
        ];
    }
}
