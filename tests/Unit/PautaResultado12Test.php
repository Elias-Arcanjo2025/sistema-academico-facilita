<?php

namespace Tests\Unit;

use App\Support\PautaExcelFormulasTransicao;
use App\Support\PautaResultadoTransicao;
use PHPUnit\Framework\TestCase;

class PautaResultado12Test extends TestCase
{
    public function test_apto_sem_negativas(): void
    {
        $r = PautaResultadoTransicao::resolverResultado12(0, [], 3);

        $this->assertSame('APTO', $r['resultado']);
        $this->assertSame('', $r['observacao']);
        $this->assertFalse($r['reprova_directamente']);
    }

    public function test_recurso_ate_limite_com_observacao(): void
    {
        $neg = [
            ['disciplina_nome' => 'MAT'],
            ['disciplina_nome' => 'TLP'],
            ['disciplina_nome' => 'SEAC'],
        ];
        $r = PautaResultadoTransicao::resolverResultado12(3, $neg, 3);

        $this->assertSame('RECURSO', $r['resultado']);
        $this->assertSame('Rec.: MAT, TLP, SEAC', $r['observacao']);
        $this->assertFalse($r['reprova_directamente']);
    }

    public function test_n_apto_acima_do_limite_lista_todas(): void
    {
        $neg = [
            ['disciplina_nome' => 'MAT'],
            ['disciplina_nome' => 'TLP'],
            ['disciplina_nome' => 'SEAC'],
            ['disciplina_nome' => 'FIS'],
        ];
        $r = PautaResultadoTransicao::resolverResultado12(4, $neg, 3);

        $this->assertSame('N/APTO', $r['resultado']);
        $this->assertSame('MAT, TLP, SEAC, FIS', $r['observacao']);
        $this->assertTrue($r['reprova_directamente']);
    }

    public function test_limite_configuravel(): void
    {
        $neg = [
            ['disciplina_nome' => 'A'],
            ['disciplina_nome' => 'B'],
        ];

        $this->assertSame('RECURSO', PautaResultadoTransicao::resolverResultado12(2, $neg, 2)['resultado']);
        $this->assertSame('N/APTO', PautaResultadoTransicao::resolverResultado12(2, $neg, 1)['resultado']);
    }

    public function test_formula_excel_12_usa_apto_recurso_napto(): void
    {
        $builder = new PautaExcelFormulasTransicao(
            [
                1 => ['pg' => 'AA', 'ca' => 'AB', 'cfd' => null],
                2 => ['pg' => 'AC', 'ca' => 'AD', 'cfd' => 'AE'],
            ],
            [
                1 => ['codigo' => 'MAT', 'classe_fim' => 12, 'continua' => true, 'terminal' => false],
                2 => ['codigo' => 'TLP', 'classe_fim' => 12, 'continua' => false, 'terminal' => true, 'usa_cfd' => true],
            ],
            [],
            12,
            3,
        );

        $resultado = $builder->formulaResultado(15);
        $observacao = $builder->formulaObservacao(15);

        $this->assertStringContainsString('APTO', $resultado);
        $this->assertStringContainsString('RECURSO', $resultado);
        $this->assertStringContainsString('N/APTO', $resultado);
        $this->assertStringNotContainsString('TRANSITA', $resultado);
        $this->assertStringContainsString('Rec.:', $observacao);
        $this->assertStringContainsString('<=3', $resultado);
    }
}
