<?php

namespace App\Support;

use App\Models\Curso;
use App\Models\Nota;
use App\Services\RegimePauta\RegimePautaInterface;

class BoletimColunas
{
    /**
     * @return array<int, array{key: string, header: string}>
     */
    public static function paraTrimestre(int $trimestre, RegimePautaInterface $regime, int $classeNumero): array
    {
        if ($regime->id() === Curso::REGIME_ADMINISTRACAO) {
            return self::administracao($trimestre, $classeNumero);
        }

        return self::politecnico($trimestre, $classeNumero);
    }

    /** Chave da coluna usada para situação (aprovado/reprovado). */
    public static function chaveResultado(int $trimestre, RegimePautaInterface $regime): string
    {
        if ($trimestre === 3) {
            return 'ca';
        }

        if ($trimestre === 2 && $regime->precisaMFT2()) {
            return 'mft2';
        }

        return 'mt';
    }

    public static function tipoCa(int $classeNumero): string
    {
        return match ($classeNumero) {
            10 => Nota::TIPO_CA10,
            11 => Nota::TIPO_CA11,
            12 => Nota::TIPO_CA12,
            default => Nota::TIPO_CA12,
        };
    }

    public static function rotuloCa(int $classeNumero): string
    {
        return match ($classeNumero) {
            10 => 'CA-10ª',
            11 => 'CA-11ª',
            12 => 'CA-12ª',
            default => 'CA',
        };
    }

    /**
     * @return array<int, array{key: string, header: string}>
     */
    protected static function politecnico(int $trimestre, int $classeNumero): array
    {
        return match ($trimestre) {
            1 => [
                ['key' => 'mac', 'header' => 'MAC1'],
                ['key' => 'pp', 'header' => 'PP1'],
                ['key' => 'pt', 'header' => 'PT1'],
                ['key' => 'mt', 'header' => 'MT1'],
            ],
            2 => [
                ['key' => 'mt1', 'header' => 'MT1'],
                ['key' => 'mac', 'header' => 'MAC2'],
                ['key' => 'pp', 'header' => 'PP2'],
                ['key' => 'pt', 'header' => 'PT2'],
                ['key' => 'mt', 'header' => 'MT2'],
                ['key' => 'mft2', 'header' => 'MFT2'],
            ],
            3 => [
                ['key' => 'mft2', 'header' => 'MFT2'],
                ['key' => 'mac', 'header' => 'MAC3'],
                ['key' => 'pp', 'header' => 'PP3'],
                ['key' => 'mt', 'header' => 'MT3'],
                ['key' => 'cf', 'header' => 'CF'],
                ['key' => 'pg', 'header' => 'PG'],
                ['key' => 'ca', 'header' => self::rotuloCa($classeNumero)],
                ...match ($classeNumero) {
                    10, 11 => [['key' => 'recuperacao', 'header' => 'RECUP.']],
                    12 => [['key' => 'recurso', 'header' => 'RECURSO']],
                    default => [],
                },
                ['key' => 'nota_final', 'header' => 'Final'],
            ],
            default => [],
        };
    }

    /**
     * @return array<int, array{key: string, header: string}>
     */
    protected static function administracao(int $trimestre, int $classeNumero): array
    {
        if ($trimestre === 3) {
            return [
                ['key' => 'cf', 'header' => 'CF'],
                ['key' => 'pg', 'header' => 'PG'],
                ['key' => 'ca', 'header' => 'CA'],
                ...match ($classeNumero) {
                    10, 11 => [['key' => 'recuperacao', 'header' => 'RECUP.']],
                    12 => [['key' => 'recurso', 'header' => 'RECURSO']],
                    default => [],
                },
                ['key' => 'nota_final', 'header' => 'Final'],
            ];
        }

        $sufixo = $trimestre;

        return [
            ['key' => 'mac', 'header' => "MAC{$sufixo}"],
            ['key' => 'pp', 'header' => "PP{$sufixo}"],
            ['key' => 'pt', 'header' => "PT{$sufixo}"],
            ['key' => 'mt', 'header' => "MT{$sufixo}"],
        ];
    }
}
