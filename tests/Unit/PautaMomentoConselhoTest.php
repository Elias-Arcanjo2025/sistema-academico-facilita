<?php

namespace Tests\Unit;

use App\Models\Nota;
use App\Support\PautaMomentoConselho;
use PHPUnit\Framework\TestCase;

class PautaMomentoConselhoTest extends TestCase
{
    public function test_seleccionar_nota_pre_conselho(): void
    {
        $pre = new Nota(['estado_conselho' => Nota::ESTADO_PRE_CONSELHO, 'valor' => 12]);
        $pos = new Nota(['estado_conselho' => Nota::ESTADO_POS_CONSELHO, 'valor' => 15]);

        $sel = Nota::seleccionarNotaPorMomento(collect([$pre, $pos]), PautaMomentoConselho::PRE);

        $this->assertSame(12.0, (float) $sel->valor);
    }

    public function test_seleccionar_nota_pos_conselho_prefere_ajuste(): void
    {
        $pre = new Nota(['estado_conselho' => Nota::ESTADO_PRE_CONSELHO, 'valor' => 12]);
        $pos = new Nota(['estado_conselho' => Nota::ESTADO_POS_CONSELHO, 'valor' => 15]);

        $sel = Nota::seleccionarNotaPorMomento(collect([$pre, $pos]), PautaMomentoConselho::POS);

        $this->assertSame(15.0, (float) $sel->valor);
    }
}
