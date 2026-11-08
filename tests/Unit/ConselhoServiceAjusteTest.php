<?php

namespace Tests\Unit;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\ConselhoTrimestre;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\Turma;
use App\Models\User;
use App\Services\ConselhoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ConselhoServiceAjusteTest extends TestCase
{
    use RefreshDatabase;

    private ConselhoTrimestre $conselho;

    private Nota $notaMac;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $anoLectivoId = AnoLectivo::create([
            'nome' => '2025-2026',
            'estado' => AnoLectivo::ESTADO_ACTUAL,
        ])->id;

        Configuracao::set('ano_lectivo_actual', '2025-2026');

        $curso = Curso::create(['nome' => 'Curso Teste', 'codigo' => 'CT', 'activo' => true]);
        $classe = Classe::firstOrCreate(['codigo' => 'T10'], ['nome' => '10ª Classe', 'activo' => true]);
        $disciplina = Disciplina::create(['nome' => 'Matemática', 'codigo' => 'MAT', 'tipo' => 'geral', 'activo' => true]);
        $turma = Turma::create([
            'codigo' => 'T10A',
            'nome' => 'T10A',
            'curso_id' => $curso->id,
            'classe_id' => $classe->id,
            'ano_lectivo_id' => $anoLectivoId,
            'ano_lectivo' => '2025-2026',
            'activo' => true,
        ]);
        $aluno = Aluno::create([
            'numero_processo' => '2025001',
            'nome' => 'Aluno Teste',
            'numero_ordem' => 1,
            'turma_id' => $turma->id,
            'activo' => true,
        ]);

        $this->director = User::factory()->create(['estado' => 'activo']);
        $this->director->assignRole('director_turma');

        $this->conselho = ConselhoTrimestre::create([
            'turma_id' => $turma->id,
            'trimestre' => 2,
            'ano_lectivo_id' => $anoLectivoId,
            'ano_lectivo' => '2025-2026',
            'estado' => ConselhoTrimestre::ESTADO_EM_CURSO,
            'data_inicio' => now(),
            'iniciado_por' => $this->director->id,
        ]);

        $this->notaMac = Nota::create([
            'aluno_id' => $aluno->id,
            'disciplina_id' => $disciplina->id,
            'turma_id' => $turma->id,
            'professor_id' => $this->director->id,
            'tipo' => Nota::TIPO_MAC,
            'valor' => 11,
            'ano_lectivo' => '2025-2026',
            'ano_lectivo_id' => $anoLectivoId,
            'trimestre' => 2,
            'estado_conselho' => Nota::ESTADO_PRE_CONSELHO,
        ]);
    }

    public function test_servico_ajusta_nota_para_cima(): void
    {
        $service = app(ConselhoService::class);

        $resultado = $service->ajustarNota(
            $this->conselho,
            $this->notaMac,
            13.0,
            'Melhoria comportamental e assiduidade reconhecida pelo conselho.',
            $this->director,
        );

        $this->assertSame(13.0, (float) $resultado['nota_pos']->valor);
        $this->assertNull($resultado['ajuste']->motivo_reducao);
    }

    public function test_servico_rejeita_reducao_punitiva(): void
    {
        $service = app(ConselhoService::class);

        $this->expectException(InvalidArgumentException::class);

        $service->ajustarNota(
            $this->conselho,
            $this->notaMac,
            9.0,
            'Queremos baixar sem fundamento legal.',
            $this->director,
        );
    }
}
