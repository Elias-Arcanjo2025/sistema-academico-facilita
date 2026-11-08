<?php

namespace Tests\Unit;

use App\Models\Nota;
use App\Services\PautaService;
use PHPUnit\Framework\TestCase;

class TiposCaCfdAnterioresTest extends TestCase
{
    public function test_mat_12_precisa_ca10_e_ca11(): void
    {
        $tipos = PautaService::tiposCaHistoricosParaDisciplina(12, 10, 12, 12);

        $this->assertSame([Nota::TIPO_CA10, Nota::TIPO_CA11], $tipos);
    }

    public function test_lp_12_precisa_ca10_e_ca11(): void
    {
        $tipos = PautaService::tiposCaHistoricosParaDisciplina(12, 10, 11, 12);

        $this->assertSame([Nota::TIPO_CA10, Nota::TIPO_CA11], $tipos);
    }

    public function test_lp_11_precisa_apenas_ca10(): void
    {
        $tipos = PautaService::tiposCaHistoricosParaDisciplina(11, 10, 11, 12);

        $this->assertSame([Nota::TIPO_CA10], $tipos);
    }

    public function test_tic_10_nao_precisa_ca(): void
    {
        $tipos = PautaService::tiposCaHistoricosParaDisciplina(12, 10, 10, 12);

        $this->assertSame([], $tipos);
    }

    public function test_fai_11_nao_precisa_ca(): void
    {
        $tipos = PautaService::tiposCaHistoricosParaDisciplina(12, 11, 11, 12);

        $this->assertSame([], $tipos);
    }

    public function test_tic_10_e_fai_11_sao_cfd_igual_ca(): void
    {
        $this->assertTrue(PautaService::cfdIgualCaDoAno(10, 10, 10, 12));
        $this->assertTrue(PautaService::cfdIgualCaDoAno(11, 11, 11, 12));
    }
}
