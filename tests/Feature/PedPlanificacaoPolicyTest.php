<?php

namespace Tests\Feature;

use App\Models\User;
use App\Policies\Pedagogico\PedPlanificacaoPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PedPlanificacaoPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'pedagogico.planificacao.criar']);
    }

    public function test_utilizador_sem_permissao_nao_pode_criar_planificacao(): void
    {
        $user = User::factory()->create();
        $policy = new PedPlanificacaoPolicy;

        $this->assertFalse($policy->create($user));
    }

    public function test_utilizador_com_permissao_pode_criar_planificacao(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('pedagogico.planificacao.criar');

        $policy = new PedPlanificacaoPolicy;

        $this->assertTrue($policy->create($user));
    }
}
