<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Services\SignedDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class SegurancaUrlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagina_inicial_nao_expoe_estatisticas(): void
    {
        (new TransicaoAnoScenarioBuilder)->build();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Alunos Activos', false);
    }

    public function test_boletim_assinado_permite_download_sem_autenticacao(): void
    {
        (new TransicaoAnoScenarioBuilder)->build();

        $aluno = Aluno::first();
        $url = app(SignedDocumentService::class)->urlBoletim($aluno);

        $this->get($url)->assertOk();
    }

    public function test_boletim_assinado_rejeita_url_sem_assinatura(): void
    {
        (new TransicaoAnoScenarioBuilder)->build();
        $aluno = Aluno::first();

        $this->get(route('documentos.boletim.signed', ['aluno' => $aluno->public_uuid]))
            ->assertForbidden();
    }

    public function test_route_binding_aluno_aceita_id_numerico_e_uuid(): void
    {
        (new TransicaoAnoScenarioBuilder)->build();
        $aluno = Aluno::first();
        $this->assertNotNull($aluno?->public_uuid);

        $porId = app('router')->getRoutes()->getByName('export.boletim.pdf');
        $this->assertNotNull($porId);

        $boundId = app(\Illuminate\Routing\Router::class)->getBindingCallback('aluno')
            ? (app(\Illuminate\Routing\Router::class)->getBindingCallback('aluno'))((string) $aluno->id)
            : null;
        $boundUuid = app(\Illuminate\Routing\Router::class)->getBindingCallback('aluno')
            ? (app(\Illuminate\Routing\Router::class)->getBindingCallback('aluno'))($aluno->public_uuid)
            : null;

        $this->assertSame($aluno->id, $boundId?->id);
        $this->assertSame($aluno->id, $boundUuid?->id);
    }
}
