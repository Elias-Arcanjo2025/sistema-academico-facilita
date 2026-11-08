<?php

namespace Tests\Unit;

use App\Services\Pedagogico\ChaveCorrecaoService;
use Tests\TestCase;

class ChaveCorrecaoServiceTest extends TestCase
{
    public function test_resolve_resposta_objectiva_pela_letra(): void
    {
        $service = new ChaveCorrecaoService;

        $resultado = $service->resolverRespostaEsperada([
            'tipo' => 'objectiva',
            'opcoes' => ['Opção A', 'Opção B', 'Opção C', 'Opção D'],
            'resposta_correcta' => 'B',
        ]);

        $this->assertSame('Opção B', $resultado['texto']);
        $this->assertSame('B', $resultado['letra']);
    }

    public function test_resolve_resposta_objectiva_sem_letra_usa_primeira_opcao(): void
    {
        $service = new ChaveCorrecaoService;

        $resultado = $service->resolverRespostaEsperada([
            'tipo' => 'objectiva',
            'opcoes' => ['Resposta correcta', 'Errada 1', 'Errada 2'],
        ]);

        $this->assertSame('Resposta correcta', $resultado['texto']);
        $this->assertSame('A', $resultado['letra']);
    }
}
