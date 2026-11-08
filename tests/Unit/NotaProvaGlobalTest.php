<?php

namespace Tests\Unit;

use App\Models\Nota;
use PHPUnit\Framework\TestCase;

class NotaProvaGlobalTest extends TestCase
{
    public function test_valor_prova_global_metodo_existe(): void
    {
        $this->assertTrue(method_exists(Nota::class, 'valorProvaGlobal'));
    }

    public function test_tipos_pt3_e_pg_sao_distintos(): void
    {
        $this->assertSame('PT', Nota::TIPO_PT);
        $this->assertSame('PG', Nota::TIPO_PG);
        $this->assertNotSame(Nota::TIPO_PT, Nota::TIPO_PG);
    }
}
