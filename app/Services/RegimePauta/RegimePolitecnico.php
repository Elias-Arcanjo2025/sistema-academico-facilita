<?php

namespace App\Services\RegimePauta;

use App\Models\Curso;
use App\Models\Nota;
use App\Support\PautaRegrasTransicaoMinisterio;

/**
 * Regime POLITÉCNICO — comportamento histórico do sistema.
 *
 * Características:
 *  - T2 calcula MFT2 = (MT1 + MT2) / 2 (acumulado).
 *  - T3: esquema de colunas VARIÁVEL por disciplina (classe_inicio / classe_fim).
 *  - 11.ª/12.ª: carrega CA-10ª / CA-11ª como colunas separadas quando aplicável.
 *  - T3: MAC3 + PP3 (sem PT3); MT3 = (MAC3 + PP3) / 2; PG substitui a prova trimestral.
 *  - CF (10.ª): (MFT2 + MT3) / 2.
 *  - CA: 0.6×CF + 0.4×PG.
 *  - Disciplina terminal na classe: CA-Nª + CFD; não terminal: só CA-Nª.
 *  - Resultado da 12.ª baseado em CFD (com substituições por exames especiais).
 *  - Resultado da 10.ª/11.ª baseado em CA.
 */
class RegimePolitecnico implements RegimePautaInterface
{
    public function id(): string
    {
        return Curso::REGIME_POLITECNICO;
    }

    public function nome(): string
    {
        return 'Politécnico (Informática, Técnico)';
    }

    public function precisaMFT2(): bool
    {
        return true;
    }

    public function carregaCAsAnteriores(): bool
    {
        return true;
    }

    public function temColunaMediaAluno(int $trimestre): bool
    {
        return false;
    }

    public function colunasDisciplina(int $trimestre, int $classeNumero, array $flags = []): array
    {
        if ($trimestre === 1) {
            return $this->colunasT1($classeNumero, $flags);
        }

        if ($trimestre === 2) {
            $cols = [
                ['key' => 'mt1', 'header' => 'MT1', 'tipo' => 'nota'],
                ['key' => 'mac', 'header' => 'MAC2', 'tipo' => 'nota'],
                ['key' => 'pp', 'header' => 'PP2', 'tipo' => 'nota'],
                ['key' => 'pt', 'header' => 'PT2', 'tipo' => 'nota'],
                ['key' => 'mt', 'header' => 'MT2', 'tipo' => 'formula'],
                ['key' => 'mft2', 'header' => 'MFT2', 'tipo' => 'formula', 'destaque' => true],
            ];

            return $this->anexarRecuperacaoSeTransicao($cols, $classeNumero);
        }

        return $this->colunasT3($classeNumero, $flags);
    }

    /**
     * T1: 10.ª/11.ª usam J|I separados; 12.ª usa coluna única FALTAS (modelo oficial).
     * T.R.E.I na 12.ª: MTP1 em vez de MAC1.
     *
     * @param array{faltas_fundidas?:bool, usa_mtp?:bool} $flags
     */
    private function colunasT1(int $classeNumero, array $flags): array
    {
        $usaMtp = (bool) ($flags['usa_mtp'] ?? false);
        $faltasFundidas = (bool) ($flags['faltas_fundidas'] ?? ($classeNumero === 12));

        $cols = [];

        if ($faltasFundidas) {
            $cols[] = ['key' => 'faltas', 'header' => 'FALTAS', 'group' => 'FALTAS', 'tipo' => 'int', 'merged' => 1];
        } else {
            $cols[] = ['key' => 'faltas_j', 'header' => 'J', 'group' => 'FALTAS', 'tipo' => 'int'];
            $cols[] = ['key' => 'faltas_i', 'header' => 'I', 'group' => 'FALTAS', 'tipo' => 'int'];
        }

        $cols[] = ['key' => 'mac', 'header' => $usaMtp ? 'MTP1' : 'MAC1', 'tipo' => 'nota'];
        $cols[] = ['key' => 'pp', 'header' => 'PP1', 'tipo' => 'nota'];
        $cols[] = ['key' => 'pt', 'header' => 'PT1', 'tipo' => 'nota'];
        $cols[] = ['key' => 'mt', 'header' => 'MT1', 'tipo' => 'formula', 'destaque' => true];

        return $this->anexarRecuperacaoSeTransicao($cols, $classeNumero);
    }

