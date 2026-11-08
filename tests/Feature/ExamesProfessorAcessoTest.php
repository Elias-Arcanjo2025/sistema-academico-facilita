<?php

namespace Tests\Feature;

use App\Models\ProfessorDisciplinaTurma;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class ExamesProfessorAcessoTest extends TestCase
{
    use RefreshDatabase;

    public function test_professor_acede_recuperacoes_e_lancamento_na_sua_turma(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $scenario = (new TransicaoAnoScenarioBuilder)->build();
        $turma = $scenario->turmas->origem_10;

        $professor = User::factory()->create(['estado' => 'activo']);
        $professor->assignRole('professor');

        ProfessorDisciplinaTurma::where('turma_id', $turma->id)->update([
            'user_id' => $professor->id,
        ]);

        $this->actingAs($professor)
            ->get(route('recuperacoes.index'))
            ->assertOk()
            ->assertSee('Recuperação', false);

        $this->actingAs($professor)
            ->get(route('recuperacoes.lancar', [
                'turma' => $turma,
                'ano_lectivo' => $scenario->anos->origem->nome,
            ]))
            ->assertOk()
            ->assertSee('Recuperação anual', false);
    }

    public function test_menu_lateral_exames_filtra_por_classe_do_professor(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $scenario = (new TransicaoAnoScenarioBuilder)->build();
        $turma10 = $scenario->turmas->origem_10;

        $professor10 = User::factory()->create(['estado' => 'activo']);
        $professor10->assignRole('professor');

        ProfessorDisciplinaTurma::where('turma_id', $turma10->id)->update([
            'user_id' => $professor10->id,
        ]);

        $this->actingAs($professor10)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Recuperação (10.ª/11.ª)', false)
            ->assertDontSee('Recurso (12.ª)', false);

        $classe12 = \App\Models\Classe::firstOrCreate(
            ['codigo' => '12'],
            ['nome' => '12ª Classe', 'activo' => true]
        );

        $turma12 = \App\Models\Turma::create([
            'codigo' => 'TI12AT-O',
            'nome' => 'TI12AT',
            'curso_id' => $scenario->curso->ti->id,
            'classe_id' => $classe12->id,
            'ano_lectivo' => $scenario->anos->origem->nome,
            'ano_lectivo_id' => $scenario->anos->origem->id,
            'activo' => true,
        ]);

        $professor12 = User::factory()->create(['estado' => 'activo']);
        $professor12->assignRole('professor');

        ProfessorDisciplinaTurma::create([
            'user_id' => $professor12->id,
            'disciplina_id' => $scenario->disciplina->id,
            'turma_id' => $turma12->id,
            'ano_lectivo' => $scenario->anos->origem->nome,
            'ano_lectivo_id' => $scenario->anos->origem->id,
            'activo' => true,
        ]);

        $this->actingAs($professor12)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Recurso (12.ª)', false)
            ->assertDontSee('Recuperação (10.ª/11.ª)', false);
    }

    public function test_professor_sem_pautas_ver_nao_acede_pautas(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $scenario = (new TransicaoAnoScenarioBuilder)->build();

        $professor = User::factory()->create(['estado' => 'activo']);
        $professor->assignRole('professor');

        $this->actingAs($professor)
            ->get(route('pautas.index'))
            ->assertForbidden();
    }
}
