<?php

namespace Tests\Unit;

use App\Services\PautaService;
use PHPUnit\Framework\TestCase;

class CfdIgualCaDoAnoTest extends TestCase
{
    public function test_tic_10_terminal(): void
    {
        $this->assertTrue(PautaService::cfdIgualCaDoAno(10, 10, 10, 12));
    }

    public function test_lp_11_media_das_cas(): void
    {
        $this->assertFalse(PautaService::cfdIgualCaDoAno(10, 11, 11, 12));
    }

    public function test_dt_11_solo_classe(): void
    {
        $this->assertTrue(PautaService::cfdIgualCaDoAno(11, 11, 11, 12));
    }

    public function test_mat_12_nao_igual_ca_somente(): void
    {
        $this->assertFalse(PautaService::cfdIgualCaDoAno(10, 12, 12, 12));
    }

    public function test_mat_11_ainda_nao_terminal(): void
    {
        $this->assertFalse(PautaService::cfdIgualCaDoAno(10, 12, 11, 12));
    }
}