    /**
     * Colunas de exame especial (recuperação / recurso) no T3 — histórico visível na pauta.
     *
     * @param  array<int, array<string, mixed>>  $cols
     * @return array<int, array<string, mixed>>
     */
    private function anexarColunasExameEspecial(array $cols, int $classeNumero, array $flags): array
    {
        // RECUP. só nas disciplinas com nota de recuperação já lançada.
        $comRecuperacao = ($flags['recuperacao'] ?? false)
            && ($flags['recuperacao_disciplina'] ?? false);
        $comRecurso = ($flags['recurso'] ?? false)
            && ($flags['recurso_disciplina'] ?? false);

        if (in_array($classeNumero, [10, 11], true) && $comRecuperacao) {
            $cols[] = ['key' => 'recuperacao', 'header' => 'RECUP.', 'tipo' => 'nota', 'exame' => true];
        }

        if ($classeNumero === 12 && $comRecurso) {
            $cols[] = ['key' => 'recurso', 'header' => 'RECURSO', 'tipo' => 'nota', 'exame' => true];
        }

        return $cols;
    }

    /**
     * A coluna REC trimestral não é apresentada na pauta T1/T2; recuperação anual
     * aparece no T3 via {@see anexarColunasExameEspecial}.
     *
     * @param  array<int, array<string, mixed>>  $cols
     * @return array<int, array<string, mixed>>
     */
    private function anexarRecuperacaoSeTransicao(array $cols, int $classeNumero): array
    {
        return $cols;
    }

    /**
     * T3: layout variável conforme histórico da disciplina no curso.
     *
     * @param array{
     *   classe_inicio?:int,
     *   classe_fim?:int,
     *   classe_max?:int,
     *   terminal_classe_atual?:bool,
     *   projeto_tecnologico?:bool
     * } $flags
     */
    private function colunasT3(int $classeNumero, array $flags): array
    {
        $inicio = (int) ($flags['classe_inicio'] ?? $classeNumero);
        $fim = (int) ($flags['classe_fim'] ?? $classeNumero);
        $classeMax = (int) ($flags['classe_max'] ?? 12);
        $ehProjetoTec = (bool) ($flags['projeto_tecnologico'] ?? false);

        $cols = [];

        if ($classeNumero >= 11 && $inicio <= 10) {
            $cols[] = ['key' => 'ca10', 'header' => 'CA-10ª', 'tipo' => 'nota'];
        }
        if ($classeNumero >= 12 && $inicio <= 11) {
            $cols[] = ['key' => 'ca11', 'header' => 'CA-11ª', 'tipo' => 'nota'];
        }

        $cols = array_merge($cols, [
            ['key' => 'mft2', 'header' => 'MFT2', 'tipo' => 'nota'],
            ['key' => 'mac', 'header' => 'MAC3', 'tipo' => 'nota'],
            ['key' => 'pp', 'header' => 'PP3', 'tipo' => 'nota'],
            ['key' => 'mt', 'header' => 'MT3', 'tipo' => 'formula', 'fundo_pale' => true],
            ['key' => 'cf', 'header' => 'CF', 'tipo' => 'formula', 'fundo_pale' => true],
            ['key' => 'pg', 'header' => 'PG', 'tipo' => 'nota'],
        ]);

        $caHeader = match ($classeNumero) {
            10 => 'CA-10ª',
            11 => 'CA-11ª',
            12 => 'CA-12ª',
            default => 'CA',
        };

        $terminaNestaClasse = $fim === $classeNumero;

        // Disciplina exclusiva da última classe do curso (ex.: O.G.I, Proj. Tecn., Ing. Técnico na 12.ª)
        $soNaClasseFinal = $classeNumero === $classeMax
            && $inicio === $classeNumero
            && $fim === $classeNumero;

        if ($soNaClasseFinal) {
            if ($ehProjetoTec) {
                $cols[] = ['key' => 'ca', 'header' => $caHeader, 'tipo' => 'formula', 'destaque' => true];
            } else {
                $cols[] = ['key' => 'ca', 'header' => $caHeader, 'tipo' => 'formula', 'fundo_pale' => true];
                $cols[] = ['key' => 'cfd', 'header' => 'CFD', 'tipo' => 'formula', 'destaque' => true, 'fundo_cfd' => true];
            }

            return $this->anexarColunasExameEspecial($cols, $classeNumero, $flags);
        }

        // Disciplina terminal nesta classe (ex.: TIC 10.ª–10.ª, LP 10.ª–11.ª, DT 11.ª–11.ª, MAT 10.ª–12.ª na 12.ª)
        if ($terminaNestaClasse) {
            $cols[] = [
                'key' => 'ca',
                'header' => $caHeader,
                'tipo' => 'formula',
                'destaque' => $classeNumero < 12,
                'fundo_pale' => true,
            ];

            if (! $ehProjetoTec) {
                $cols[] = [
                    'key' => 'cfd',
                    'header' => 'CFD',
                    'tipo' => 'formula',
                    'destaque' => $classeNumero >= 12,
                    'fundo_cfd' => true,
                ];
            }

            return $this->anexarColunasExameEspecial($cols, $classeNumero, $flags);
        }

        // Disciplina continua — só CA do ano (resultado baseia-se na CA)
        $cols[] = [
            'key' => 'ca',
            'header' => $caHeader,
            'tipo' => 'formula',
            'destaque' => true,
            'fundo_pale' => true,
        ];

        return $this->anexarColunasExameEspecial($cols, $classeNumero, $flags);
    }

