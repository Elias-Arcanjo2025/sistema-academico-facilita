<?php

namespace Tests\Unit;

use App\Imports\AlunosImport;
use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Curso;
use App\Models\Turma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlunosImportValidacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_permite_mesmo_numero_processo_em_anos_lectivos_diferentes(): void
    {
        [$turma2024, $turma2025] = $this->criarTurmasEmDoisAnos('PROC-001');

        $import = new AlunosImport($turma2025->id);
        $failMessage = null;

        $this->invocarValidacaoProcesso($import, 'PROC-001', $turma2025->id, function (string $msg) use (&$failMessage) {
            $failMessage = $msg;
        });

        $this->assertNull($failMessage);
        $this->assertDatabaseHas('alunos', [
            'numero_processo' => 'PROC-001',
            'turma_id' => $turma2024->id,
        ]);
    }

    public function test_rejeita_numero_processo_duplicado_no_mesmo_ano_lectivo(): void
    {
        [$turma2024] = $this->criarTurmasEmDoisAnos('PROC-002');
        $outraTurma2024 = Turma::create([
            'codigo' => 'T24-B',
            'nome' => '10A-B',
            'curso_id' => $turma2024->curso_id,
            'classe_id' => $turma2024->classe_id,
            'ano_lectivo_id' => $turma2024->ano_lectivo_id,
            'ano_lectivo' => $turma2024->ano_lectivo,
            'activo' => true,
        ]);

        $import = new AlunosImport($outraTurma2024->id);
        $failMessage = null;

        $this->invocarValidacaoProcesso($import, 'PROC-002', $outraTurma2024->id, function (string $msg) use (&$failMessage) {
            $failMessage = $msg;
        });

        $this->assertSame('O número de processo PROC-002 já está cadastrado neste ano lectivo.', $failMessage);
    }

    /**
     * @return array{0: Turma, 1: Turma}
     */
    private function criarTurmasEmDoisAnos(string $numeroProcesso): array
    {
        $ano2024 = AnoLectivo::create([
            'nome' => '2024-2025',
            'estado' => AnoLectivo::ESTADO_ANTERIOR,
            'data_inicio' => '2024-09-01',
            'data_fim' => '2025-07-31',
        ]);
        $ano2025 = AnoLectivo::create([
            'nome' => '2025-2026',
            'estado' => AnoLectivo::ESTADO_ACTUAL,
            'data_inicio' => '2025-09-01',
            'data_fim' => '2026-07-31',
        ]);

        $curso = Curso::create([
            'codigo' => 'INF',
            'nome' => 'Informática',
            'activo' => true,
        ]);

        $classe = Classe::where('codigo', '10')->firstOrFail();

        $turma2024 = Turma::create([
            'codigo' => 'T24-A',
            'nome' => '10A-A',
            'curso_id' => $curso->id,
            'classe_id' => $classe->id,
            'ano_lectivo_id' => $ano2024->id,
            'ano_lectivo' => $ano2024->nome,
            'activo' => true,
        ]);

        $turma2025 = Turma::create([
            'codigo' => 'T25-A',
            'nome' => '11A-A',
            'curso_id' => $curso->id,
            'classe_id' => $classe->id,
            'ano_lectivo_id' => $ano2025->id,
            'ano_lectivo' => $ano2025->nome,
            'activo' => true,
        ]);

        Aluno::create([
            'numero_processo' => $numeroProcesso,
            'nome' => 'Aluno Teste',
            'turma_id' => $turma2024->id,
            'numero_ordem' => 1,
            'activo' => true,
        ]);

        return [$turma2024, $turma2025];
    }

    private function invocarValidacaoProcesso(AlunosImport $import, string $value, ?int $turmaId, callable $fail): void
    {
        $method = new \ReflectionMethod(AlunosImport::class, 'validarNumeroProcessoPorAnoLectivo');
        $method->setAccessible(true);
        $method->invoke($import, $value, $turmaId, $fail);
    }
}
