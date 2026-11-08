<?php

namespace Tests\Unit;

use App\Services\RegimePauta\RegimeAdministracao;
use App\Services\RegimePauta\RegimePolitecnico;
use PHPUnit\Framework\TestCase;

class ValorParaResultadoTest extends TestCase
{
    private RegimePolitecnico $politecnico;

    private RegimeAdministracao $administracao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->politecnico = new RegimePolitecnico();
        $this->administracao = new RegimeAdministracao();
    }

    public function test_t3_11a_sem_pg_conta_ca(): void
    {
        $valor = $this->politecnico->valorParaResultado([
            'cf' => 12,
            'pg' => null,
            'ca' => 11,
        ], 3, 11);

        $this->assertSame(11.0, $valor);
    }

    public function test_t3_11a_com_pg_e_ca_conta(): void
    {
        $valor = $this->politecnico->valorParaResultado([
            'cf' => 12,
            'pg' => 14,
            'ca' => 13,
        ], 3, 11);

        $this->assertSame(13.0, $valor);
    }

    public function test_t3_12a_usa_cfd_quando_existe(): void
    {
        $valor = $this->politecnico->valorParaResultado([
            'tem_coluna_cfd' => true,
            'pg' => 14,
            'ca' => 11,
            'cfd' => 12,
        ], 3, 12);

        $this->assertSame(12.0, $valor);
    }

    public function test_chave_coluna_resultado_t3_11a_e_ca(): void
    {
        $cols = $this->politecnico->colunasDisciplina(3, 11, [
            'classe_inicio' => 10,
            'classe_fim' => 12,
            'classe_max' => 12,
        ]);

        $this->assertSame('ca', $this->politecnico->chaveColunaResultado(3, 11, $cols));
    }

    public function test_chave_coluna_resultado_t3_11a_terminal_usa_cfd(): void
    {
        $cols = $this->politecnico->colunasDisciplina(3, 11, [
            'classe_inicio' => 10,
            'classe_fim' => 11,
            'classe_max' => 12,
            'terminal_classe_atual' => true,
        ]);

        $this->assertSame('cfd', $this->politecnico->chaveColunaResultado(3, 11, $cols));
    }

    public function test_t3_11a_com_coluna_cfd_usa_cfd(): void
    {
        $valor = $this->politecnico->valorParaResultado([
            'tem_coluna_cfd' => true,
            'ca' => 12,
            'cfd' => 9,
        ], 3, 11);

        $this->assertSame(9.0, $valor);
    }

    public function test_chave_coluna_resultado_t3_12a_com_cfd(): void
    {
        $cols = $this->politecnico->colunasDisciplina(3, 12, [
            'classe_inicio' => 10,
            'classe_fim' => 12,
            'classe_max' => 12,
        ]);

        $this->assertSame('cfd', $this->politecnico->chaveColunaResultado(3, 12, $cols));
    }

    public function test_administracao_t3_sem_pg_conta_ca(): void
    {
        $valor = $this->administracao->valorParaResultado([
            'cf' => 12,
            'ca' => 11,
        ], 3, 11);

        $this->assertSame(11.0, $valor);
    }
}
