<?php

namespace Tests\Feature;

use App\Models\Control\ControlEscola;
use App\Models\Control\ControlLicenseState;
use App\Models\User;
use App\Services\Control\ControlLicenseService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ControlHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config([
            'control.mode' => 'hub',
            'control.api_prefix' => 'api/control/v1',
        ]);
    }

    public function test_heartbeat_actualiza_metricas_e_devolve_licenca(): void
    {
        $secret = 'secret-teste-1234567890';
        $escola = ControlEscola::query()->create([
            'installation_id' => (string) Str::uuid(),
            'nome' => 'Escola Teste',
            'secret_hash' => ControlEscola::hashSecret($secret),
            'status' => ControlEscola::STATUS_ACTIVO,
            'activo' => true,
        ]);

        $ts = (string) now()->timestamp;
        $sig = hash_hmac('sha256', $escola->installation_id.'|'.$ts, $secret);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$secret,
            'X-Facilita-Installation' => $escola->installation_id,
            'X-Facilita-Timestamp' => $ts,
            'X-Facilita-Signature' => $sig,
        ])->postJson('/api/control/v1/heartbeat', [
            'instituicao' => ['nome' => 'Escola Teste Actualizada'],
            'ano_lectivo' => '2025/2026',
            'metricas' => [
                'alunos' => 120,
                'professores' => 15,
                'cursos' => 4,
                'turmas' => 8,
                'salas' => 10,
                'disciplinas' => 20,
                'utilizadores' => 40,
            ],
        ])->assertOk()
            ->assertJson([
                'status' => 'activo',
                'blocked' => false,
            ]);

        $escola->refresh();
        $this->assertSame(120, $escola->total_alunos);
        $this->assertSame('Escola Teste Actualizada', $escola->nome);
        $this->assertNotNull($escola->last_heartbeat_at);
    }

    public function test_sync_alunos_e_bloqueio_reflecte_na_licenca(): void
    {
        $secret = 'secret-teste-abcdef';
        $escola = ControlEscola::query()->create([
            'installation_id' => (string) Str::uuid(),
            'nome' => 'Escola Sync',
            'secret_hash' => ControlEscola::hashSecret($secret),
            'status' => ControlEscola::STATUS_ACTIVO,
            'activo' => true,
        ]);

        $uuid = (string) Str::uuid();
        $ts = (string) now()->timestamp;
        $sig = hash_hmac('sha256', $escola->installation_id.'|'.$ts, $secret);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$secret,
            'X-Facilita-Installation' => $escola->installation_id,
            'X-Facilita-Timestamp' => $ts,
            'X-Facilita-Signature' => $sig,
        ])->postJson('/api/control/v1/alunos/sync', [
            'ano_lectivo' => '2025/2026',
            'alunos' => [
                [
                    'aluno_uuid' => $uuid,
                    'nome' => 'Aluno Um',
                    'numero_processo' => 'P001',
                    'ano_lectivo' => '2025/2026',
                    'activo' => true,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('synced', 1)
            ->assertJsonPath('total_alunos_activos', 1);

        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole('admin_full');

        $this->actingAs($admin)
            ->post(route('control.hub.escolas.bloquear', $escola), [
                'motivo' => 'Pagamento em falta',
            ])
            ->assertRedirect();

        $escola->refresh();
        $this->assertSame(ControlEscola::STATUS_BLOQUEADO, $escola->status);

        $ts2 = (string) now()->timestamp;
        $sig2 = hash_hmac('sha256', $escola->installation_id.'|'.$ts2, $secret);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$secret,
            'X-Facilita-Installation' => $escola->installation_id,
            'X-Facilita-Timestamp' => $ts2,
            'X-Facilita-Signature' => $sig2,
        ])->getJson('/api/control/v1/license')
            ->assertOk()
            ->assertJson([
                'status' => 'bloqueado',
                'blocked' => true,
            ]);
    }

    public function test_middleware_bloqueia_acesso_quando_licenca_suspensa(): void
    {
        config([
            'control.mode' => 'school',
            'control.bypass' => false,
            // Evita que maybeRefreshLicenseFromHub contacte o hub real do .env
            // e reescreva o estado de teste.
            'control.hub_url' => '',
            'control.installation_id' => '',
            'control.secret' => '',
        ]);

        ControlLicenseState::query()->delete();
        ControlLicenseState::query()->create([
            'status' => ControlLicenseState::STATUS_BLOQUEADO,
            'message' => 'Suspenso para teste',
            'checked_at' => now(),
            'blocked_at' => now(),
        ]);

        app(ControlLicenseService::class)->bustCache();

        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole('admin_full');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('control.license.blocked'));

        $this->get(route('control.license.blocked'))
            ->assertOk()
            ->assertSee('Suspenso para teste');
    }

    public function test_api_rejeita_secret_invalido(): void
    {
        $escola = ControlEscola::query()->create([
            'installation_id' => (string) Str::uuid(),
            'nome' => 'Escola Auth',
            'secret_hash' => ControlEscola::hashSecret('correct-secret'),
            'status' => ControlEscola::STATUS_ACTIVO,
            'activo' => true,
        ]);

        $ts = (string) now()->timestamp;
        $sig = hash_hmac('sha256', $escola->installation_id.'|'.$ts, 'wrong-secret');

        $this->withHeaders([
            'Authorization' => 'Bearer wrong-secret',
            'X-Facilita-Installation' => $escola->installation_id,
            'X-Facilita-Timestamp' => $ts,
            'X-Facilita-Signature' => $sig,
        ])->postJson('/api/control/v1/heartbeat', [])
            ->assertUnauthorized();
    }

    public function test_criar_cobranca_e_marcar_paga(): void
    {
        $escola = ControlEscola::query()->create([
            'installation_id' => (string) Str::uuid(),
            'nome' => 'Escola Facturação',
            'secret_hash' => ControlEscola::hashSecret('secret'),
            'status' => ControlEscola::STATUS_ACTIVO,
            'total_alunos' => 100,
            'preco_anual_aluno' => 1500,
            'ano_lectivo' => '2025/2026',
            'activo' => true,
        ]);

        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole('admin_full');

        $this->actingAs($admin)
            ->post(route('control.hub.escolas.cobrancas.store', $escola), [
                'ano_lectivo' => '2025/2026',
                'total_alunos' => 100,
                'preco_unitario' => 1500,
            ])
            ->assertRedirect();

        $escola->refresh();
        $this->assertSame(ControlEscola::STATUS_EM_COBRANCA, $escola->status);

        $cobranca = $escola->cobrancaAberta();
        $this->assertNotNull($cobranca);
        $this->assertEquals(150000, (float) $cobranca->valor_total);

        $secret = 'secret';
        $ts = (string) now()->timestamp;
        $sig = hash_hmac('sha256', $escola->installation_id.'|'.$ts, $secret);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$secret,
            'X-Facilita-Installation' => $escola->installation_id,
            'X-Facilita-Timestamp' => $ts,
            'X-Facilita-Signature' => $sig,
        ])->getJson('/api/control/v1/license')
            ->assertOk()
            ->assertJsonPath('status', 'em_cobranca')
            ->assertJsonPath('warning_level', 'warning')
            ->assertJsonPath('cobranca.referencia', $cobranca->referencia);

        $this->actingAs($admin)
            ->post(route('control.hub.cobrancas.pagar', $cobranca), [
                'metodo_pagamento' => 'Transferência',
            ])
            ->assertRedirect();

        $escola->refresh();
        $cobranca->refresh();
        $this->assertSame(ControlEscola::STATUS_ACTIVO, $escola->status);
        $this->assertSame(\App\Models\Control\ControlCobranca::STATUS_PAGA, $cobranca->status);
    }

    public function test_banner_aviso_quando_em_cobranca(): void
    {
        config(['control.mode' => 'school', 'control.bypass' => false]);

        ControlLicenseState::query()->create([
            'status' => ControlLicenseState::STATUS_EM_COBRANCA,
            'message' => 'Cobrança anual em curso — teste',
            'checked_at' => now(),
            'payload' => [
                'warning_level' => 'warning',
                'cobranca' => [
                    'referencia' => 'FC-TEST',
                    'valor_total' => 1000,
                ],
            ],
        ]);

        app(ControlLicenseService::class)->bustCache();

        $coordenador = User::factory()->create(['estado' => 'activo']);
        $coordenador->assignRole('coordenador_curso');

        $professor = User::factory()->create(['estado' => 'activo']);
        $professor->assignRole('professor');

        $banner = app(ControlLicenseService::class)->warningBanner($coordenador);
        $this->assertNotNull($banner);
        $this->assertSame('warning', $banner['level']);
        $this->assertStringContainsString('Cobrança anual', $banner['message']);

        $this->assertNull(app(ControlLicenseService::class)->warningBanner($professor));

        // Renderiza o layout (sem dashboard) para confirmar o banner no HTML
        $html = view('layouts.app', [
            'controlLicenseWarning' => $banner,
            'bloqueioInactividadeActivo' => false,
            'bloqueioInactividadeMinutos' => 15,
            'slot' => '',
        ])->render();

        $this->assertStringContainsString('Cobrança anual em curso', $html);
        $this->assertStringContainsString('FC-TEST', $html);
    }

    public function test_cobranca_com_bloqueio_zero_minutos_bloqueia_de_imediato(): void
    {
        $escola = ControlEscola::query()->create([
            'installation_id' => (string) Str::uuid(),
            'nome' => 'Escola Bloqueio Imediato',
            'secret_hash' => ControlEscola::hashSecret('secret'),
            'status' => ControlEscola::STATUS_ACTIVO,
            'total_alunos' => 10,
            'preco_anual_aluno' => 100,
            'ano_lectivo' => '2025/2026',
            'activo' => true,
        ]);

        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole('admin_full');

        $this->actingAs($admin)
            ->post(route('control.hub.escolas.cobrancas.store', $escola), [
                'ano_lectivo' => '2025/2026',
                'total_alunos' => 10,
                'preco_unitario' => 100,
                'minutos_ate_aviso' => 0,
                'minutos_ate_bloqueio' => 0,
            ])
            ->assertRedirect();

        $escola->refresh();
        $this->assertSame(ControlEscola::STATUS_BLOQUEADO, $escola->status);
    }

    public function test_license_aplica_bloqueio_quando_prazo_vence_sem_cron(): void
    {
        $secret = 'secret-prazos-123';
        $escola = ControlEscola::query()->create([
            'installation_id' => (string) Str::uuid(),
            'nome' => 'Escola Prazos',
            'secret_hash' => ControlEscola::hashSecret($secret),
            'status' => ControlEscola::STATUS_ACTIVO,
            'total_alunos' => 20,
            'preco_anual_aluno' => 50,
            'ano_lectivo' => '2025/2026',
            'activo' => true,
        ]);

        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole('admin_full');

        $this->actingAs($admin)
            ->post(route('control.hub.escolas.cobrancas.store', $escola), [
                'ano_lectivo' => '2025/2026',
                'total_alunos' => 20,
                'preco_unitario' => 50,
                'minutos_ate_aviso' => 0,
                'minutos_ate_atraso' => 2,
                'minutos_ate_bloqueio' => 5,
            ])
            ->assertRedirect();

        $escola->refresh();
        $this->assertSame(ControlEscola::STATUS_EM_COBRANCA, $escola->status);

        $cobranca = $escola->cobrancaAberta();
        $this->assertNotNull($cobranca);
        $this->assertNotNull($cobranca->bloqueio_em);
        $this->assertTrue($cobranca->bloqueio_em->isFuture());

        // Ainda antes do prazo → continua em cobrança
        $ts = (string) now()->timestamp;
        $sig = hash_hmac('sha256', $escola->installation_id.'|'.$ts, $secret);
        $this->withHeaders([
            'Authorization' => 'Bearer '.$secret,
            'X-Facilita-Installation' => $escola->installation_id,
            'X-Facilita-Timestamp' => $ts,
            'X-Facilita-Signature' => $sig,
        ])->getJson('/api/control/v1/license')
            ->assertOk()
            ->assertJson([
                'status' => 'em_cobranca',
                'blocked' => false,
            ]);

        // Após o prazo de bloqueio, a API aplica o estado sem depender do cron
        $this->travel(6)->minutes();

        $ts2 = (string) now()->timestamp;
        $sig2 = hash_hmac('sha256', $escola->installation_id.'|'.$ts2, $secret);
        $this->withHeaders([
            'Authorization' => 'Bearer '.$secret,
            'X-Facilita-Installation' => $escola->installation_id,
            'X-Facilita-Timestamp' => $ts2,
            'X-Facilita-Signature' => $sig2,
        ])->getJson('/api/control/v1/license')
            ->assertOk()
            ->assertJson([
                'status' => 'bloqueado',
                'blocked' => true,
            ]);

        $escola->refresh();
        $this->assertSame(ControlEscola::STATUS_BLOQUEADO, $escola->status);
    }
}