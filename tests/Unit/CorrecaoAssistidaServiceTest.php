<?php

namespace Tests\Unit;

use App\Services\Pedagogico\CorrecaoAssistidaService;
use ReflectionMethod;
use Tests\TestCase;

class CorrecaoAssistidaServiceTest extends TestCase
{
    public function test_normaliza_confianca_para_valores_curtos(): void
    {
        $service = app(CorrecaoAssistidaService::class);
        $method = new ReflectionMethod(CorrecaoAssistidaService::class, 'normalizarConfianca');
        $method->setAccessible(true);

        $this->assertSame('alta', $method->invoke($service, 'confiança muito alta na leitura OCR'));
        $this->assertSame('media', $method->invoke($service, 'média'));
        $this->assertSame('baixa', $method->invoke($service, 0.2));
        $this->assertSame('alta', $method->invoke($service, 0.9));
    }
}
