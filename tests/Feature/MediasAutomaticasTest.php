<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\Turma;
use App\Models\User;
use App\Services\NotaService;
use App\Services\PautaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediasAutomaticasTest extends TestCase
{
    use RefreshDatabase;

    private AnoLectivo $ano;

    private Turma $turma;

    private Disciplina $disciplina;

    private Aluno $aluno;

    private User $professor;

    private NotaService $notaService;

    private PautaService $pautaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->ano = AnoLectivo::create(['nome' => '2025-2026', 'estado' => AnoLectivo::ESTADO_ACTUAL]);
        Configuracao::set('ano_lectivo_actual', '2025-2026');

        $curso = Curso::create([
            'nome' => 'TI',
            'codigo' => 'TI'.uniqid(),
            'activo' => true,
            'regime' => Curso::REGIME_POLITECNICO,
        ]);
        $classe = Classe::firstOrCreate(['codigo' => '12'], ['nome' => '12ª', 'activo' => true]);
        $this->turma = Turma::create([
            'codigo' => 'T12'.uniqid(),
            'nome' => 'T12',
            'curso_id' => $curso->id,
            'classe_id' => $classe->id,
            'ano_lectivo_id' => $this->ano->id,
            'ano_lectivo' => '2025-2026',
            'activo' => true,
        ]);
        $this->disciplina = Disciplina::create([
            'nome' => 'Matemática',
            'codigo' => 'MAT'.uniqid(),
            'activo' => true,
        ]);
        $this->aluno = Aluno::create([
            'numero_processo' => 'MA-1',
            'nome' => 'Admilson Teste',
            'turma_id' => $this->turma->id,
            'activo' => true,
            'situacao_academica' => Aluno::SITUACAO_MATRICULADO,
        ]);
        $this->professor = User::factory()->create(['estado' => 'activo']);
        $this->professor->assignRole('professor');

        $this->notaService = app(NotaService::class);
        $this->pautaService = app(PautaService::class);
    }

    public function test_mt3_so_aparece_com_mac_e_pp_completos(): void
    {
        $this->notaService->lancar([
            'aluno_id' => $this->aluno->id,
            'disciplina_id' => $this->disciplina->id,
            'turma_id' => $this->turma->id,
            'tipo' => Nota::TIPO_MAC,
            'valor' => 11,
            'trimestre' => 3,
            'ano_lectivo' => '2025-2026',
            'ano_lectivo_id' => $this->ano->id,
        ], $this->professor);

        $this->assertNull(
            $this->pautaService->calcularMediaTrimestral(
                $this->aluno->id,
                $this->disciplina->id,
                $this->turma->id,
                3,
                '2025-2026'
            )
        );

        $this->assertDatabaseMissing('notas', [
            'aluno_id' => $this->aluno->id,
            'disciplina_id' => $this->disciplina->id,
            'tipo' => Nota::TIPO_MT,
            'trimestre' => 3,
            'archived_at' => null,
        ]);

        $this->notaService->lancar([
            'aluno_id' => $this->aluno->id,
            'disciplina_id' => $this->disciplina->id,
            'turma_id' => $this->turma->id,
            'tipo' => Nota::TIPO_PP,
            'valor' => 10,
            'trimestre' => 3,
            'ano_lectivo' => '2025-2026',
            'ano_lectivo_id' => $this->ano->id,
        ], $this->professor);

        $this->assertSame(11.0, $this->pautaService->calcularMediaTrimestral(
            $this->aluno->id,
            $this->disciplina->id,
            $this->turma->id,
            3,
            '2025-2026'
        ));

        $this->assertDatabaseHas('notas', [
            'aluno_id' => $this->aluno->id,
            'disciplina_id' => $this->disciplina->id,
            'tipo' => Nota::TIPO_MT,
            'trimestre' => 3,
            'valor' => 11,
        ]);
    }

    public function test_lancar_pg_recalcula_ca_automaticamente(): void
    {
        // MT1 e MT2 para MFT2
        foreach ([1 => [12, 12, 12], 2 => [10, 10, 12]] as $tri => [$mac, $pp, $pt]) {
            foreach ([
                Nota::TIPO_MAC => $mac,
                Nota::TIPO_PP => $pp,
                Nota::TIPO_PT => $pt,
            ] as $tipo => $valor) {
                $this->notaService->lancar([
                    'aluno_id' => $this->aluno->id,
                    'disciplina_id' => $this->disciplina->id,
                    'turma_id' => $this->turma->id,
                    'tipo' => $tipo,
                    'valor' => $valor,
                    'trimestre' => $tri,
                    'ano_lectivo' => '2025-2026',
                    'ano_lectivo_id' => $this->ano->id,
                ], $this->professor);
            }
        }

        $this->notaService->lancar([
            'aluno_id' => $this->aluno->id,
            'disciplina_id' => $this->disciplina->id,
            'turma_id' => $this->turma->id,
            'tipo' => Nota::TIPO_MAC,
            'valor' => 11,
            'trimestre' => 3,
            'ano_lectivo' => '2025-2026',
            'ano_lectivo_id' => $this->ano->id,
        ], $this->professor);
        $this->notaService->lancar([
            'aluno_id' => $this->aluno->id,
            'disciplina_id' => $this->disciplina->id,
            'turma_id' => $this->turma->id,
            'tipo' => Nota::TIPO_PP,
            'valor' => 10,
            'trimestre' => 3,
            'ano_lectivo' => '2025-2026',
            'ano_lectivo_id' => $this->ano->id,
        ], $this->professor);

        $this->notaService->lancar([
            'aluno_id' => $this->aluno->id,
            'disciplina_id' => $this->disciplina->id,
            'turma_id' => $this->turma->id,
            'tipo' => Nota::TIPO_PG,
            'valor' => 10,
            'trimestre' => null,
            'ano_lectivo' => '2025-2026',
            'ano_lectivo_id' => $this->ano->id,
        ], $this->professor);

        // MT1=12, MT2=ROUND((10+10+12)/3)=11, MFT2=ROUND((12+11)/2)=12
        // MT3=ROUND((11+10)/2)=11, CF=ROUND((12+11)/2)=12
        // CA = ROUND(0.6*12 + 0.4*10) = ROUND(11.2) = 11
        $this->assertDatabaseHas('notas', [
            'aluno_id' => $this->aluno->id,
            'disciplina_id' => $this->disciplina->id,
            'tipo' => Nota::TIPO_CF,
            'valor' => 12,
        ]);
        $this->assertDatabaseHas('notas', [
            'aluno_id' => $this->aluno->id,
            'disciplina_id' => $this->disciplina->id,
            'tipo' => Nota::TIPO_CA12,
            'valor' => 11,
        ]);
    }
}
