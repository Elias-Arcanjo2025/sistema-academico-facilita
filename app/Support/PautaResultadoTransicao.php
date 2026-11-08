<?php

namespace App\Support;

/**
 * Resultado T3 (10.ª–12.ª) — Condições de Transição / Aptidão.
 *
 * 10.ª–11.ª: TRANSITA | TRANSITA/C DEF | RECUPERAÇÃO | N/TRANSITA
 *   - Contínuas negativas → Def. / TRANSITA/C DEF
 *   - Terminais (acabam nesta classe) negativas → Rec. / RECUPERAÇÃO
 * 12.ª: APTO | RECURSO | N/APTO
 *
 * Limiares de RECUPERAÇÃO (10/11) e RECURSO (12) são configuráveis.
 * Nota oficial: contínuas → CA | terminais → CFD
 * A partir da 11.ª: CFD de terminais de anos anteriores também conta.
 */
class PautaResultadoTransicao
{
    public const APROVACAO_MINIMA = 10;

    /** Default DE 424/25: até 2 negativas para ainda transitar com exame. */
    public const MAX_NEGATIVAS_TRANSITA = PautaRegrasTransicaoMinisterio::MAX_NEGATIVAS_TRANSITA;

    /** Três negativas em continuidade → não transita (alínea 4). */
    public const MAX_NEGATIVAS_CONTINUIDADE = PautaRegrasTransicaoMinisterio::MAX_NEGATIVAS_CONTINUIDADE;

    /** Default: até 2 negativas → RECUPERAÇÃO / TRANSITA/C DEF (10.ª/11.ª). */
    public const DEFAULT_MAX_NEGATIVAS_RECUPERACAO = 2;

    /** Default: até 3 negativas → RECURSO; >3 → N/APTO (12.ª). */
    public const DEFAULT_MAX_NEGATIVAS_RECURSO_12 = 3;

    public const RESULTADO_TRANSITA = 'TRANSITA';

    public const RESULTADO_N_TRANSITA = 'N/TRANSITA';

    public const RESULTADO_TRANSITA_COM_DEFICIENCIA = 'TRANSITA/C DEF';

    public const RESULTADO_RECUPERACAO = 'RECUPERAÇÃO';

    public const RESULTADO_APTO = 'APTO';

    public const RESULTADO_N_APTO = 'N/APTO';

    public const RESULTADO_RECURSO = 'RECURSO';

    public const CONFIG_MAX_NEGATIVAS_RECUPERACAO = 'resultado_max_negativas_recuperacao';

    public const CONFIG_MAX_NEGATIVAS_RECURSO_12 = 'resultado_12_max_negativas_recurso';

    /**
     * Limite de negativas para RECUPERAÇÃO / TRANSITA/C DEF na 10.ª/11.ª (1…N; >N = N/TRANSITA).
     */
    public static function maxNegativasRecuperacao(?int $override = null): int
    {
        if ($override !== null) {
            return max(0, $override);
        }

        try {
            $valor = \App\Models\Configuracao::get(
                self::CONFIG_MAX_NEGATIVAS_RECUPERACAO,
                self::DEFAULT_MAX_NEGATIVAS_RECUPERACAO,
            );
        } catch (\Throwable) {
            $valor = self::DEFAULT_MAX_NEGATIVAS_RECUPERACAO;
        }

        return max(0, (int) $valor);
    }

    /**
     * Limite de negativas para RECURSO na 12.ª (1…N = RECURSO; >N = N/APTO).
     */
    public static function maxNegativasRecurso12(?int $override = null): int
    {
        if ($override !== null) {
            return max(0, $override);
        }

        try {
            $valor = \App\Models\Configuracao::get(self::CONFIG_MAX_NEGATIVAS_RECURSO_12, self::DEFAULT_MAX_NEGATIVAS_RECURSO_12);
        } catch (\Throwable) {
            $valor = self::DEFAULT_MAX_NEGATIVAS_RECURSO_12;
        }

        return max(0, (int) $valor);
    }

