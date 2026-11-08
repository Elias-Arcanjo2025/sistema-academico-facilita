<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Assistente\AssistantDataService;
use Tests\TestCase;

class AssistantDataServiceTest extends TestCase
{
    public function test_extrai_termos_de_busca_ignorando_stop_words(): void
    {
        $service = app(AssistantDataService::class);

        $termos = $service->extrairTermosBusca('Como está o aluno João Silva na turma?');

        $this->assertContains('joão', $termos);
        $this->assertContains('silva', $termos);
        $this->assertNotContains('como', $termos);
        $this->assertNotContains('aluno', $termos);
    }
}
