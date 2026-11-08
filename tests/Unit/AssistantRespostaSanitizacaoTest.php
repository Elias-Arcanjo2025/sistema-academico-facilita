<?php

namespace Tests\Unit;

use App\Services\Assistente\VirtualAssistantService;
use ReflectionMethod;
use Tests\TestCase;

class AssistantRespostaSanitizacaoTest extends TestCase
{
    protected function normalizar(string $resposta): string
    {
        $metodo = new ReflectionMethod(VirtualAssistantService::class, 'normalizarResposta');

        return $metodo->invoke(app(VirtualAssistantService::class), $resposta);
    }

    public function test_remove_bloco_de_contexto_ecoado(): void
    {
        $resposta = "CONTEXTO DO SISTEMA (JSON)\n{\"perfil\": \"completo\", \"escopo\": {\"nivel_pii\": \"completo\"}}";

        $limpa = $this->normalizar($resposta);

        $this->assertStringNotContainsString('CONTEXTO DO SISTEMA', $limpa);
        $this->assertStringNotContainsString('nivel_pii', $limpa);
        $this->assertNotSame('', $limpa);
    }

    public function test_preserva_texto_util_antes_e_depois_do_bloco(): void
    {
        $resposta = "O professor já lançou as notas.\n\nCONTEXTO DO SISTEMA (JSON)\n{\"perfil\": \"completo\"}\nPosso ajudar em mais alguma coisa?";

        $limpa = $this->normalizar($resposta);

        $this->assertStringContainsString('O professor já lançou as notas.', $limpa);
        $this->assertStringContainsString('Posso ajudar em mais alguma coisa?', $limpa);
        $this->assertStringNotContainsString('CONTEXTO DO SISTEMA', $limpa);
    }

    public function test_resposta_normal_fica_intacta(): void
    {
        $limpa = $this->normalizar('Ralfe Lourenço é professor de Química nas turmas TI10AT, TI10BT e TI10CT.');

        $this->assertSame('Ralfe Lourenço é professor de Química nas turmas TI10AT, TI10BT e TI10CT.', $limpa);
    }
}
