<?php

namespace Tests\Unit;

use App\Support\NotaArredondamento;
use PHPUnit\Framework\TestCase;

class NotaArredondamentoTest extends TestCase
{
    public function test_arredonda_por_defeito_abaixo_de_meio(): void
    {
        $this->assertSame(11.0, NotaArredondamento::media(11.4));
        $this->assertSame(9.0, NotaArredondamento::media(9.49));
    }

    public function test_arredonda_por_excesso_a_partir_de_meio(): void
    {
        $this->assertSame(12.0, NotaArredondamento::media(11.5));
        $this->assertSame(10.0, NotaArredondamento::media(9.5));
        $this->assertSame(10.0, NotaArredondamento::media(9.6));
    }

    public function test_ca_arredonda_apos_cf_como_no_excel(): void
    {
        // CF = ROUND((10+9)/2,0)=10; CA = ROUND(0.6*10+0.4*9,0)=ROUND(9.6,0)=10
        $cf = \App\Support\NotaArredondamento::media((10 + 9) / 2);
        $this->assertSame(10.0, $cf);
        $this->assertSame(10.0, \App\Support\PautaCalculos::caFromCfPg($cf, 9));
        // CF intermédio 9.5 (legado) também sobe antes da CA
        $this->assertSame(10.0, \App\Support\PautaCalculos::caFromCfPg(9.5, 9));
    }

    public function test_media_int_e_null(): void
    {
        $this->assertSame(12, NotaArredondamento::mediaInt(11.5));
        $this->assertNull(NotaArredondamento::media(null));
        $this->assertNull(NotaArredondamento::mediaInt(null));
    }
}
