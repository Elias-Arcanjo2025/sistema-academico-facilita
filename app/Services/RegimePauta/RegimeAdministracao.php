<?php

namespace App\Services\RegimePauta;

use App\Models\Curso;
use App\Models\Nota;

/**
 * Regime ADMINISTRAÇÃO — Contabilidade e Gestão / Gestão Empresarial.
 *
 * Características:
 *  - Trimestres totalmente independentes. NÃO existe MFT2 acumulado.
 *  - T1, T2 são iguais ao politécnico exceto que T2 NÃO mostra coluna MFT2.
 *  - T3 (pauta final) mostra apenas CF | PG | CA (+ RECURSO se 12.ª) por disciplina.
 *  - CF = (MT1 + MT2 + MT3) / 3
 *  - CA = 0.6 × CF + 0.4 × PG (igual ao politécnico)
 *  - Pauta final tem coluna "MÉDIA" do aluno (média aritmética das CAs do ano).
 *  - Resultado Transita/Não Transita baseado em CA (em TODAS as classes).
 *  - Sem carry-overs de CA-10ª / CA-11ª como colunas (são guardadas para
 *    eventual cálculo de CFD em terminais, mas não aparecem na pauta).
 *  - Disciplinas terminais continuam: CFD = CA do ano em que termina.
 */
class RegimeAdministracao implements RegimePautaInterface
{
    public function id(): string
    {
        return Curso::REGIME_ADMINISTRACAO;
    }

    public function nome(): string
    {
        return 'Administração (Contabilidade, Gestão)';
    }

    public function precisaMFT2(): bool
    {
        return false;
    }

    public function carregaCAsAnteriores(): bool
    {
        return false;
    }

    public function temColunaMediaAluno(int $trimestre): bool
    {
        return $trimestre === 3;
    }

    public function colunasDisciplina(int $trimestre, int $classeNumero, array $flags = []): array
    {
        $comRecurso = $flags['recurso'] ?? false;

        if ($trimestre === 1) {
            return $this->anexarRecuperacaoSeTransicao([
                ['key' => 'faltas_j', 'header' => 'J', 'group' => 'FALTAS', 'tipo' => 'int'],
                ['key' => 'faltas_i', 'header' => 'I', 'group' => 'FALTAS', 'tipo' => 'int'],
                ['key' => 'mac', 'header' => 'MAC1', 'tipo' => 'nota'],
                ['key' => 'pp', 'header' => 'PP1', 'tipo' => 'nota'],
                ['key' => 'pt', 'header' => 'PT1', 'tipo' => 'nota'],
                ['key' => 'mt', 'header' => 'MT1', 'tipo' => 'formula', 'destaque' => true],
            ], $classeNumero);
        }

        if ($trimestre === 2) {
            return $this->anexarRecuperacaoSeTransicao([
                ['key' => 'faltas_j', 'header' => 'J', 'group' => 'FALTAS', 'tipo' => 'int'],
                ['key' => 'faltas_i', 'header' => 'I', 'group' => 'FALTAS', 'tipo' => 'int'],
                ['key' => 'mac', 'header' => 'MAC2', 'tipo' => 'nota'],
                ['key' => 'pp', 'header' => 'PP2', 'tipo' => 'nota'],
                ['key' => 'pt', 'header' => 'PT2', 'tipo' => 'nota'],
                ['key' => 'mt', 'header' => 'MT2', 'tipo' => 'formula', 'destaque' => true],
            ], $classeNumero);
        }

        // T3 — pauta final RESUMIDA. Mostra apenas CF | PG | CA (+ RECURSO se 12.ª).
        // Os 3 MTs entram na fórmula de CF mas não são apresentados como colunas
        // (já estavam nas pautas trimestrais T1 e T2 respectivas).
        // CF é calculado SERVER-SIDE (sem coluna MT no Excel para fazer fórmula).
        $cols = [
            ['key' => 'cf', 'header' => 'CF', 'tipo' => 'nota'],
            ['key' => 'pg', 'header' => 'PG', 'tipo' => 'nota'],
            ['key' => 'ca', 'header' => 'CA', 'tipo' => 'formula', 'destaque' => true],
        ];

        // RECURSO / RECUP. só nas disciplinas com nota já lançada.
        $comRecursoDisciplina = $flags['recurso_disciplina'] ?? $comRecurso;
        if ($classeNumero === 12 && $comRecursoDisciplina) {
            $cols[] = ['key' => 'recurso', 'header' => 'RECURSO', 'tipo' => 'nota', 'exame' => true];
        }

        $comRecuperacaoDisciplina = $flags['recuperacao_disciplina'] ?? false;
        if (in_array($classeNumero, [10, 11], true) && ($flags['recuperacao'] ?? false) && $comRecuperacaoDisciplina) {
            $cols[] = ['key' => 'recuperacao', 'header' => 'RECUP.', 'tipo' => 'nota', 'exame' => true];
        }

        return $this->anexarRecuperacaoSeTransicao($cols, $classeNumero);
    }

