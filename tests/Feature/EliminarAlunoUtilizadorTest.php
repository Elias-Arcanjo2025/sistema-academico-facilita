<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EliminarAlunoUtilizadorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin_full']);
        Role::firstOrCreate(['name' => 'directora_geral']);
        Role::firstOrCreate(['name' => 'aluno']);
        Role::firstOrCreate(['name' => 'professor']);
    }

    public function test_apenas_admin_full_pode_acceder_eliminacao_forcada(): void
    {
        $directora = User::factory()->create(['estado' => 'activo']);
        $directora->assignRole('directora_geral');

        $userAluno = User::factory()->create(['aluno_id' => null]);
        $userAluno->assignRole('aluno');

        $this->actingAs($directora)
            ->delete(route('users.eliminar-aluno', $userAluno), [
                'confirmar_eliminacao' => '1',
            ])
            ->assertForbidden();
    }

    public function test_admin_nao_pode_eliminar_funcionario(): void
    {
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole('admin_full');

        $professor = User::factory()->create(['estado' => 'activo']);
        $professor->assignRole('professor');

        $this->actingAs($admin)
            ->delete(route('users.eliminar-aluno', $professor), [
                'confirmar_eliminacao' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $professor->id]);
    }
}