    /**
     * Nota oficial para efeitos de transição: CA (contínua) ou CFD (terminal).
     *
     * @param  array<string, mixed>  $dadosDisciplina
     * @param  array{classe_fim?:int, terminal?:bool, continua?:bool, usa_cfd?:bool}  $info
     */
    public static function notaOficialDisciplina(array $dadosDisciplina, array $info, int $classeNumero): ?float
    {
        $classeFim = (int) ($info['classe_fim'] ?? 0);
        $terminal = ($info['terminal'] ?? false) || ($classeFim > 0 && $classeFim === $classeNumero);
        $temCfd = (bool) ($dadosDisciplina['tem_coluna_cfd'] ?? $info['usa_cfd'] ?? false);

        if ($terminal && $temCfd) {
            $cfd = $dadosDisciplina['cfd'] ?? null;
            if ($cfd !== null && $cfd !== '') {
                return PautaCalculos::cfdEfectivaDisciplina($dadosDisciplina);
            }

            $ca = PautaCalculos::caParaResultado($dadosDisciplina['ca'] ?? null);
            if ($ca !== null) {
                return PautaCalculos::cfdEfectivaDisciplina($dadosDisciplina, $ca);
            }

            // Só exame especial lançado (sem CA/CFD ainda)
            return PautaCalculos::cfdEfectivaDisciplina($dadosDisciplina);
        }

        $ca = PautaCalculos::caParaResultado($dadosDisciplina['ca'] ?? null);
        if ($ca !== null) {
            return PautaCalculos::cfdEfectivaDisciplina($dadosDisciplina, $ca);
        }

        return PautaCalculos::cfdEfectivaDisciplina($dadosDisciplina);
    }

    public static function disciplinaTerminal(array $info, int $classeNumero): bool
    {
        $classeFim = (int) ($info['classe_fim'] ?? 0);

        return ($info['terminal'] ?? false) || ($classeFim > 0 && $classeFim === $classeNumero);
    }

    public static function disciplinaContinua(array $info, int $classeNumero): bool
    {
        $classeFim = (int) ($info['classe_fim'] ?? 0);

        return ($info['continua'] ?? false) || ($classeFim > $classeNumero);
    }

    /**
     * 10.ª/11.ª: TRANSITA | TRANSITA/C DEF | RECUPERAÇÃO | N/TRANSITA.
     *
     * @param  array<int, array{disciplina_nome:string}>  $negativasTerminais
     * @param  array<int, array{disciplina_nome:string}>  $negativasContinuas
     * @return array{resultado:string, situacao:string, observacao:string, reprova_directamente:bool}
     */
    public static function resolverResultado(
        int $totalNegativas,
        array $negativasTerminais,
        array $negativasContinuas,
        ?int $maxNegativasRecuperacao = null,
    ): array {
        $countCont = count($negativasContinuas);
        $countTerm = count($negativasTerminais);
        $max = self::maxNegativasRecuperacao($maxNegativasRecuperacao);

        if ($countCont >= self::MAX_NEGATIVAS_CONTINUIDADE) {
            return self::pacote(self::RESULTADO_N_TRANSITA, 'reprova', '', true);
        }

        if ($totalNegativas > $max) {
            return self::pacote(self::RESULTADO_N_TRANSITA, 'reprova', '', true);
        }

        if ($totalNegativas === 0) {
            return self::pacote(self::RESULTADO_TRANSITA, 'transita', '');
        }

        // Terminais que acabam nesta classe (ou carry-over) → exame de recuperação
        if ($countTerm >= 1) {
            return self::pacote(
                self::RESULTADO_RECUPERACAO,
                'recuperacao',
                self::observacaoMista($negativasContinuas, $negativasTerminais),
            );
        }

        return self::pacote(
            self::RESULTADO_TRANSITA_COM_DEFICIENCIA,
            'transita_deficiencia',
            self::observacaoDeficiencia($negativasContinuas),
        );
    }

    /**
     * Resultado da 12.ª classe: APTO | RECURSO | N/APTO.
     *
     * @param  array<int, array{disciplina_nome:string}>  $negativas  Todas as disciplinas negativas
     * @return array{resultado:string, situacao:string, observacao:string, reprova_directamente:bool}
     */
    public static function resolverResultado12(
        int $totalNegativas,
        array $negativas,
        ?int $maxNegativasRecurso = null,
    ): array {
        $max = self::maxNegativasRecurso12($maxNegativasRecurso);

        if ($totalNegativas === 0) {
            return self::pacote(self::RESULTADO_APTO, 'apto', '');
        }

        if ($totalNegativas <= $max) {
            return self::pacote(
                self::RESULTADO_RECURSO,
                'recurso',
                self::observacaoRecuperacao($negativas),
            );
        }

        return self::pacote(
            self::RESULTADO_N_APTO,
            'reprova',
            self::observacaoNegativas($negativas),
            true,
        );
    }

