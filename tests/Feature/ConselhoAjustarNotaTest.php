<?php

namespace Tests\Feature;

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
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConselhoAjustarNotaTest extends TestCase
{
    use RefreshDatabase;

    private ConselhoTrimestre $conselho;

    private Nota $notaMac;

    private User $gestor;

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

        $this->gestor = User::factory()->create(['estado' => 'activo']);
        $this->gestor->assignRole('admin_full');

        $this->conselho = ConselhoTrimestre::create([
            'turma_id' => $turma->id,
            'trimestre' => 1,
            'ano_lectivo_id' => $anoLectivoId,
            'ano_lectivo' => '2025-2026',
            'estado' => ConselhoTrimestre::ESTADO_EM_CURSO,
            'data_inicio' => now(),
            'iniciado_por' => $this->gestor->id,
        ]);

        $this->notaMac = Nota::create([
            'aluno_id' => $aluno->id,
            'disciplina_id' => $disciplina->id,
            'turma_id' => $turma->id,
            'professor_id' => $this->gestor->id,
            'tipo' => Nota::TIPO_MAC,
            'valor' => 10,
            'ano_lectivo' => '2025-2026',
            'ano_lectivo_id' => $anoLectivoId,
            'trimestre' => 1,
            'estado_conselho' => Nota::ESTADO_PRE_CONSELHO,
        ]);
    }

    public function test_ajustar_nota_com_sucesso_redirecciona_com_mensagem(): void
    {
        $response = $this->actingAs($this->gestor)->post(route('conselhos.ajustar', $this->conselho), [
            'nota_id' => $this->notaMac->id,
            'novo_valor' => '12',
            'justificacao' => 'Benefício pedagógico acordado em conselho de classe.',
        ]);

        $response->assertRedirect(route('conselhos.show', $this->conselho));
        $response->assertSessionHas('success');

        $this->assertTrue($this->notaMac->fresh()->foiAjustada());
    }

    public function test_ajustar_com_mesmo_valor_mostra_erro_de_validacao(): void
    {
        $response = $this->actingAs($this->gestor)->post(route('conselhos.ajustar', $this->conselho), [
            'nota_id' => $this->notaMac->id,
            'novo_valor' => '10',
            'justificacao' => 'Justificacao com mais de dez caracteres para teste.',
        ]);

        $response->assertRedirect(route('conselhos.show', $this->conselho));
        $response->assertSessionHasErrors('novo_valor');
        $this->assertFalse($this->notaMac->fresh()->foiAjustada());
    }
}
