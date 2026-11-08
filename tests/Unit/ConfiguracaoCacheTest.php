<?php

namespace Tests\Unit;

use App\Models\Configuracao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ConfiguracaoCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_usa_cache_e_set_invalida(): void
    {
        Configuracao::set('nota_minima_aprovacao', '10', 'teste');
        Cache::flush();
        Configuracao::flushCache();

        $this->assertSame('10', Configuracao::get('nota_minima_aprovacao'));
        $this->assertTrue(Cache::has('config_nota_minima_aprovacao'));

        Configuracao::set('nota_minima_aprovacao', '12', 'teste');

        $this->assertSame('12', Configuracao::get('nota_minima_aprovacao'));
    }

    public function test_memo_do_request_evita_leituras_repetidas(): void
    {
        Configuracao::set('instituicao_nome', 'Escola Teste');
        Configuracao::flushCache();

        $a = Configuracao::get('instituicao_nome');
        $b = Configuracao::get('instituicao_nome');

        $this->assertSame('Escola Teste', $a);
        $this->assertSame($a, $b);
    }
}
