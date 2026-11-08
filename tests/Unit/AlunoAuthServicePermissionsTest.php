<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AlunoAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlunoAuthServicePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_papel_aluno_recebe_permissao_assistente(): void
    {
        Role::firstOrCreate(['name' => 'aluno', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'portal.aluno.ver', 'guard_name' => 'web']);

        app(AlunoAuthService::class)->ensureAlunoRoleExists();

        $role = Role::findByName('aluno', 'web');
        $this->assertTrue($role->hasPermissionTo('assistente.usar'));
        $this->assertTrue($role->hasPermissionTo('portal.aluno.ver'));
    }

    public function test_utilizador_aluno_pode_usar_assistente_apos_sincronizar_papel(): void
    {
        app(AlunoAuthService::class)->ensureAlunoRoleExists();

        $user = User::factory()->create();
        $user->assignRole('aluno');

        $this->assertTrue($user->can('assistente.usar'));
    }
}