    public function tiposNotaUsados(int $trimestre, int $classeNumero): array
    {
        $tipos = match ($trimestre) {
            1 => [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT, Nota::TIPO_MT],
            2 => [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT, Nota::TIPO_MT, Nota::TIPO_MFT2],
            3 => [
                Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_MT, Nota::TIPO_MFT2,
                Nota::TIPO_CF, Nota::TIPO_PG,
                Nota::TIPO_CA10, Nota::TIPO_CA11, Nota::TIPO_CA12, Nota::TIPO_CFD,
                Nota::TIPO_RECURSO, Nota::TIPO_MELHORIA, Nota::TIPO_EXTRAORDINARIO,
            ],
            default => [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT, Nota::TIPO_MT],
        };

        if ($classeNumero <= 11) {
            $tipos[] = Nota::TIPO_RECUPERACAO;
        }

        return array_values(array_unique($tipos));
    }

    public function calcularCF(?float $mt1, ?float $mt2, ?float $mt3, ?float $mft2): ?float
    {
        if ($mft2 === null || $mt3 === null) {
            return null;
        }

        // Igual ao Excel: ROUND((MFT2+MT3)/2,0) — depois CA usa este CF já arredondado
        return \App\Support\NotaArredondamento::media(($mft2 + $mt3) / 2);
    }

    public function componentesMediaTrimestral(int $trimestre): array
    {
        if ($trimestre === 3) {
            return [Nota::TIPO_MAC, Nota::TIPO_PP];
        }

        return [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT];
    }

    public function divisorMediaTrimestral(int $trimestre): int
    {
        return $trimestre === 3 ? 2 : 3;
    }

    public function usaProvaTrimestral(int $trimestre): bool
    {
        return $trimestre !== 3;
    }

    public function valorParaResultado(array $base, int $trimestre, int $classeNumero): ?float
    {
        if ($trimestre !== 3) {
            $mt = isset($base['mt']) ? (float) $base['mt'] : null;
            $rec = isset($base['recuperacao']) ? (float) $base['recuperacao'] : null;

            return \App\Services\RecuperacaoService::mtEfectiva($mt, $rec);
        }

        if ($trimestre === 3) {
            if (! empty($base['tem_coluna_cfd'])) {
                $cfd = $base['cfd'] ?? null;
                if ($cfd !== null && $cfd !== '') {
                    return \App\Support\PautaCalculos::cfdEfectivaDisciplina($base);
                }

                // CFD vazia (ex. CA < 7): ainda assim usar RECUP./RECURSO ou CA se existirem
                return \App\Support\PautaCalculos::notaResultadoDisciplina($base, true);
            }

            return \App\Support\PautaCalculos::notaResultadoDisciplina($base, false);
        }

        return null;
    }

