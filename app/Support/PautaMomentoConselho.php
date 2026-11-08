<?php

namespace App\Support;

/**
 * Momento da pauta em relação ao Conselho de Classe.
 */
class PautaMomentoConselho
{
    public const POS = 'pos_conselho';

    public const PRE = 'pre_conselho';

    public static function normalizar(?string $momento): string
    {
        return $momento === self::PRE ? self::PRE : self::POS;
    }

    public static function isPre(string $momento): bool
    {
        return self::normalizar($momento) === self::PRE;
    }

    public static function isPos(string $momento): bool
    {
        return ! self::isPre($momento);
    }

    public static function rotulo(string $momento): string
    {
        return self::isPre($momento) ? 'Pré-conselho' : 'Pós-conselho';
    }

    public static function rotuloCurto(string $momento): string
    {
        return self::isPre($momento) ? 'PRÉ-CONSELHO' : 'PÓS-CONSELHO';
    }

    public static function descricao(string $momento): string
    {
        return self::isPre($momento)
            ? 'Notas lançadas pelos professores antes do Conselho de Classe.'
            : 'Notas finais após o Conselho (ajustes favoráveis registados). Documento oficial.';
    }

    /** @return array<string, string> */
    public static function opcoes(): array
    {
        return [
            self::PRE => self::rotulo(self::PRE),
            self::POS => self::rotulo(self::POS),
        ];
    }
}
