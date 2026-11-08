<?php

namespace Tests\Feature;

use App\Models\Encarregado;
use App\Models\User;
use App\Services\Conformidade\ConformidadePautaService;
use App\Services\Radar\RadarPedagogicoService;
use App\Services\WhatsApp\WhatsAppService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InovacaoModulosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['control.mode' => 'off', 'whatsapp.enabled' => true, 'whatsapp.provider' => 'log']);
    }

    public function test_radar_acessivel_para_admin(): void
    {
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole('admin_full');

        $this->actingAs($admin)
            ->get(route('radar.index'))
            ->assertOk()
            ->assertSee('Radar Pedagógico');
    }

    public function test_pauta_certificada_index(): void
    {
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole('admin_full');

        $this->actingAs($admin)
            ->get(route('pauta-certificada.index'))
            ->assertOk()
            ->assertSee('Pauta Certificada');
    }

    public function test_whatsapp_log_provider_grava_mensagem(): void
    {
        $service = app(WhatsAppService::class);
        $msg = $service->sendText('923000000', 'Olá teste Facilita');

        $this->assertSame('sent', $msg->status);
        $this->assertDatabaseHas('whatsapp_mensagens', [
            'telefone' => '244923000000',
            'direction' => 'out',
        ]);
    }

    public function test_portal_encarregado_dashboard(): void
    {
        $encarregado = Encarregado::query()->create([
            'nome' => 'Maria Encarregada',
            'telefone' => '923111222',
            'email' => 'maria.enc@test.ao',
            'whatsapp_opt_in' => true,
            'activo' => true,
        ]);

        $user = User::factory()->create([
            'estado' => 'activo',
            'encarregado_id' => $encarregado->id,
            'email' => 'maria.enc@test.ao',
        ]);
        $user->assignRole('encarregado');

        $this->actingAs($user)
            ->get(route('portal.encarregado.dashboard'))
            ->assertOk()
            ->assertSee('educandos');
    }

    public function test_radar_service_gera_estrutura(): void
    {
        $dados = app(RadarPedagogicoService::class)->gerar();
        $this->assertArrayHasKey('turmas_risco', $dados);
        $this->assertArrayHasKey('professores_atraso', $dados);
        $this->assertArrayHasKey('benchmark', $dados);
    }
}