    public function chaveColunaResultado(int $trimestre, int $classeNumero, array $colsDisc): string
    {
        if ($trimestre !== 3) {
            return 'mt';
        }

        $keys = array_column($colsDisc, 'key');

        if (in_array('cfd', $keys, true)) {
            return 'cfd';
        }

        return 'ca';
    }

    /**
     * CA disponível para resultado (PG em falta não impede — CA já inclui PG=0).
     *
     * @param  array<string, mixed>  $base
     */
    private function valorCaOficialParaResultado(array $base): ?float
    {
        return \App\Support\PautaCalculos::caParaResultado($base['ca'] ?? null);
    }

    public function expressoesExcel(callable $get, int $classeNumero): array
    {
        $macCel = $get('mac');
        $ppCel = $get('pp');
        $ptCel = $get('pt');
        $mt1Cel = $get('mt1');
        $mtCel = $get('mt');
        $mft2Cel = $get('mft2');
        $cfCel = $get('cf');
        $pgCel = $get('pg');

        $mac = $this->celulaNumericaExcel($get, 'mac');
        $pp = $this->celulaNumericaExcel($get, 'pp');
        $pt = $this->celulaNumericaExcel($get, 'pt');
        $mt1 = $this->celulaNumericaExcel($get, 'mt1');
        $mt = $this->celulaNumericaExcel($get, 'mt');
        $mft2 = $this->celulaNumericaExcel($get, 'mft2');
        $cf = $this->celulaNumericaExcel($get, 'cf');
        $pg = $this->celulaNumericaExcel($get, 'pg');
        $ca = $this->celulaNumericaExcel($get, 'ca');
        $ca10 = $this->celulaNumericaExcel($get, 'ca10');
        $ca11 = $this->celulaNumericaExcel($get, 'ca11');
        $minCfd = PautaRegrasTransicaoMinisterio::CA_MINIMA_MEDIA_CFD;

        // CFD por intervalo da disciplina no curso (todos os cursos):
        // 10.ª–12.ª → (CA10+CA11+CA12)/3 | 11.ª–12.ª → (CA11+CA12)/2 | solo → CA
        // 10.ª–11.ª → (CA10+CA11)/2 | Exige todas as CAs do intervalo; CA terminal < 7 → vazio.
        // Médias: célula vazia se faltarem componentes (não usar 0 — evita falsas negativas no RESULTADO).
        return [
            'mt_t1' => $macCel === '' || $ppCel === '' || $ptCel === ''
                ? '=""'
                : "=IF(OR(NOT(ISNUMBER({$macCel})),NOT(ISNUMBER({$ppCel})),NOT(ISNUMBER({$ptCel}))),\"\",ROUND(({$mac}+{$pp}+{$pt})/3,0))",
            'mt_t2' => $macCel === '' || $ppCel === '' || $ptCel === ''
                ? '=""'
                : "=IF(OR(NOT(ISNUMBER({$macCel})),NOT(ISNUMBER({$ppCel})),NOT(ISNUMBER({$ptCel}))),\"\",ROUND(({$mac}+{$pp}+{$pt})/3,0))",
            'mt_t3' => $macCel === '' || $ppCel === ''
                ? '=""'
                : "=IF(OR(NOT(ISNUMBER({$macCel})),NOT(ISNUMBER({$ppCel}))),\"\",ROUND(({$mac}+{$pp})/2,0))",
            'mft2' => $mt1Cel === '' || $mtCel === ''
                ? '=""'
                : "=IF(OR(NOT(ISNUMBER({$mt1Cel})),NOT(ISNUMBER({$mtCel}))),\"\",ROUND(({$mt1}+{$mt})/2,0))",
            'cf' => $mft2Cel === '' || $mtCel === ''
                ? '=""'
                : "=IF(OR(NOT(ISNUMBER({$mft2Cel})),NOT(ISNUMBER({$mtCel}))),\"\",ROUND(({$mft2}+{$mt})/2,0))",
            'ca' => $cfCel === ''
                ? '=""'
                : "=IF(NOT(ISNUMBER({$cfCel})),\"\",ROUND(0.6*{$cf}+0.4*{$pg},0))",
            'cfd_solo' => $cfCel === ''
                ? '=""'
                : "=IF(NOT(ISNUMBER({$cfCel})),\"\",ROUND(0.6*{$cf}+0.4*{$pg},0))",
            'cfd_10' => '='.$get('ca'),
            'cfd_11' => "=IF(OR({$get('ca10')}=\"\",{$get('ca')}=\"\"),\"\",IF({$ca}<{$minCfd},\"\",ROUND(({$ca10}+{$ca})/2,0)))",
            'cfd_12_de_11' => "=IF(OR({$get('ca11')}=\"\",{$get('ca')}=\"\"),\"\",IF({$ca}<{$minCfd},\"\",ROUND(({$ca11}+{$ca})/2,0)))",
            'cfd_12' => "=IF(OR({$get('ca10')}=\"\",{$get('ca11')}=\"\",{$get('ca')}=\"\"),\"\",IF({$ca}<{$minCfd},\"\",ROUND(({$ca10}+{$ca11}+{$ca})/3,0)))",
        ];
    }

