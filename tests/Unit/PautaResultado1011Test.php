<?php

namespace Tests\Unit;

use App\Support\PautaExcelFormulasTransicao;
use App\Support\PautaResultadoTransicao;
use PHPUnit\Framework\TestCase;

class PautaResultado1011Test extends TestCase
{
    public function test_nota_oficial_terminal_usa_recuperacao_em_vez_de_cfd(): void
    {
        $nota = PautaResultadoTransicao::notaOficialDisciplina(
            ['cfd' => 7, 'ca' => 7, 'recuperacao' => 14, 'tem_coluna_cfd' => true],
            ['classe_fim' => 11, 'terminal' => true, 'usa_cfd' => true],
            11,
        );

        $this->assertSame(14.0, $nota);
    }

    public function test_nota_oficial_continua_usa_recuperacao_em_vez_de_ca(): void
    {
        $nota = PautaResultadoTransicao::notaOficialDisciplina(
            ['ca' => 9, 'recuperacao' => 13],
            ['classe_fim' => 12, 'continua' => true, 'terminal' => false],
            11,
        );

        $this->assertSame(13.0, $nota);
    }


    public function test_so_continuas_transita_c_def(): void
    {
        $cont = [['disciplina_nome' => 'MAT']];
        $r = PautaResultadoTransicao::resolverResultado(1, [], $cont, 2);

        $this->assertSame('TRANSITA/C DEF', $r['resultado']);
        $this->assertSame('Def.: MAT', $r['observacao']);
    }

    public function test_terminais_recuperacao_com_observacao(): void
    {
        $term = [['disciplina_nome' => 'LP']];
        $r = PautaResultadoTransicao::resolverResultado(1, $term, [], 2);

        $this->assertSame('RECUPERAÇÃO', $r['resultado']);
        $this->assertSame('Rec.: LP', $r['observacao']);
    }

    public function test_misto_def_e_rec_resultado_recuperacao(): void
    {
        $cont = [['disciplina_nome' => 'SEAC']];
        $term = [['disciplina_nome' => 'LP']];
        $r = PautaResultadoTransicao::resolverResultado(2, $term, $cont, 2);

        $this->assertSame('RECUPERAÇÃO', $r['resultado']);
        $this->assertSame('Def.: SEAC | Rec.: LP', $r['observacao']);
    }

    public function test_acima_do_limite_n_transita(): void
    {
        $term = [
            ['disciplina_nome' => 'A'],
            ['disciplina_nome' => 'B'],
            ['disciplina_nome' => 'C'],
        ];
        $r = PautaResultadoTransicao::resolverResultado(3, $term, [], 2);

        $this->assertSame('N/TRANSITA', $r['resultado']);
        $this->assertTrue($r['reprova_directamente']);
    }

    public function test_formula_excel_10_11_usa_recuperacao(): void
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

        $resultado = $builder->formulaResultadoComSomas('(A15)', '(B15)');

        $this->assertStringContainsString('RECUPERAÇÃO', $resultado);
        $this->assertStringContainsString('TRANSITA/C DEF', $resultado);
        $this->assertStringContainsString('N/TRANSITA', $resultado);
        $this->assertStringContainsString('(B15)>=1', $resultado);
    }
}
