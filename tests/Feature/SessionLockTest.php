<?php

namespace Tests\Feature;

use App\Models\Configuracao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SessionLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_desbloquear_com_palavra_passe_correcta(): void
    {
        $user = User::factory()->create([
            'estado' => 'activo',
            'password' => bcrypt('segredo123'),
        ]);

        $this->actingAs($user)
            ->postJson(route('sessao.desbloquear'), ['password' => 'segredo123'])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_aluno_pode_desbloquear_sessao_no_portal(): void
    {
        Role::firstOrCreate(['name' => 'aluno', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'estado' => 'activo',
            'password' => bcrypt('segredo123'),
        ]);
        $user->assignRole('aluno');

        $this->actingAs($user)
            ->postJson(route('sessao.desbloquear'), ['password' => 'segredo123'])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_encarregado_pode_desbloquear_sessao_no_portal(): void
    {
        Role::firstOrCreate(['name' => 'encarregado', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'estado' => 'activo',
            'password' => bcrypt('segredo123'),
        ]);
        $user->assignRole('encarregado');

        $this->actingAs($user)
            ->postJson(route('sessao.desbloquear'), ['password' => 'segredo123'])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_desbloquear_rejeita_palavra_passe_incorrecta(): void
    {
        $user = User::factory()->create([
            'estado' => 'activo',
            'password' => bcrypt('segredo123'),
        ]);

        $this->actingAs($user)
            ->postJson(route('sessao.desbloquear'), ['password' => 'errada'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_desbloquear_requer_autenticacao(): void
    {
        $this->postJson(route('sessao.desbloquear'), ['password' => 'qualquer'])
            ->assertUnauthorized();
    }

    public function test_desbloquear_requer_palavra_passe(): void
    {
        $user = User::factory()->create(['estado' => 'activo']);

        $this->actingAs($user)
            ->postJson(route('sessao.desbloquear'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_bloquear_marca_sessao_no_servidor(): void
    {
        Configuracao::set('bloqueio_inactividade_activo', '1', 'test');
        Configuracao::set('bloqueio_inactividade_minutos', '30', 'test');

        $user = User::factory()->create(['estado' => 'activo']);

        $this->actingAs($user)
            ->withSession([
                'facilita_ultima_actividade' => now()->subMinutes(31)->timestamp,
            ])
            ->postJson(route('sessao.bloquear'))
            ->assertOk()
            ->assertJson(['locked' => true]);

        $this->assertTrue(session('facilita_sessao_bloqueada'));
    }

    public function test_bloquear_recusado_quando_ha_actividade_recente(): void
    {
        Configuracao::set('bloqueio_inactividade_activo', '1', 'test');
        Configuracao::set('bloqueio_inactividade_minutos', '30', 'test');

        $user = User::factory()->create(['estado' => 'activo']);

        $this->actingAs($user)
            ->withSession([
                'facilita_ultima_actividade' => now()->subMinutes(5)->timestamp,
            ])
            ->postJson(route('sessao.bloquear'))
            ->assertOk()
            ->assertJson(['locked' => false]);

        $this->assertFalse((bool) session('facilita_sessao_bloqueada'));
    }

    public function test_navegacao_actualiza_ultima_actividade(): void
    {
        Configuracao::set('bloqueio_inactividade_activo', '1', 'test');

        $user = User::factory()->create(['estado' => 'activo']);

        $this->actingAs($user)
            ->withSession([
                'facilita_ultima_actividade' => now()->subMinutes(20)->timestamp,
            ])
            ->get(route('profile.edit'))
            ->assertOk();

        $this->assertNotNull(session('facilita_ultima_actividade'));
        $this->assertGreaterThan(now()->subMinute()->timestamp, (int) session('facilita_ultima_actividade'));
        $this->assertFalse((bool) session('facilita_sessao_bloqueada'));
    }

    public function test_pedido_post_bloqueado_quando_sessao_travada(): void
    {
        Configuracao::set('bloqueio_inactividade_activo', '1', 'test');

        $user = User::factory()->create(['estado' => 'activo']);

        $this->actingAs($user)
            ->withSession(['facilita_sessao_bloqueada' => true])
            ->patchJson(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->assertStatus(423)
            ->assertJson(['locked' => true]);
    }
}
