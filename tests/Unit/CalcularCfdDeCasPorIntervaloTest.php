<?php

namespace Tests\Unit;

use App\Services\PautaService;
use PHPUnit\Framework\TestCase;

class CalcularCfdDeCasPorIntervaloTest extends TestCase
{
    public function test_solo_10_cfd_igual_ca10(): void
    {
        $this->assertSame(14.0, PautaService::calcularCfdDeCasPorIntervalo(10, 10, 14.0));
    }

    public function test_solo_11_cfd_igual_ca11(): void
    {
        $this->assertSame(13.0, PautaService::calcularCfdDeCasPorIntervalo(11, 11, null, 13.0));
    }

    public function test_solo_12_cfd_igual_ca12(): void
    {
        $this->assertSame(15.0, PautaService::calcularCfdDeCasPorIntervalo(12, 12, null, null, 15.0));
    }

    public function test_10_a_11_media_duas_cas(): void
    {
        // LP 10.ª–11.ª: (12+10)/2 = 11
        $this->assertSame(11.0, PautaService::calcularCfdDeCasPorIntervalo(10, 11, 12.0, 10.0));
    }

    public function test_10_a_11_exige_ambas_cas(): void
    {
        $this->assertNull(PautaService::calcularCfdDeCasPorIntervalo(10, 11, 12.0, null));
        $this->assertNull(PautaService::calcularCfdDeCasPorIntervalo(10, 11, null, 10.0));
    }

    public function test_10_a_12_media_tres_cas(): void
    {
        // MAT 10.ª–12.ª: (10+14+12)/3 = 12
        $this->assertSame(12.0, PautaService::calcularCfdDeCasPorIntervalo(10, 12, 10.0, 14.0, 12.0));
    }

    public function test_11_a_12_media_duas_cas(): void
    {
        $this->assertSame(14.0, PautaService::calcularCfdDeCasPorIntervalo(11, 12, null, 12.0, 16.0));
    }

    public function test_regra_dos_7_bloqueia_media(): void
    {
        $this->assertNull(PautaService::calcularCfdDeCasPorIntervalo(10, 11, 18.0, 6.0));
    }
}
