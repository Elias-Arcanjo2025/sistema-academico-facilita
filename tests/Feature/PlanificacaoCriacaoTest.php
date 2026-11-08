<?php

namespace Tests\Feature;

use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\User;
use App\Services\Pedagogico\PlanificacaoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanificacaoCriacaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_cria_planificacao_usando_professor_associado(): void
    {
        [$admin, $professor, $turma, $disciplina, $anoId] = $this->cenarioComAssociacao();

        Configuracao::set('lancamento_t1_inicio', '2025-09-01', 'test');
        Configuracao::set('lancamento_t1_fim', '2025-12-31', 'test');

        $planificacao = app(PlanificacaoService::class)->criar($admin, [
            'trimestre' => 1,
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'tipo_periodo' => 'quinzenal',
            'ano_lectivo_id' => $anoId,
        ]);

        $this->assertSame($professor->id, $planificacao->user_id);
        $this->assertSame($turma->id, $planificacao->turma_id);
        $this->assertSame($disciplina->id, $planificacao->disciplina_id);
    }

    public function test_admin_sem_associacao_recebe_erro_claro(): void
    {
        $ano = AnoLectivo::create([
            'nome' => '2025-2026',
            'estado' => AnoLectivo::ESTADO_ACTUAL,
        ]);
        Configuracao::set('ano_lectivo_actual', '2025-2026');
        Configuracao::set('lancamento_t1_inicio', '2025-09-01', 'test');
        Configuracao::set('lancamento_t1_fim', '2025-12-31', 'test');

        $curso = Curso::create(['nome' => 'Curso Teste', 'codigo' => 'CT2', 'activo' => true]);
        $classe = Classe::firstOrCreate(['codigo' => 'T11'], ['nome' => '11ª Classe', 'activo' => true]);
        $disciplina = Disciplina::create(['nome' => 'Física', 'codigo' => 'FIS', 'tipo' => 'geral', 'activo' => true]);
        $turma = Turma::create([
            'codigo' => 'T11A',
            'nome' => 'T11A',
            'curso_id' => $curso->id,
            'classe_id' => $classe->id,
            'ano_lectivo_id' => $ano->id,
            'ano_lectivo' => '2025-2026',
            'activo' => true,
        ]);

        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole('admin_full');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Não existe professor com associação activa');

        app(PlanificacaoService::class)->criar($admin, [
            'trimestre' => 1,
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'ano_lectivo_id' => $ano->id,
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: Turma, 3: Disciplina, 4: int}
     */
    private function cenarioComAssociacao(): array
    {
        $ano = AnoLectivo::create([
            'nome' => '2025-2026',
            'estado' => AnoLectivo::ESTADO_ACTUAL,
        ]);
        Configuracao::set('ano_lectivo_actual', '2025-2026');

        $curso = Curso::create(['nome' => 'Curso Teste', 'codigo' => 'CT', 'activo' => true]);
        $classe = Classe::firstOrCreate(['codigo' => 'T10'], ['nome' => '10ª Classe', 'activo' => true]);
        $disciplina = Disciplina::create(['nome' => 'Matemática', 'codigo' => 'MAT', 'tipo' => 'geral', 'activo' => true]);
        $turma = Turma::create([
            'codigo' => 'T10A',
            'nome' => 'T10A',
            'curso_id' => $curso->id,
            'classe_id' => $classe->id,
            'ano_lectivo_id' => $ano->id,
            'ano_lectivo' => '2025-2026',
            'activo' => true,
        ]);

        $professor = User::factory()->create(['estado' => 'activo']);
        $professor->assignRole('professor');

        ProfessorDisciplinaTurma::create([
            'user_id' => $professor->id,
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'ano_lectivo_id' => $ano->id,
            'ano_lectivo' => '2025-2026',
            'activo' => true,
        ]);

        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole('admin_full');

        return [$admin, $professor, $turma, $disciplina, $ano->id];
    }
}
