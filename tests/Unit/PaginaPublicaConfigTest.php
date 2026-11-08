<?php

namespace Tests\Unit;

use App\Support\PaginaPublicaConfig;
use PHPUnit\Framework\TestCase;

class PaginaPublicaConfigTest extends TestCase
{
    public function test_resolve_usa_defaults_quando_vazio(): void
    {
        $config = PaginaPublicaConfig::resolve([], '2025-2026');

        $this->assertSame('#4f46e5', $config['cor_primaria']);
        $this->assertSame('Ano Lectivo 2025-2026', $config['badge']);
        $this->assertCount(5, $config['sobre_itens']);
    }

    public function test_resolve_aceita_cores_validas(): void
    {
        $config = PaginaPublicaConfig::resolve([
            'pagina_publica_cor_primaria' => '#112233',
            'pagina_publica_cor_primaria_escura' => '#AABBCC',
            'pagina_publica_cor_secundaria' => '#FF00AA',
        ]);

        $this->assertSame('#112233', $config['cor_primaria']);
        $this->assertSame('#aabbcc', $config['cor_primaria_escura']);
        $this->assertSame('#ff00aa', $config['cor_secundaria']);
    }

    public function test_resolve_rejeita_cor_invalida(): void
    {
        $config = PaginaPublicaConfig::resolve([
            'pagina_publica_cor_primaria' => 'vermelho',
        ]);

        $this->assertSame('#4f46e5', $config['cor_primaria']);
    }
}
