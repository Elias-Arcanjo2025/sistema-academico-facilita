<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Conformidade\SimuladorConselhoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class PautaCertificadaSimuladorTest extends TestCase
{
    use RefreshDatabase;

    public function test_analisar_mostra_select_disciplinas_e_simular_funciona(): void
    {
        $scenario = (new TransicaoAnoScenarioBuilder)->build();
        $turma = $scenario->turmas->origem_10;
        $disciplina = $scenario->disciplina;
        $this->assertNotNull($disciplina);

        $disciplina->cursos()->syncWithoutDetaching([$scenario->curso->ti->id]);
        $disciplina->classes()->syncWithoutDetaching([$scenario->classes->c10->id]);

        Role::findOrCreate('admin_full');
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole('admin_full');

        $this->actingAs($admin)
            ->post(route('pauta-certificada.analisar', $turma), ['trimestre' => 3])
            ->assertOk()
            ->assertSee('Simulador de conselho')
            ->assertSee($disciplina->nome)
            ->assertDontSee('Disciplina ID');

        $this->actingAs($admin)
            ->post(route('pauta-certificada.simular', $turma), [
                'trimestre' => 3,
                'disciplina_id' => $disciplina->id,
                'tipo' => 'MAC',
                'delta' => -1,
                'aluno_id' => '',
            ])
            ->assertOk()
            ->assertSee('Simulador');
    }

    public function test_servico_simular_turma_nao_rebenta(): void
    {
        $scenario = (new TransicaoAnoScenarioBuilder)->build();
        $turma = $scenario->turmas->origem_10;
        $disciplina = $scenario->disciplina;
        $disciplina->cursos()->syncWithoutDetaching([$scenario->curso->ti->id]);
        $disciplina->classes()->syncWithoutDetaching([$scenario->classes->c10->id]);

        $resultado = app(SimuladorConselhoService::class)->simularTurma(
            $turma,
            3,
            [['disciplina_id' => (int) $disciplina->id, 'tipo' => 'MAC', 'delta' => -1.0]]
        );

        $this->assertArrayHasKey('total_alunos', $resultado);
        $this->assertArrayHasKey('total_mudancas', $resultado);
        $this->assertIsInt($resultado['total_alunos']);
        $this->assertIsInt($resultado['total_mudancas']);
    }
}
