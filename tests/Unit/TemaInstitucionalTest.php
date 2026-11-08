<?php

namespace Tests\Unit;

use App\Support\PaginaPublicaConfig;
use App\Support\TemaInstitucional;
use PHPUnit\Framework\TestCase;

class TemaInstitucionalTest extends TestCase
{
    public function test_gera_variaveis_css_a_partir_das_cores(): void
    {
        $pagina = PaginaPublicaConfig::resolve([
            'pagina_publica_cor_primaria' => '#dc2626',
            'pagina_publica_cor_primaria_escura' => '#991b1b',
            'pagina_publica_cor_secundaria' => '#2563eb',
        ]);

        $tema = TemaInstitucional::fromPaginaPublica($pagina);

        $this->assertSame('#dc2626', $tema['light']['--primary-color']);
        $this->assertSame('#2563eb', $tema['light']['--secondary-color']);
        $this->assertStringContainsString('220, 38, 38', $tema['light']['--primary-rgb']);
        $this->assertArrayHasKey('--accent-color', $tema['light']);
        $this->assertSame('#dc2626', $tema['welcome']['--primary']);
    }
}
