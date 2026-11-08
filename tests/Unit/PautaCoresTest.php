<?php

namespace Tests\Unit;

use App\Support\PautaCores;
use PHPUnit\Framework\TestCase;

class PautaCoresTest extends TestCase
{
    public function test_css_variables_espelham_constantes(): void
    {
        $vars = PautaCores::cssVariables();

        $this->assertSame('#'.PautaCores::VERDE_PALIDO, $vars['--pauta-verde-palido']);
        $this->assertSame('#'.PautaCores::VERDE_CFD, $vars['--pauta-verde-cfd']);
        $this->assertSame('#'.PautaCores::LARANJA_EXAME, $vars['--pauta-laranja-exame']);
        $this->assertSame('#'.PautaCores::VERMELHO_NOTA, $vars['--pauta-vermelho-nota']);
        $this->assertSame('#'.PautaCores::AZUL_NOTA, $vars['--pauta-azul-nota']);
        $this->assertSame('#'.PautaCores::NAVY, $vars['--pauta-navy']);
        $this->assertSame('#'.PautaCores::ZEBRA, $vars['--pauta-zebra']);
    }

    public function test_css_variables_style_compacto(): void
    {
        $style = PautaCores::cssVariablesStyle();

        $this->assertStringContainsString('--pauta-verde-cfd: #92D050', $style);
        $this->assertStringContainsString('--pauta-laranja-exame: #FCE4D6', $style);
    }

    public function test_fundo_exame_e_cfd(): void
    {
        $this->assertSame('#'.PautaCores::LARANJA_EXAME, PautaCores::fundoHex(['exame' => true]));
        $this->assertSame('#'.PautaCores::VERDE_CFD, PautaCores::fundoHex(['fundo_cfd' => true]));
        $this->assertSame(PautaCores::VERDE_PALIDO, PautaCores::fundoRgb(['fundo_pale' => true]));
    }
}
