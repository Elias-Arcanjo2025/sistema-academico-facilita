<?php

namespace App\Support;

/**
 * Arredondamento de médias escolares (0–20).
 *
 * Regra: parte decimal &lt; 0,5 → por defeito (baixa); ≥ 0,5 → por excesso (sobe).
 * Exemplos: 11,4 → 11 | 11,5 → 12 | 9,49 → 9 | 9,5 → 10
 */
class NotaArredondamento
{
    /**
     * Arredonda à unidade (half-up).
     */
    public static function media(float|int|null $valor): ?float
    {
        if ($valor === null) {
            return null;
        }

        return (float) round((float) $valor, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Igual a media(), mas devolve int (para células Excel/UI).
     */
    public static function mediaInt(float|int|null $valor): ?int
    {
        $r = self::media($valor);

        return $r === null ? null : (int) $r;
    }
}
