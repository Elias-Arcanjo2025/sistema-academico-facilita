<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\DocumentoPedido;
use App\Models\Encarregado;
use App\Models\Turma;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentoPedidoPortalTest extends TestCase
{
    use RefreshDatabase;

    private Aluno $aluno;

    private User $encarregadoUser;

    private Encarregado $encarregado;

    private User $secretaria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $ano = AnoLectivo::create(['nome' => '2025-2026', 'estado' => AnoLectivo::ESTADO_ACTUAL]);
        Configuracao::set('ano_lectivo_actual', '2025-2026');

        $curso = Curso::create(['nome' => 'C', 'codigo' => 'C'.uniqid(), 'activo' => true]);
        $classe = Classe::firstOrCreate(['codigo' => 'E10'], ['nome' => '10ª', 'activo' => true]);
        $turma = Turma::create([
            'codigo' => 'TD'.uniqid(),
            'nome' => 'TD',
            'curso_id' => $curso->id,
            'classe_id' => $classe->id,
            'ano_lectivo_id' => $ano->id,
            'ano_lectivo' => '2025-2026',
            'activo' => true,
        ]);

        $this->aluno = Aluno::create([
            'numero_processo' => 'DOC-1',
            'nome' => 'Aluno Doc',
            'turma_id' => $turma->id,
            'activo' => true,
            'situacao_academica' => Aluno::SITUACAO_MATRICULADO,
        ]);

        $this->encarregado = Encarregado::create([
            'nome' => 'Pai Doc',
            'telefone' => '900111222',
            'email' => 'pai.doc@exemplo.ao',
            'activo' => true,
        ]);
        $this->encarregado->alunos()->attach($this->aluno->id, [
            'parentesco' => 'pai',
            'principal' => true,
            'pode_ver_notas' => true,
            'pode_assinar_boletim' => true,
        ]);

        $this->encarregadoUser = User::factory()->create([
            'name' => 'Pai Doc',
            'email' => 'pai.doc.user@exemplo.ao',
            'estado' => 'activo',
            'encarregado_id' => $this->encarregado->id,
        ]);
        $this->encarregadoUser->assignRole('encarregado');

        $this->secretaria = User::factory()->create([
            'name' => 'Sec',
            'email' => 'sec.doc@exemplo.ao',
            'estado' => 'activo',
        ]);
        $this->secretaria->assignRole('secretaria');
    }

    public function test_encarregado_pode_pedir_documento(): void
    {
        $this->actingAs($this->encarregadoUser)
            ->post(route('portal.encarregado.documentos.store'), [
                'aluno_id' => $this->aluno->id,
                'tipo' => DocumentoPedido::TIPO_FREQUENCIA,
                'observacao' => 'Para o banco',
            ])
            ->assertRedirect(route('portal.encarregado.documentos.index'));

        $this->assertDatabaseHas('documento_pedidos', [
            'aluno_id' => $this->aluno->id,
            'encarregado_id' => $this->encarregado->id,
            'tipo' => DocumentoPedido::TIPO_FREQUENCIA,
            'estado' => DocumentoPedido::ESTADO_PENDENTE,
        ]);
    }

    public function test_secretaria_marca_pedido_como_pronto(): void
    {
        $pedido = DocumentoPedido::create([
            'aluno_id' => $this->aluno->id,
            'encarregado_id' => $this->encarregado->id,
            'solicitado_por' => $this->encarregadoUser->id,
            'tipo' => DocumentoPedido::TIPO_CARTAO,
            'estado' => DocumentoPedido::ESTADO_PENDENTE,
        ]);

        $this->actingAs($this->secretaria)
            ->patch(route('secretaria.documentos.pedidos.actualizar', $pedido), [
                'estado' => DocumentoPedido::ESTADO_PRONTO,
                'nota_secretaria' => 'Pode levantar amanhã',
            ])
            ->assertRedirect();

        $pedido->refresh();
        $this->assertSame(DocumentoPedido::ESTADO_PRONTO, $pedido->estado);
        $this->assertSame('Pode levantar amanhã', $pedido->nota_secretaria);
        $this->assertNotNull($pedido->atendido_em);
    }

    public function test_encarregado_nao_duplica_pedido_pendente(): void
    {
        DocumentoPedido::create([
            'aluno_id' => $this->aluno->id,
            'encarregado_id' => $this->encarregado->id,
            'solicitado_por' => $this->encarregadoUser->id,
            'tipo' => DocumentoPedido::TIPO_FICHA,
            'estado' => DocumentoPedido::ESTADO_PENDENTE,
        ]);

        $this->actingAs($this->encarregadoUser)
            ->from(route('portal.encarregado.documentos.index'))
            ->post(route('portal.encarregado.documentos.store'), [
                'aluno_id' => $this->aluno->id,
                'tipo' => DocumentoPedido::TIPO_FICHA,
            ])
            ->assertRedirect(route('portal.encarregado.documentos.index'))
            ->assertSessionHas('error');

        $this->assertSame(1, DocumentoPedido::query()->where('tipo', DocumentoPedido::TIPO_FICHA)->count());
    }
}
