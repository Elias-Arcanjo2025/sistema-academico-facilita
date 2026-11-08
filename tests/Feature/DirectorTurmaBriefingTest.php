<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\PresencaAula;
use App\Models\PresencaRegisto;
use App\Models\Turma;
use App\Models\User;
use App\Services\DirectorTurmaBriefingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectorTurmaBriefingTest extends TestCase
{
    use RefreshDatabase;

    public function test_briefing_lista_aluno_com_faltas(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $ano = AnoLectivo::create(['nome' => '2025-2026', 'estado' => AnoLectivo::ESTADO_ACTUAL]);
        Configuracao::set('ano_lectivo_actual', '2025-2026');

        $curso = Curso::create(['nome' => 'C', 'codigo' => 'C'.uniqid(), 'activo' => true]);
        $classe = Classe::firstOrCreate(['codigo' => 'E10'], ['nome' => '10ª', 'activo' => true]);
        $turma = Turma::create([
            'codigo' => 'TB'.uniqid(),
            'nome' => 'TB',
            'curso_id' => $curso->id,
            'classe_id' => $classe->id,
            'ano_lectivo_id' => $ano->id,
            'ano_lectivo' => '2025-2026',
            'activo' => true,
        ]);

        $director = User::factory()->create(['email' => 'dt.brief@exemplo.ao']);
        $director->assignRole('director_turma');
        $director->turmasComoDirector()->attach($turma->id, [
            'ano_lectivo' => '2025-2026',
            'ano_lectivo_id' => $ano->id,
            'activo' => true,
        ]);

        $disciplina = Disciplina::create([
            'nome' => 'Matemática',
            'codigo' => 'MAT'.uniqid(),
            'curso_id' => $curso->id,
            'activo' => true,
        ]);

        $aluno = Aluno::create([
            'numero_processo' => 'BRF-1',
            'nome' => 'Aluno Briefing',
            'turma_id' => $turma->id,
            'activo' => true,
            'situacao_academica' => Aluno::SITUACAO_MATRICULADO,
        ]);

        for ($i = 0; $i < 2; $i++) {
            $aula = PresencaAula::create([
                'turma_id' => $turma->id,
                'disciplina_id' => $disciplina->id,
                'professor_id' => $director->id,
                'ano_lectivo_id' => $ano->id,
                'data' => now()->subDays($i + 1)->toDateString(),
                'hora_inicio' => '08:00:00',
                'hora_fim' => '08:45:00',
                'trimestre' => 1,
                'registado_por' => $director->id,
            ]);
            PresencaRegisto::create([
                'presenca_aula_id' => $aula->id,
                'aluno_id' => $aluno->id,
                'estado' => PresencaRegisto::FALTA_INJUSTIFICADA,
            ]);
        }

        $briefing = app(DirectorTurmaBriefingService::class)->briefing($director);

        $this->assertGreaterThanOrEqual(1, $briefing['total']);
        $this->assertSame($aluno->id, $briefing['alertas'][0]['aluno_id']);
    }
}
