<?php

namespace Tests\Unit;

use App\Support\PautaExcelFormulasTransicao;
use PHPUnit\Framework\TestCase;

class PautaExcelFormulasTransicaoTest extends TestCase
{
    public function test_formula_resultado_recalcula_dinamicamente(): void
    {
        $builder = new PautaExcelFormulasTransicao(
            [
                1 => ['pg' => 'AA', 'ca' => 'AB', 'cfd' => null],
                2 => ['pg' => 'AC', 'ca' => 'AD', 'cfd' => 'AE'],
            ],
            [
                1 => ['codigo' => 'MAT', 'classe_fim' => 12, 'continua' => true, 'terminal' => false],
                2 => ['codigo' => 'LP', 'classe_fim' => 11, 'continua' => false, 'terminal' => true, 'usa_cfd' => true],
            ],
            [],
            11,
        );

        $formula = $builder->formulaResultado(15);

        $this->assertStringStartsWith('=', $formula);
        $this->assertStringNotContainsString('NOT(AND(', $formula);
        $this->assertStringContainsString('N/TRANSITA', $formula);
        $this->assertStringContainsString('TRANSITA/C DEF', $formula);
        $this->assertStringContainsString('TRANSITA', $formula);
        $this->assertStringContainsString('AB15', $formula);
        $this->assertStringContainsString('AE15', $formula);
        $this->assertStringContainsString('>=3', $formula);
        $this->assertStringContainsString('>2', $formula);
        $this->assertSame(0, substr_count($formula, '(') - substr_count($formula, ')'));
    }

    public function test_formula_observacao_lista_disciplinas_deficiencia(): void
    {
        $builder = new PautaExcelFormulasTransicao(
            [
                1 => ['pg' => 'AA', 'ca' => 'AB', 'cfd' => null],
                2 => ['pg' => 'AC', 'ca' => 'AD', 'cfd' => 'AE'],
            ],
            [
                1 => ['codigo' => 'MAT', 'classe_fim' => 12, 'continua' => true, 'terminal' => false],
                2 => ['codigo' => 'LP', 'classe_fim' => 11, 'continua' => false, 'terminal' => true, 'usa_cfd' => true],
            ],
            [],
            11,
        );

        $formula = $builder->formulaObservacao(20);

        $this->assertStringStartsWith('=', $formula);
        $this->assertStringNotContainsString('NOT(AND(', $formula);
        $this->assertStringNotContainsString('TEXTJOIN', $formula);
        $this->assertStringContainsString('Def.:', $formula);
        $this->assertStringContainsString('Rec.:', $formula);
        $this->assertStringContainsString('MAT', $formula);
        $this->assertStringContainsString('LP', $formula);
        $this->assertStringContainsString('AB20', $formula);
        $this->assertStringContainsString('AE20', $formula);
    }

    public function test_formula_observacao_vazia_sem_negativas(): void
    {
        $builder = new PautaExcelFormulasTransicao(
            [
                1 => ['pg' => 'AA', 'ca' => 'AB', 'cfd' => null],
            ],
            [
                1 => ['codigo' => 'MAT', 'classe_fim' => 12, 'continua' => true, 'terminal' => false],
            ],
            [],
            11,
        );

        $formula = $builder->formulaObservacao(5);

        $this->assertStringContainsString('=0', $formula);
        $this->assertStringContainsString('""', $formula);
        $this->assertStringNotContainsString('>=3', $formula);
    }

    public function test_formulas_aparecem_com_notas_parciais(): void
    {
        $builder = new PautaExcelFormulasTransicao(
            [
                1 => ['pg' => 'AA', 'ca' => 'AB', 'cfd' => null],
                2 => ['pg' => 'AC', 'ca' => 'AD', 'cfd' => 'AE'],
            ],
            [
                1 => ['codigo' => 'MAT', 'classe_fim' => 12, 'continua' => true, 'terminal' => false],
                2 => ['codigo' => 'LP', 'classe_fim' => 11, 'continua' => false, 'terminal' => true, 'usa_cfd' => true],
            ],
            [],
            11,
        );

        $resultado = $builder->formulaResultado(15);
        $observacao = $builder->formulaObservacao(15);

        // Não exige que todas as disciplinas tenham nota lançada.
        $this->assertStringNotContainsString('ISNUMBER(AB15),ISNUMBER(AE15)', $resultado);
        $this->assertStringNotContainsString('NOT(AND(', $resultado);
        $this->assertStringNotContainsString('NOT(AND(', $observacao);
        $this->assertStringContainsString('TRANSITA', $resultado);
        $this->assertStringContainsString('Def.:', $observacao);
    }

    public function test_formula_11_classe_inclui_cfd_terminal_anterior(): void
    {
        $builder = new PautaExcelFormulasTransicao(
            [
                1 => ['pg' => 'AA', 'ca' => 'AB', 'cfd' => null],
            ],
            [
                1 => ['codigo' => 'MAT', 'classe_fim' => 12, 'continua' => true, 'terminal' => false],
            ],
            [['col' => 'E', 'codigo' => 'TIC']],
            11,
        );

        $formula = $builder->formulaResultado(23);

        $this->assertStringContainsString('E23', $formula);
        $this->assertStringContainsString('AB23', $formula);
    }

