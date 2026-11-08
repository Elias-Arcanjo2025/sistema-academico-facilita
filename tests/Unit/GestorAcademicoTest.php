<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\NotaPolicy;
use App\Services\Assistente\AssistantAccess;
use App\Support\CargosInstitucionais;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GestorAcademicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['notas.ver', 'notas.lancar', 'notas.editar', 'notas.eliminar', 'assistente.usar'] as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        Role::firstOrCreate(['name' => CargosInstitucionais::GESTOR_ACADEMICO])
            ->syncPermissions([
                'notas.ver', 'notas.lancar', 'notas.editar', 'notas.eliminar', 'assistente.usar',
            ]);
    }

    public function test_gestor_tem_controlo_academico_institucional(): void
    {
        $user = User::factory()->create();
        $user->assignRole(CargosInstitucionais::GESTOR_ACADEMICO);

        $this->assertTrue($user->temControloAcademicoInstitucional());
        $this->assertTrue($user->temVisaoInstitucional());
        $this->assertTrue($user->podeIgnorarTrimestreFechado());
        $this->assertFalse($user->hasRole('admin_full'));
    }

    public function test_gestor_pode_lancar_em_qualquer_turma(): void
    {
        $user = User::factory()->create();
        $user->assignRole(CargosInstitucionais::GESTOR_ACADEMICO);

        $policy = new NotaPolicy();

        $this->assertTrue($policy->lancar($user, 1, 99));
    }

    public function test_gestor_tem_perfil_assistente_dedicado(): void
    {
        $user = User::factory()->create();
        $user->assignRole(CargosInstitucionais::GESTOR_ACADEMICO);
        $user->givePermissionTo('assistente.usar');

        $access = app(AssistantAccess::class);

        $this->assertSame(AssistantAccess::PII_COMPLETO, $access->nivelPii($user));
        $regras = $access->regrasResposta($user);
        $this->assertSame('gestor_academico', $regras['perfil']);
        $this->assertStringContainsString('utilizadores', mb_strtolower(implode(' ', $regras['nao_pode'])));
    }
}
