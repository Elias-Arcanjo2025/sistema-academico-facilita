<?php

namespace Tests\Feature;

use App\Models\Configuracao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class RegrasAvaliacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_utilizador_autenticado_acede_regras_avaliacao(): void
    {
        $user = User::factory()->create(['estado' => 'activo']);

        $this->actingAs($user)
            ->get(route('regras-avaliacao'))
            ->assertOk()
            ->assertSee('Regras de Avaliação', false)
            ->assertSee('Fórmulas de médias', false);
    }

    public function test_aluno_acede_regras_com_layout_portal(): void
    {
        Role::firstOrCreate(['name' => 'aluno']);
        $scenario = (new TransicaoAnoScenarioBuilder)->build();
        Configuracao::set('ano_lectivo_actual', $scenario->anos->origem->nome, 'test');

        $alunoUser = User::factory()->create([
            'estado' => 'activo',
            'aluno_id' => $scenario->alunos->aprovado->id,
        ]);
        $alunoUser->assignRole('aluno');

        $this->actingAs($alunoUser)
            ->get(route('regras-avaliacao'))
            ->assertOk()
            ->assertSee('Transição de classe', false)
            ->assertSee('Exame de Recurso', false);
    }
}