    public function test_formula_continua_usa_coluna_recuperacao(): void
    {
        $builder = new PautaExcelFormulasTransicao(
            [
                1 => ['pg' => 'AA', 'ca' => 'AB', 'cfd' => null, 'recuperacao' => 'AC', 'recurso' => null],
            ],
            [
                1 => ['codigo' => 'MAT', 'classe_fim' => 12, 'continua' => true, 'terminal' => false],
            ],
            [],
            11,
        );

        $negs = $builder->negatividades(15);
        $this->assertCount(1, $negs['continuas']);
        $this->assertStringContainsString('IF(ISNUMBER(AC15)', $negs['continuas'][0]['neg']);
        $this->assertStringContainsString('AB15', $negs['continuas'][0]['neg']);
    }

    public function test_formula_terminal_dt_com_recuperacao_positiva_nao_conta_negativa(): void
    {
        $builder = new PautaExcelFormulasTransicao(
            [
                1 => ['pg' => 'AA', 'ca' => 'AB', 'cfd' => 'AC', 'recuperacao' => 'AD', 'recurso' => null],
            ],
            [
                1 => ['codigo' => 'DT', 'classe_fim' => 11, 'continua' => false, 'terminal' => true, 'usa_cfd' => true],
            ],
            [],
            11,
        );

        $negs = $builder->negatividades(27);
        $this->assertCount(1, $negs['terminais']);
        $this->assertStringContainsString('IF(ISNUMBER(AD27)', $negs['terminais'][0]['neg']);
        $this->assertStringContainsString('AD27<10', $negs['terminais'][0]['cond']);
    }


    public function test_formula_observacao_ignora_terminal_com_recuperacao_aprovada(): void
    {
        $builder = new PautaExcelFormulasTransicao(
            [
                1 => ['pg' => 'AA', 'ca' => 'AB', 'cfd' => 'AC', 'recuperacao' => 'AD', 'recurso' => null],
            ],
            [
                1 => ['codigo' => 'LP', 'classe_fim' => 10, 'continua' => false, 'terminal' => true, 'usa_cfd' => true],
            ],
            [],
            10,
        );

        $formula = $builder->formulaObservacao(12);

        $this->assertStringContainsString('AD12', $formula);
    }

    public function test_formula_10_classe_ignora_cfd_terminal_anterior(): void
    {
        $builder = new PautaExcelFormulasTransicao(
            [
                1 => ['pg' => 'AA', 'ca' => 'AB', 'cfd' => null],
            ],
            [
                1 => ['codigo' => 'MAT', 'classe_fim' => 12, 'continua' => true, 'terminal' => false],
            ],
            [['col' => 'E', 'codigo' => 'TIC']],
            10,
        );

        $formula = $builder->formulaResultado(23);

        $this->assertStringNotContainsString('E23', $formula);
        $this->assertStringContainsString('AB23', $formula);
    }

    public function test_obs_11_aparece_com_transita_c_def_ou_recuperacao(): void
    {
        $builder = new PautaExcelFormulasTransicao(
            [
                1 => ['pg' => 'AA', 'ca' => 'AB', 'cfd' => null],
                2 => ['pg' => 'AC', 'ca' => 'AD', 'cfd' => 'AE'],
            ],
            [
                1 => ['codigo' => 'MAT', 'classe_fim' => 12, 'continua' => true, 'terminal' => false],
                2 => ['codigo' => 'LP', 'classe_fim' => 11, 'continua' => false, 'terminal' => true, 'usa_cfd' => true],
            ],
            [],
            11,
            null,
            2,
        );

        $formula = $builder->formulaObservacao(20, 'DK');

        $this->assertStringContainsString('TRANSITA/C DEF', $formula);
        $this->assertStringContainsString('RECUPERAÇÃO', $formula);
        $this->assertStringContainsString('DK20', $formula);
        $this->assertStringContainsString('Def.:', $formula);
        $this->assertStringContainsString('Rec.:', $formula);
    }

    public function test_resultado_com_somas_helpers_respeita_regras_11(): void
    {
        $builder = new PautaExcelFormulasTransicao([], [], [], 11, null, 2);
        $formula = $builder->formulaResultadoComSomas('(A15)', '(B15)');

        $this->assertStringContainsString('TRANSITA/C DEF', $formula);
        $this->assertStringContainsString('RECUPERAÇÃO', $formula);
        $this->assertStringContainsString('N/TRANSITA', $formula);
        $this->assertStringContainsString('(A15)>=3', $formula);
        $this->assertLessThan(500, strlen($formula));
    }

    public function test_formula_observacao_12_com_helpers_cabe_no_limite(): void
    {
        $builder = new PautaExcelFormulasTransicao([], [], [], 12, 3);
        $lista = '"MAT,LP,QUIM"';
        $formula = $builder->formulaObservacaoComListas(15, 'CM', '""', '""', $lista);

        $this->assertStringStartsWith('=', $formula);
        $this->assertStringContainsString('CM15', $formula);
        $this->assertStringContainsString('Rec.:', $formula);
        $this->assertLessThan(500, strlen($formula));
    }
}
