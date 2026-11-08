<?php

namespace App\Support;

/**
 * Cálculos partilhados da pauta trimestral (CA, etc.).
 */
class PautaCalculos
{
    /**
     * CA = 60% CF + 40% PG. PG em falta conta como 0.
     */
    public static function caFromCfPg(mixed $cf, mixed $pg): ?float
    {
        if ($cf === null || $cf === '') {
            return null;
        }

        // Excel: ROUND(0.6*CF+0.4*PG,0) com CF já arredondado à unidade
        $cfNum = NotaArredondamento::media((float) $cf);
        $pgNum = ($pg === null || $pg === '') ? 0.0 : (float) $pg;

        return NotaArredondamento::media(0.6 * (float) $cfNum + 0.4 * $pgNum);
    }

    /**
     * Valor CA para resultado — só exige CA calculada (não depende de PG).
     */
    public static function caParaResultado(mixed $ca): ?float
    {
        if ($ca === null || $ca === '') {
            return null;
        }

        return (float) $ca;
    }

    /**
     * Resolve CA a partir dos dados da disciplina (CA lançada ou derivada de CF+PG).
     *
     * @param  array<string, mixed>  $dadosDisciplina
     */
    public static function caEfectivaDisciplina(array $dadosDisciplina): ?float
    {
        $ca = self::caParaResultado($dadosDisciplina['ca'] ?? null);
        if ($ca !== null) {
            return $ca;
        }

        return self::caFromCfPg($dadosDisciplina['cf'] ?? null, $dadosDisciplina['pg'] ?? null);
    }

    /**
     * Nota oficial para resultado: CFD (ou CA) após substituição por exames especiais.
     *
     * @param  array<string, mixed>  $dadosDisciplina
     */
    public static function notaResultadoDisciplina(array $dadosDisciplina, bool $usaCfd): ?float
    {
        if ($usaCfd) {
            $cfd = $dadosDisciplina['cfd'] ?? null;
            if ($cfd !== null && $cfd !== '') {
                return self::cfdEfectivaDisciplina($dadosDisciplina);
            }

            $ca = self::caEfectivaDisciplina($dadosDisciplina);
            if ($ca !== null) {
                return self::cfdEfectivaDisciplina($dadosDisciplina, $ca);
            }

            return self::cfdEfectivaDisciplina($dadosDisciplina);
        }

        $ca = self::caEfectivaDisciplina($dadosDisciplina);
        if ($ca !== null) {
            return self::cfdEfectivaDisciplina($dadosDisciplina, $ca);
        }

        return self::cfdEfectivaDisciplina($dadosDisciplina);
    }

    /**
     * CFD efectiva após exames especiais (Art.º 31.º–34.º DE 424/25):
     *  - Extraordinário / Recurso / Recuperação anual → substituem a nota base
     *  - Melhoria → MAX(nota base, melhoria)
     *
     * @param  array<string, mixed>  $dadosDisciplina
     */
    public static function cfdEfectivaDisciplina(array $dadosDisciplina, ?float $notaBase = null): ?float
    {
        $extra = self::valorNumericoOpcional($dadosDisciplina['extraordinario'] ?? null);
        $recurso = self::valorNumericoOpcional($dadosDisciplina['recurso'] ?? null);
        $recuperacao = self::valorNumericoOpcional($dadosDisciplina['recuperacao'] ?? null);
        $melhoria = self::valorNumericoOpcional($dadosDisciplina['melhoria'] ?? null);

        if ($notaBase === null) {
            $raw = $dadosDisciplina['cfd'] ?? null;
            if ($raw !== null && $raw !== '') {
                $notaBase = (float) $raw;
            }
        }

        // Nota seca do exame (RECUP./RECURSO/EXTRA) substitui CA/CFD — mesmo sem base.
        if ($extra !== null) {
            $apos = $extra;
        } elseif ($recurso !== null || $recuperacao !== null) {
            $substituto = $recurso ?? $recuperacao;
            $apos = $notaBase !== null
                ? \App\Services\RecursoService::cfdEfectiva($notaBase, $substituto)
                : $substituto;
        } elseif ($notaBase === null) {
            return null;
        } else {
            $apos = $notaBase;
        }

        return \App\Services\MelhoriaService::cfdEfectiva($apos, $melhoria);
    }

    /**
     * Metadados para histórico na pauta quando um exame especial substitui CA/CFD.
     *
     * @param  array<string, mixed>  $dadosDisciplina
     * @return array{tipo: string, rotulo: string, nota_original: int, nota_exame: int, nota_efectiva: int}|null
     */
    public static function detalheSubstituicaoExame(
        array $dadosDisciplina,
        float $notaOriginal,
        float $notaEfectiva,
    ): ?array {
        $extra = self::valorNumericoOpcional($dadosDisciplina['extraordinario'] ?? null);
        $recurso = self::valorNumericoOpcional($dadosDisciplina['recurso'] ?? null);
        $recuperacao = self::valorNumericoOpcional($dadosDisciplina['recuperacao'] ?? null);
        $melhoria = self::valorNumericoOpcional($dadosDisciplina['melhoria'] ?? null);

        if ($extra !== null) {
            return [
                'tipo' => 'extraordinario',
                'rotulo' => 'Exame extraordinário',
                'nota_original' => (int) round($notaOriginal),
                'nota_exame' => (int) round($extra),
                'nota_efectiva' => (int) round($notaEfectiva),
            ];
        }

        if ($recurso !== null) {
            return [
                'tipo' => 'recurso',
                'rotulo' => 'Exame de recurso',
                'nota_original' => (int) round($notaOriginal),
                'nota_exame' => (int) round($recurso),
                'nota_efectiva' => (int) round($notaEfectiva),
            ];
        }

        if ($recuperacao !== null) {
            return [
                'tipo' => 'recuperacao',
                'rotulo' => 'Exame de recuperação',
                'nota_original' => (int) round($notaOriginal),
                'nota_exame' => (int) round($recuperacao),
                'nota_efectiva' => (int) round($notaEfectiva),
            ];
        }

        if ($melhoria !== null && $melhoria > $notaOriginal) {
            return [
                'tipo' => 'melhoria',
                'rotulo' => 'Melhoria',
                'nota_original' => (int) round($notaOriginal),
                'nota_exame' => (int) round($melhoria),
                'nota_efectiva' => (int) round($notaEfectiva),
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $dadosDisciplina
     */
    public static function disciplinaComNotaFinal(array $dadosDisciplina): bool
    {
        if (($dadosDisciplina['cf'] ?? null) !== null && ($dadosDisciplina['cf'] ?? '') !== '') {
            return true;
        }

        if (($dadosDisciplina['cfd'] ?? null) !== null && ($dadosDisciplina['cfd'] ?? '') !== '') {
            return true;
        }

        return self::caEfectivaDisciplina($dadosDisciplina) !== null;
    }

    private static function valorNumericoOpcional(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (float) $valor;
    }
}
