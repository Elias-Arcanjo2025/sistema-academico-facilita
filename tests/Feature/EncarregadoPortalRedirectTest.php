<?php

namespace Tests\Feature;

use App\Models\Encarregado;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EncarregadoPortalRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_encarregado_e_redireccionado_do_dashboard_staff_para_o_portal(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $encarregado = Encarregado::query()->create([
            'nome' => 'Pedro Quissanga',
            'telefone' => '900000001',
            'email' => 'pedro.enc@example.com',
            'activo' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Pedro Quissanga',
            'email' => 'pedro.enc@example.com',
            'encarregado_id' => $encarregado->id,
            'estado' => 'activo',
            'must_change_password' => false,
        ]);
        $user->syncRoles(['encarregado']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('portal.encarregado.dashboard'));
    }

    public function test_dashboard_encarregado_mostra_educandos_e_kpis(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $ano = \App\Models\AnoLectivo::create(['nome' => '2025-2026', 'estado' => \App\Models\AnoLectivo::ESTADO_ACTUAL]);
        \App\Models\Configuracao::set('ano_lectivo_actual', '2025-2026');
        $curso = \App\Models\Curso::create(['nome' => 'TI', 'codigo' => 'TI'.uniqid(), 'activo' => true]);
        $classe = \App\Models\Classe::firstOrCreate(['codigo' => 'C12'], ['nome' => '12ª', 'activo' => true]);
        $turma = \App\Models\Turma::create([
            'codigo' => 'TI12AM',
            'nome' => 'TI12AM',
            'curso_id' => $curso->id,
            'classe_id' => $classe->id,
            'ano_lectivo_id' => $ano->id,
            'activo' => true,
        ]);

        $encarregado = Encarregado::query()->create([
            'nome' => 'Pedro Quissanga',
            'telefone' => '900000010',
            'email' => 'pedro.dash@example.com',
            'activo' => true,
        ]);

        $aluno = \App\Models\Aluno::create([
            'numero_processo' => 'DASH-1',
            'nome' => 'Abel Eduardo Adelardo',
            'turma_id' => $turma->id,
            'activo' => true,
            'situacao_academica' => \App\Models\Aluno::SITUACAO_MATRICULADO,
        ]);
        $encarregado->alunos()->attach($aluno->id, [
            'parentesco' => 'pai',
            'principal' => true,
            'pode_ver_notas' => true,
            'pode_assinar_boletim' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Pedro Quissanga',
            'email' => 'pedro.dash@example.com',
            'encarregado_id' => $encarregado->id,
            'estado' => 'activo',
            'must_change_password' => false,
        ]);
        $user->syncRoles(['encarregado']);

        $this->actingAs($user)
            ->get(route('portal.encarregado.dashboard'))
            ->assertOk()
            ->assertSee('Os seus educandos', false)
            ->assertSee('Abel Eduardo Adelardo', false)
            ->assertSee('Ver dashboard', false);

        $this->actingAs($user)
            ->get(route('portal.encarregado.filho', $aluno))
            ->assertOk()
            ->assertSee('Todos os educandos', false)
            ->assertSee('Evolução trimestral', false)
            ->assertSee('Assiduidade', false);
    }

    public function test_portal_encarregado_inclui_bloqueio_por_inactividade(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        \App\Models\Configuracao::set('bloqueio_inactividade_activo', '1', 'test');
        \App\Models\Configuracao::set('bloqueio_inactividade_minutos', '30', 'test');

        $encarregado = Encarregado::query()->create([
            'nome' => 'Carlos Encarregado',
            'telefone' => '900000003',
            'email' => 'carlos.enc@example.com',
            'activo' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Carlos Encarregado',
            'email' => 'carlos.enc@example.com',
            'encarregado_id' => $encarregado->id,
            'estado' => 'activo',
            'must_change_password' => false,
        ]);
        $user->syncRoles(['encarregado']);

        $this->actingAs($user)
            ->get(route('portal.encarregado.dashboard'))
            ->assertOk()
            ->assertSee('facilitaBloqueioInactividade', false)
            ->assertSee('facilita-lock-overlay', false)
            ->assertSee('sweetalert2', false);
    }

    public function test_apos_alterar_senha_encarregado_vai_ao_portal(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $encarregado = Encarregado::query()->create([
            'nome' => 'Maria Encarregada',
            'telefone' => '900000002',
            'email' => 'maria.enc@example.com',
            'activo' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Maria Encarregada',
            'email' => 'maria.enc@example.com',
            'encarregado_id' => $encarregado->id,
            'estado' => 'activo',
            'must_change_password' => true,
        ]);
        $user->syncRoles(['encarregado']);

        $this->actingAs($user)
            ->put(route('password.force.update'), [
                'password' => 'NovaSenha123!',
                'password_confirmation' => 'NovaSenha123!',
            ])
            ->assertRedirect(route('portal.encarregado.dashboard'));
    }
}
