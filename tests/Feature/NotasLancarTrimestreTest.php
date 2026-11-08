<?php

namespace Tests\Feature;

use App\Models\Configuracao;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class NotasLancarTrimestreTest extends TestCase
{
    use RefreshDatabase;

    public function test_lancar_notas_preseleciona_trimestre_actual(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Configuracao::set('trimestre_actual', '3', 'test');

        $scenario = (new TransicaoAnoScenarioBuilder)->build();
        $professor = User::factory()->create(['estado' => 'activo']);
        $professor->assignRole('professor');

        \App\Models\ProfessorDisciplinaTurma::where('turma_id', $scenario->turmas->origem_10->id)
            ->update(['user_id' => $professor->id]);

        $this->actingAs($professor)
            ->get(route('notas.lancar', ['ano_lectivo_id' => $scenario->anos->origem->id]))
            ->assertOk()
            ->assertSee('value="3"', false)
            ->assertSee('3.º Trimestre · Actual', false);
    }
}