    /**
     * @return array{resultado:string, situacao:string, observacao:string, reprova_directamente:bool}
     */
    private static function pacote(
        string $resultado,
        string $situacao,
        string $observacao,
        bool $reprovaDirectamente = false,
    ): array {
        return [
            'resultado' => $resultado,
            'situacao' => $situacao,
            'observacao' => $observacao,
            'reprova_directamente' => $reprovaDirectamente,
        ];
    }

    public static function reprovaDirectamente(
        int $totalNegativas,
        int $negativasContinuas = 0,
        ?int $maxNegativasRecuperacao = null,
    ): bool {
        return $negativasContinuas >= self::MAX_NEGATIVAS_CONTINUIDADE
            || $totalNegativas > self::maxNegativasRecuperacao($maxNegativasRecuperacao);
    }

    public static function elegivelRecuperacao(
        int $totalNegativas,
        int $negativasTerminais = 0,
        int $negativasContinuas = 0,
        ?int $maxNegativasRecuperacao = null,
    ): bool {
        return $negativasContinuas < self::MAX_NEGATIVAS_CONTINUIDADE
            && $negativasTerminais >= 1
            && $totalNegativas >= 1
            && $totalNegativas <= self::maxNegativasRecuperacao($maxNegativasRecuperacao);
    }

    public static function ehTransitaComDeficiencia(string $resultado): bool
    {
        return str_starts_with($resultado, self::RESULTADO_TRANSITA_COM_DEFICIENCIA)
            || str_starts_with($resultado, 'TRANS. C/DEF.');
    }

    public static function ehApto(string $resultado): bool
    {
        return $resultado === self::RESULTADO_APTO
            || $resultado === self::RESULTADO_TRANSITA
            || $resultado === 'APROVADO';
    }

    public static function ehRecurso12(string $resultado): bool
    {
        return $resultado === self::RESULTADO_RECURSO;
    }

    public static function ehRecuperacao(string $resultado): bool
    {
        return $resultado === self::RESULTADO_RECUPERACAO
            || str_starts_with($resultado, 'RECUP.');
    }

    public static function ehNApto(string $resultado): bool
    {
        return $resultado === self::RESULTADO_N_APTO
            || $resultado === self::RESULTADO_N_TRANSITA
            || str_starts_with($resultado, 'NÃO APTO')
            || str_starts_with($resultado, 'NAO APTO');
    }

    /**
     * @param  array<int, array{disciplina_nome:string}>  $negativas
     */
    public static function observacaoDeficiencia(array $negativas): string
    {
        if ($negativas === []) {
            return '';
        }

        $nomes = array_map(fn ($t) => $t['disciplina_nome'], $negativas);

        return 'Def.: '.implode(', ', $nomes);
    }

    /**
     * @param  array<int, array{disciplina_nome:string}>  $negativas
     */
    public static function observacaoRecuperacao(array $negativas): string
    {
        if ($negativas === []) {
            return '';
        }

        $nomes = array_map(fn ($t) => $t['disciplina_nome'], $negativas);

        return 'Rec.: '.implode(', ', $nomes);
    }

    /**
     * Lista todas as disciplinas negativas (N/APTO na 12.ª).
     *
     * @param  array<int, array{disciplina_nome:string}>  $negativas
     */
    public static function observacaoNegativas(array $negativas): string
    {
        if ($negativas === []) {
            return '';
        }

        $nomes = array_map(fn ($t) => $t['disciplina_nome'], $negativas);

        return implode(', ', $nomes);
    }

    /**
     * @param  array<int, array{disciplina_nome:string}>  $continuas
     * @param  array<int, array{disciplina_nome:string}>  $terminais
     */
    public static function observacaoMista(array $continuas, array $terminais): string
    {
        $partes = [];
        $def = self::observacaoDeficiencia($continuas);
        $rec = self::observacaoRecuperacao($terminais);
        if ($def !== '') {
            $partes[] = $def;
        }
        if ($rec !== '') {
            $partes[] = $rec;
        }

        return implode(' | ', $partes);
    }
}
