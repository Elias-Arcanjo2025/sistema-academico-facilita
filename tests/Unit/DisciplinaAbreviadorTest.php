<?php

namespace Tests\Unit;

use App\Support\DisciplinaAbreviador;
use PHPUnit\Framework\TestCase;

class DisciplinaAbreviadorTest extends TestCase
{
    public function test_abrevia_disciplinas_longas_politecnico(): void
    {
        $this->assertSame('EMPREEND.', DisciplinaAbreviador::paraBoletimCompacto('Empreendedorismo'));
        $this->assertSame('PROJ. TEC.', DisciplinaAbreviador::paraBoletimCompacto('Projecto Tecnológico'));
        $this->assertSame('ING. TEC.', DisciplinaAbreviador::paraBoletimCompacto('Inglês Técnico'));
    }

    public function test_mantem_nomes_curtos(): void
    {
        $this->assertSame('TLP', DisciplinaAbreviador::paraBoletimCompacto('TLP'));
        $this->assertSame('FÍSICA', DisciplinaAbreviador::paraBoletimCompacto('Física'));
        $this->assertSame('MATEMÁTICA', DisciplinaAbreviador::paraBoletimCompacto('Matemática'));
    }

    public function test_usa_codigo_quando_curto(): void
    {
        $this->assertSame('TLP', DisciplinaAbreviador::paraBoletimCompacto(
            'Tecnologias de Programação de Computadores',
            'TLP'
        ));
    }
}
