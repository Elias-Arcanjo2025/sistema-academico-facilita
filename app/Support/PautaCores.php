<?php

namespace App\Support;

/**
 * Paleta oficial da Pauta de Aproveitamento (modelo Excel da escola).
 */
final class PautaCores
{
    /** MT, CF, CA — fundo verde pálido */
    public const VERDE_PALIDO = 'E2EFDA';

    /** Coluna CFD final — fundo verde médio */
    public const VERDE_CFD = '92D050';

    /** Colunas de exame (recuperação / recurso) */
    public const LARANJA_EXAME = 'FCE4D6';

    public const VERMELHO_NOTA = 'C00000';

    public const AZUL_NOTA = '0070C0';

    /** Texto institucional (cabeçalhos) */
    public const NAVY = '002060';

    /** Linhas alternadas de alunos (Excel / web) */
    public const ZEBRA = 'F5F8FC';

    /** Separador visual entre disciplinas */
    public const DISC_BORDER = 'C5D4E8';

    /**
     * Tokens CSS partilhados com a pauta web (espelham as constantes acima).
     *
     * @return array<string, string>
     */
    public static function cssVariables(): array
    {
        return [
            '--pauta-verde-palido' => '#'.self::VERDE_PALIDO,
            '--pauta-verde-cfd' => '#'.self::VERDE_CFD,
            '--pauta-laranja-exame' => '#'.self::LARANJA_EXAME,
            '--pauta-vermelho-nota' => '#'.self::VERMELHO_NOTA,
            '--pauta-azul-nota' => '#'.self::AZUL_NOTA,
            '--pauta-navy' => '#'.self::NAVY,
            '--pauta-zebra' => '#'.self::ZEBRA,
            '--pauta-disc-border' => '#'.self::DISC_BORDER,
        ];
    }

    /** Atributo style="--pauta-…: …" para o contentor da pauta web. */
    public static function cssVariablesStyle(): string
    {
        $parts = [];
        foreach (self::cssVariables() as $name => $value) {
            $parts[] = $name.': '.$value;
        }

        return implode('; ', $parts);
    }

    /**
     * @param  array<string, mixed>|null  $colDef
     */
    public static function fundoHex(?array $colDef): ?string
    {
        if ($colDef === null) {
            return null;
        }

        if (! empty($colDef['fundo_cfd'])) {
            return '#'.self::VERDE_CFD;
        }

        if (! empty($colDef['exame'])) {
            return '#'.self::LARANJA_EXAME;
        }

        if (! empty($colDef['destaque']) || ! empty($colDef['fundo_pale'])) {
            return '#'.self::VERDE_PALIDO;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $colDef
     */
    public static function fundoRgb(?array $colDef): ?string
    {
        $hex = self::fundoHex($colDef);

        return $hex !== null ? ltrim($hex, '#') : null;
    }
}
