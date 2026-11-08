<?php

namespace Tests\Unit;

use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\Turma;
use App\Models\User;
use App\Services\TurmaEscopoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CoordenadorBoletimAcessoTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordenador_curso_acede_turma_do_seu_curso(): void
    {
        Role::firstOrCreate(['name' => 'coordenador_curso']);

        $ano = AnoLectivo::create(['nome' => '2025-2026', 'estado' => AnoLectivo::ESTADO_ACTUAL]);
        Configuracao::set('ano_lectivo_actual', '2025-2026');

        $curso = Curso::create(['nome' => 'Curso', 'codigo' => 'CX'.uniqid(), 'activo' => true]);
        $classe = Classe::firstOrCreate(['codigo' => 'C10'], ['nome' => '10ª', 'activo' => true]);
        $turma = Turma::create([
            'codigo' => 'TX'.uniqid(),
            'nome' => 'TX',
            'curso_id' => $curso->id,
            'classe_id' => $classe->id,
            'ano_lectivo_id' => $ano->id,
            'activo' => true,
        ]);

        $user = User::factory()->create();
        $user->assignRole('coordenador_curso');
        $user->coordenadorCursos()->create([
            'curso_id' => $curso->id,
            'ano_lectivo_id' => $ano->id,
            'ano_lectivo' => $ano->nome,
            'activo' => true,
        ]);

        $this->assertTrue(app(TurmaEscopoService::class)->podeAcederTurma($user, $turma->id));
    }

    public function test_professor_coordenador_ve_turmas_do_curso_alem_das_que_lecciona(): void
    {
        Role::firstOrCreate(['name' => 'coordenador_curso']);
        Role::firstOrCreate(['name' => 'professor']);

        $ano = AnoLectivo::create(['nome' => '2025-2026', 'estado' => AnoLectivo::ESTADO_ACTUAL]);
        Configuracao::set('ano_lectivo_actual', '2025-2026');

        $curso = Curso::create(['nome' => 'TI', 'codigo' => 'TI'.uniqid(), 'activo' => true]);
        $classe = Classe::firstOrCreate(['codigo' => 'C12'], ['nome' => '12ª', 'activo' => true]);

        $turmaLecciona = Turma::create([
            'codigo' => 'TL'.uniqid(),
            'nome' => 'Turma Lecciona',
            'curso_id' => $curso->id,
            'classe_id' => $classe->id,
            'ano_lectivo_id' => $ano->id,
            'activo' => true,
        ]);
        $turmaCurso = Turma::create([
            'codigo' => 'TC'.uniqid(),
            'nome' => 'Turma Curso',
            'curso_id' => $curso->id,
            'classe_id' => $classe->id,
            'ano_lectivo_id' => $ano->id,
            'activo' => true,
        ]);

        $disciplina = \App\Models\Disciplina::create([
            'nome' => 'Prog',
            'codigo' => 'PG'.uniqid(),
            'activo' => true,
        ]);

        $user = User::factory()->create();
        $user->assignRole(['professor', 'coordenador_curso']);
        $user->coordenadorCursos()->create([
            'curso_id' => $curso->id,
            'ano_lectivo_id' => $ano->id,
            'ano_lectivo' => $ano->nome,
            'activo' => true,
        ]);

        \App\Models\ProfessorDisciplinaTurma::create([
            'user_id' => $user->id,
            'disciplina_id' => $disciplina->id,
            'turma_id' => $turmaLecciona->id,
            'ano_lectivo_id' => $ano->id,
            'ano_lectivo' => $ano->nome,
            'activo' => true,
        ]);

        $ids = app(TurmaEscopoService::class)->turmaIdsConsultaveis($user, $ano->id);

        $this->assertTrue($ids->contains($turmaLecciona->id));
        $this->assertTrue($ids->contains($turmaCurso->id));
    }
}
