<?php

namespace App\Support;

class ProvaQuestaoHelper
{
    public static function letraOpcao(int|string $chave, int $indice = 0): string
    {
        if (is_string($chave)) {
            $letra = strtoupper(preg_replace('/[^A-D]/', '', $chave) ?? '');

            if ($letra !== '') {
                return $letra;
            }
        }

        return chr(65 + $indice);
    }

    public static function letraSubitem(mixed $letra, int $indice = 0): string
    {
        $normalizada = strtolower(preg_replace('/[^a-z]/', '', (string) ($letra ?? '')) ?? '');

        return $normalizada !== '' ? $normalizada : chr(97 + $indice);
    }

    /**
     * @param  array<int|string, mixed>  $opcoes
     * @return array<int, array{letra: string, texto: string}>
     */
    public static function opcoesNormalizadas(array $opcoes): array
    {
        $lista = [];
        $indice = 0;

        foreach ($opcoes as $chave => $texto) {
            $lista[] = [
                'letra' => self::letraOpcao($chave, $indice),
                'texto' => is_string($texto) ? $texto : json_encode($texto, JSON_UNESCAPED_UNICODE),
            ];
            $indice++;
        }

        return $lista;
    }

    /**
     * @param  array<int|string, mixed>  $opcoes
     */
    public static function textoOpcaoPorLetra(array $opcoes, string $letra): ?string
    {
        $letra = strtoupper(preg_replace('/[^A-D]/', '', $letra) ?? '');

        if ($letra !== '' && array_key_exists($letra, $opcoes)) {
            return trim((string) $opcoes[$letra]);
        }

        $indice = ord($letra) - ord('A');

        if ($indice >= 0 && isset($opcoes[$indice])) {
            return trim((string) $opcoes[$indice]);
        }

        $valores = array_values($opcoes);

        return isset($valores[$indice]) ? trim((string) $valores[$indice]) : null;
    }

    public static function refSubitem(array $questao, array $sub, int $indice): string
    {
        $numero = (string) ($questao['numero'] ?? '1');
        $letra = self::letraSubitem($sub['letra'] ?? null, $indice);

        return $numero.$letra;
    }

    public static function refItemAninhado(string $refPai, array $item, int $indice): string
    {
        $letra = self::letraSubitem($item['letra'] ?? null, $indice);

        return $refPai.$letra;
    }

    /**
     * @param  array<int, array<string, mixed>>  $itens
     */
    public static function textoRespostasAninhadas(array $itens): ?string
    {
        $linhas = [];

        foreach ($itens as $idx => $item) {
            if (! is_array($item)) {
                continue;
            }

            $letra = self::letraSubitem($item['letra'] ?? null, $idx);
            $textoItem = trim((string) ($item['texto'] ?? ''));
            $resposta = trim((string) ($item['resposta_sugerida'] ?? $item['resposta'] ?? ''));

            if ($resposta !== '') {
                $linhas[] = $textoItem !== ''
                    ? "{$letra}) {$textoItem}: {$resposta}"
                    : "{$letra}) {$resposta}";
            } elseif ($textoItem !== '') {
                $linhas[] = "{$letra}) {$textoItem}";
            }
        }

        return $linhas !== [] ? implode("\n", $linhas) : null;
    }

    public static function formatarRespostaChave(mixed $entrada): string
    {
        if (! is_array($entrada)) {
            return trim((string) $entrada);
        }

        $resposta = trim((string) ($entrada['resposta'] ?? ''));

        if ($resposta !== '') {
            return $resposta;
        }

        $letra = trim((string) ($entrada['resposta_letra'] ?? ''));

        if ($letra !== '') {
            return $letra;
        }

        $tipo = strtolower((string) ($entrada['tipo'] ?? ''));

        if (in_array($tipo, ['dissertativa', 'aberta', 'desenvolvimento'], true)) {
            return 'Resposta dissertativa — avaliar com base nos critérios da questão.';
        }

        return '—';
    }
}
