<?php

namespace App\Support;

/**
 * Condições de Transição de Classe — DE 424/25 (referência central).
 */
class PautaRegrasTransicaoMinisterio
{
    /** CA mínima no ano terminal para calcular CFD por média (regra dos 7). */
    public const CA_MINIMA_MEDIA_CFD = 7;

    public const APROVACAO_MINIMA = 10;

    public const MAX_NEGATIVAS_TRANSITA = 2;

    public const MAX_NEGATIVAS_CONTINUIDADE = 3;

    /** Limite de exames em disciplinas terminais (11.ª e 12.ª). */
    public const MAX_EXAMES_TERMINAIS = 5;

    public static function podeCalcularCfdPorMedia(?float $caAnoTerminal): bool
    {
        return $caAnoTerminal !== null && $caAnoTerminal >= self::CA_MINIMA_MEDIA_CFD;
    }

    /**
     * CFD por média das CAs do intervalo — só se CA do ano terminal ≥ 7.
     * Exige todas as CAs anteriores (não reduz o denominador se faltar alguma).
     *
     * Exemplos: 10–12 → (CA10+CA11+CA12)/3 | 11–12 → (CA11+CA12)/2 | 10–11 → (CA10+CA11)/2
     *
     * @param  float|null  ...$casAnteriores  CAs de anos anteriores (obrigatórias)
     */
    public static function calcularCfdPorMedia(?float $caTerminal, ?float ...$casAnteriores): ?float
    {
        if ($caTerminal === null || ! self::podeCalcularCfdPorMedia($caTerminal)) {
            return null;
        }

        foreach ($casAnteriores as $ca) {
            if ($ca === null) {
                return null;
            }
        }

        $valores = array_merge($casAnteriores, [$caTerminal]);

        return round(array_sum($valores) / count($valores));
    }

    /**
     * Limita disciplinas terminais elegíveis a exame (máx. 5 na 11.ª/12.ª).
     *
     * @param  array<int, array<string, mixed>>  $terminais
     * @return array<int, array<string, mixed>>
     */
    public static function limitarExamesTerminais(array $terminais, int $classeNumero): array
    {
        if (! in_array($classeNumero, [11, 12], true)) {
            return $terminais;
        }

        return array_slice($terminais, 0, self::MAX_EXAMES_TERMINAIS);
    }

    public static function incluirCfdTerminaisAnteriores(int $classeNumero): bool
    {
        return $classeNumero >= 11;
    }

    /**
     * 12.ª → 13.ª: PAP exige aprovação em todas as disciplinas do currículo.
     */
    public static function papCondicaoReunida(int $totalNegativas): bool
    {
        return $totalNegativas === 0;
    }
}