    /**
     * A coluna REC (recuperação) não é apresentada na pauta; as notas de
     * recuperação continuam a ser usadas nos cálculos de MT efectiva.
     *
     * @param  array<int, array<string, mixed>>  $cols
     * @return array<int, array<string, mixed>>
     */
    private function anexarRecuperacaoSeTransicao(array $cols, int $classeNumero): array
    {
        return $cols;
    }

    public function tiposNotaUsados(int $trimestre, int $classeNumero): array
    {
        $tipos = match ($trimestre) {
            1 => [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT, Nota::TIPO_MT],
            2 => [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT, Nota::TIPO_MT],
            3 => [
                Nota::TIPO_MT,
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
        // CF = (MT1 + MT2 + MT3) / 3 — ROUND à unidade (igual ao Excel)
        $mts = array_filter([$mt1, $mt2, $mt3], fn ($v) => $v !== null);
        if (count($mts) !== 3) {
            return null;
        }

        return \App\Support\NotaArredondamento::media(array_sum($mts) / 3);
    }

    public function componentesMediaTrimestral(int $trimestre): array
    {
        return [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT];
    }

    public function divisorMediaTrimestral(int $trimestre): int
    {
        return 3;
    }

    public function usaProvaTrimestral(int $trimestre): bool
    {
        return true;
    }

    public function valorParaResultado(array $base, int $trimestre, int $classeNumero): ?float
    {
        if ($trimestre !== 3) {
            $mt = isset($base['mt']) ? (float) $base['mt'] : null;
            $rec = isset($base['recuperacao']) ? (float) $base['recuperacao'] : null;

            return \App\Services\RecuperacaoService::mtEfectiva($mt, $rec);
        }

        $ca = $base['ca'] ?? null;
        if ($ca === null || $ca === '') {
            return null;
        }

        return \App\Support\PautaCalculos::cfdEfectivaDisciplina($base, (float) $ca);
    }

    public function chaveColunaResultado(int $trimestre, int $classeNumero, array $colsDisc): string
    {
        return $trimestre === 3 ? 'ca' : 'mt';
    }

    public function expressoesExcel(callable $get, int $classeNumero): array
    {
        $mac = $this->celulaNumericaExcel($get, 'mac');
        $pp = $this->celulaNumericaExcel($get, 'pp');
        $pt = $this->celulaNumericaExcel($get, 'pt');
        $cf = $this->celulaNumericaExcel($get, 'cf');
        $pg = $this->celulaNumericaExcel($get, 'pg');

        return [
            'mt_t1' => "=ROUND(({$mac}+{$pp}+{$pt})/3,0)",
            'mt_t2' => "=ROUND(({$mac}+{$pp}+{$pt})/3,0)",
            'mt_t3' => '',

            'mft2' => '',

            'cf' => '',

            'ca' => "=IF(NOT(ISNUMBER(".$get('cf').")),\"\",ROUND(0.6*{$cf}+0.4*{$pg},0))",

            // CFD não aparece como coluna em administração (a CA já é a final).
            'cfd_10' => '',
            'cfd_11' => '',
            'cfd_12_de_11' => '',
            'cfd_12' => '',
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
        return null;
    }
}
