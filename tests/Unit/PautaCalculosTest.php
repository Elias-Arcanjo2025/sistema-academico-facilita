<?php

namespace Tests\Unit;

use App\Support\PautaCalculos;
use PHPUnit\Framework\TestCase;

class PautaCalculosTest extends TestCase
{
    public function test_ca_from_cf_sem_pg_usa_zero(): void
    {
        $this->assertSame(12.0, PautaCalculos::caFromCfPg(20, null));
    }

    public function test_ca_from_cf_com_pg(): void
    {
        $this->assertSame(16.0, PautaCalculos::caFromCfPg(20, 10));
    }

    public function test_ca_efectiva_deriva_de_cf(): void
    {
        $ca = PautaCalculos::caEfectivaDisciplina(['pg' => null, 'cf' => 10]);

        $this->assertSame(6.0, $ca);
    }

    public function test_nota_resultado_usa_cfd_quando_coluna_existe(): void
    {
        $nota = PautaCalculos::notaResultadoDisciplina(['ca' => 14, 'cfd' => 9], true);

        $this->assertSame(9.0, $nota);
    }

    public function test_nota_resultado_sem_cfd_usa_ca(): void
    {
        $nota = PautaCalculos::notaResultadoDisciplina(['ca' => 14, 'cfd' => 9], false);

        $this->assertSame(14.0, $nota);
    }

    public function test_recurso_substitui_cfd(): void
    {
        $nota = PautaCalculos::notaResultadoDisciplina([
            'cfd' => 8,
            'recurso' => 12,
        ], true);

        $this->assertSame(12.0, $nota);
    }

    public function test_melhoria_usa_maximo_entre_cfd_e_melhoria(): void
    {
        $nota = PautaCalculos::notaResultadoDisciplina([
            'cfd' => 11,
            'melhoria' => 14,
        ], true);

        $this->assertSame(14.0, $nota);
    }

    public function test_extraordinario_substitui_cfd(): void
    {
        $nota = PautaCalculos::notaResultadoDisciplina([
            'cfd' => 8,
            'recurso' => 12,
            'extraordinario' => 9,
        ], true);

        $this->assertSame(9.0, $nota);
    }

    public function test_recurso_substitui_ca_em_disciplina_continua(): void
    {
        $nota = PautaCalculos::notaResultadoDisciplina([
            'ca' => 8,
            'recurso' => 13,
        ], false);

        $this->assertSame(13.0, $nota);
    }

    public function test_recuperacao_anual_substitui_cfd(): void
    {
        $nota = PautaCalculos::notaResultadoDisciplina([
            'cfd' => 8,
            'recuperacao' => 12,
        ], true);

        $this->assertSame(12.0, $nota);
    }

    public function test_recuperacao_anual_substitui_ca_em_disciplina_continua(): void
    {
        $nota = PautaCalculos::notaResultadoDisciplina([
            'ca' => 7,
            'recuperacao' => 11,
        ], false);

        $this->assertSame(11.0, $nota);
    }

    public function test_recuperacao_seca_sem_cfd_substitui(): void
    {
        $nota = PautaCalculos::cfdEfectivaDisciplina([
            'recuperacao' => 14,
        ]);

        $this->assertSame(14.0, $nota);
    }

    public function test_recuperacao_seca_sem_ca_em_continua(): void
    {
        $nota = PautaCalculos::notaResultadoDisciplina([
            'recuperacao' => 13,
        ], false);

        $this->assertSame(13.0, $nota);
    }

    public function test_cfd_negativa_com_recuperacao_positiva(): void
    {
        $nota = PautaCalculos::notaResultadoDisciplina([
            'cfd' => 7,
            'ca' => 7,
            'recuperacao' => 14,
        ], true);

        $this->assertSame(14.0, $nota);
    }

    public function test_cfd_vazia_com_recuperacao_usa_exame(): void
    {
        $nota = PautaCalculos::notaResultadoDisciplina([
            'ca' => 6,
            'cfd' => null,
            'recuperacao' => 11,
        ], true);

        $this->assertSame(11.0, $nota);
    }

    public function test_recurso_tem_prioridade_sobre_recuperacao(): void
    {
        $nota = PautaCalculos::notaResultadoDisciplina([
            'cfd' => 8,
            'recuperacao' => 11,
            'recurso' => 13,
        ], true);

        $this->assertSame(13.0, $nota);
    }

    public function test_disciplina_com_nota_final(): void
    {
        $this->assertTrue(PautaCalculos::disciplinaComNotaFinal(['cf' => 12]));
        $this->assertTrue(PautaCalculos::disciplinaComNotaFinal(['cfd' => 11]));
        $this->assertTrue(PautaCalculos::disciplinaComNotaFinal(['ca' => 10]));
        $this->assertFalse(PautaCalculos::disciplinaComNotaFinal(['pg' => 12]));
    }

    public function test_detalhe_substituicao_recuperacao(): void
    {
        $detalhe = PautaCalculos::detalheSubstituicaoExame(
            ['ca' => 7, 'recuperacao' => 11],
            7.0,
            11.0,
        );

        $this->assertSame('recuperacao', $detalhe['tipo']);
        $this->assertSame(7, $detalhe['nota_original']);
        $this->assertSame(11, $detalhe['nota_exame']);
        $this->assertSame(11, $detalhe['nota_efectiva']);
    }

    public function test_detalhe_substituicao_recurso(): void
    {
        $detalhe = PautaCalculos::detalheSubstituicaoExame(
            ['cfd' => 8, 'recurso' => 12],
            8.0,
            12.0,
        );

        $this->assertSame('recurso', $detalhe['tipo']);
        $this->assertSame('Exame de recurso', $detalhe['rotulo']);
    }
}
