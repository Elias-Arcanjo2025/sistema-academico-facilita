<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class RecuperacaoRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_recuperacoes_lancar_resolve_turma_por_id_numerico(): void
    {
        Role::firstOrCreate(['name' => 'admin_full']);
        $scenario = (new TransicaoAnoScenarioBuilder)->build();
        $turma = $scenario->turmas->origem_10;

        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole('admin_full');

        $this->actingAs($admin)
            ->get(route('recuperacoes.lancar', [
                'turma' => $turma->id,
                'ano_lectivo' => $scenario->anos->origem->nome,
            ]))
            ->assertOk()
            ->assertSee('Recuperação', false);
    }

    public function test_recuperacoes_lancar_resolve_turma_por_public_uuid(): void
    {
        Role::firstOrCreate(['name' => 'admin_full']);
        $scenario = (new TransicaoAnoScenarioBuilder)->build();
        $turma = $scenario->turmas->origem_10;

        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole('admin_full');

        $this->actingAs($admin)
            ->get(route('recuperacoes.lancar', [
                'turma' => $turma->public_uuid,
                'ano_lectivo' => $scenario->anos->origem->nome,
            ]))
            ->assertOk();
    }
}