    /**
     * Célula numérica no Excel: vazio ou texto → 0 (cálculo continua).
     */
    private function celulaNumericaExcel(callable $get, string $key): string
    {
        $cel = $get($key);
        if ($cel === '') {
            return '0';
        }

        return "IF(ISNUMBER({$cel}),{$cel},0)";
    }

    public function calcularCfdPreview(array $base, int $classeNumero, array $flags = []): ?float
    {
        $ca = $this->valorNumericoPauta($base['ca'] ?? null);
        $ca10 = $this->valorNumericoPauta($base['ca10'] ?? null);
        $ca11 = $this->valorNumericoPauta($base['ca11'] ?? null);
        $cf = $this->valorNumericoPauta($base['cf'] ?? null);

        $inicio = (int) ($flags['classe_inicio'] ?? $classeNumero);
        $fim = (int) ($flags['classe_fim'] ?? $classeNumero);
        $classeMax = (int) ($flags['classe_max'] ?? 12);

        // Solo-classe (início = fim): CFD = CA do ano.
        if ($ca !== null && \App\Services\PautaService::cfdIgualCaDoAno($inicio, $fim, $classeNumero, $classeMax)) {
            return round($ca);
        }

        if ($ca === null) {
            if ($cf !== null) {
                $pg = $this->valorNumericoPauta($base['pg'] ?? null) ?? 0.0;

                return round(0.6 * $cf + 0.4 * $pg);
            }

            return null;
        }

        // Média só no ano terminal da disciplina; exige todas as CAs do intervalo.
        if ($classeNumero !== $fim) {
            return null;
        }

        return match (true) {
            // 10.ª–12.ª → (CA10+CA11+CA12)/3
            $classeNumero === 12 && $inicio <= 10 => PautaRegrasTransicaoMinisterio::calcularCfdPorMedia($ca, $ca10, $ca11),
            // 11.ª–12.ª → (CA11+CA12)/2
            $classeNumero === 12 && $inicio === 11 => PautaRegrasTransicaoMinisterio::calcularCfdPorMedia($ca, $ca11),
            // 10.ª–11.ª → (CA10+CA11)/2
            $classeNumero === 11 && $inicio <= 10 => PautaRegrasTransicaoMinisterio::calcularCfdPorMedia($ca, $ca10),
            $classeNumero <= 10 => round($ca),
            default => null,
        };
    }

    private function valorNumericoPauta(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (float) $valor;
    }

    /**
     * Detecta se a disciplina é Projecto Tecnológico (layout especial na 12.ª T3).
     */
    public static function ehProjetoTecnologico(?string $nome, ?string $codigo): bool
    {
        $n = mb_strtoupper($nome ?? '');
        $c = mb_strtoupper($codigo ?? '');

        return (bool) preg_match('/PROJECTO\s+TECNO|PROJ\.?\s*TECN|PROJETO\s+TECNO/i', $n)
            || preg_match('/\bP\.?\s*T\.?\b|^PROJ/i', $c);
    }

    /**
     * T.R.E.I na 12.ª usa MTP1 em vez de MAC1 no modelo oficial.
     */
    public static function usaMtpTrimestre1(?string $nome, ?string $codigo): bool
    {
        $n = mb_strtoupper($nome ?? '');
        $c = mb_strtoupper($codigo ?? '');

        return (bool) preg_match('/T\.?\s*R\.?\s*E\.?\s*I/i', $n)
            || preg_match('/\bTREI\b/i', $c);
    }
}
