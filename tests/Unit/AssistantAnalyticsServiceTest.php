<?php

namespace Tests\Unit;

use App\Services\Assistente\AssistantAnalyticsService;
use Tests\TestCase;

class AssistantAnalyticsServiceTest extends TestCase
{
    public function test_extrair_trimestre_da_mensagem(): void
    {
        $svc = app(AssistantAnalyticsService::class);

        $this->assertSame(1, $svc->extrairTrimestre('Desempenho no 1º trimestre'));
        $this->assertSame(2, $svc->extrairTrimestre('Comparar T2 da turma TI10AT'));
        $this->assertSame(3, $svc->extrairTrimestre('Melhores alunos no terceiro trimestre'));
        $this->assertNull($svc->extrairTrimestre('Quantos alunos tem a escola?'));
        $this->assertSame(2, $svc->extrairTrimestre('melhor desempenho no segundo trimestre'));
    }

    public function test_extrair_limite_top(): void
    {
        $svc = app(AssistantAnalyticsService::class);

        $this->assertSame(10, $svc->extrairLimiteTop('Me dê uma lista de 10 melhores no segundo trimestre'));
        $this->assertSame(10, $svc->extrairLimiteTop('10 melhores alunos'));
        $this->assertSame(5, $svc->extrairLimiteTop('top 5 da escola'));
        $this->assertSame(15, $svc->extrairLimiteTop('lista dos 15 melhores'));
        $this->assertSame(10, $svc->extrairLimiteTop('melhor desempenho no geral'));
    }

    public function test_extrair_termo_curso(): void
    {
        $svc = app(AssistantAnalyticsService::class);

        $this->assertSame(
            'Gestão de Empresas',
            $svc->extrairTermoCurso('melhor desempenho no curso de Gestão de Empresas na 10ª classe')
        );
        $this->assertSame(
            'Informática',
            $svc->extrairTermoCurso('ranking no curso de Informática')
        );
    }
}
