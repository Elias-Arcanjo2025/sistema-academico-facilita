<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\CoordenadorCurso;
use App\Models\DirectorTurma;
use App\Models\Encarregado;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\User;
use App\Services\AlunoTransicaoService;
use App\Services\AnoLectivoTransferService;
use App\Services\FechoAnoWizardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class TransicaoAnoLectivoTest extends TestCase
{
    use RefreshDatabase;

    private TransicaoAnoScenarioBuilder $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin_full']);
        $this->scenario = (new TransicaoAnoScenarioBuilder)->build();
    }

    public function test_validacao_transfer_rejeita_destino_que_nao_e_novo(): void
    {
        $this->scenario->anos->destino->update(['estado' => AnoLectivo::ESTADO_ANTERIOR]);

        $errors = app(AnoLectivoTransferService::class)->validateTransfer(
            TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            TransicaoAnoScenarioBuilder::ANO_DESTINO
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('novo', strtolower(implode(' ', $errors)));
    }

    public function test_validacao_transicao_exige_alunos_na_origem(): void
    {
        Aluno::query()->delete();

        $errors = app(AlunoTransicaoService::class)->validateTransicao(
            TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            TransicaoAnoScenarioBuilder::ANO_DESTINO
        );

        $this->assertContains("Não existem alunos no ano lectivo ".TransicaoAnoScenarioBuilder::ANO_ORIGEM, $errors);
    }

    public function test_transfer_copia_estrutura_para_ano_novo(): void
    {
        $result = app(AnoLectivoTransferService::class)->executeTransfer(
            TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            TransicaoAnoScenarioBuilder::ANO_DESTINO
        );

        $this->assertTrue($result['success'], $result['message'] ?? $result['error'] ?? 'falhou');
        $this->assertGreaterThan(0, $result['stats']['associacoes']);
        $this->assertGreaterThan(0, $result['stats']['directores']);
        $this->assertGreaterThan(0, $result['stats']['coordenadores']);

        $destinoId = $this->scenario->anos->destino->id;
        $this->assertTrue(
            ProfessorDisciplinaTurma::where('ano_lectivo_id', $destinoId)->exists()
        );
        $this->assertTrue(DirectorTurma::where('ano_lectivo_id', $destinoId)->exists());
        $this->assertTrue(CoordenadorCurso::where('ano_lectivo_id', $destinoId)->exists());
    }

    public function test_transfer_com_mesmo_codigo_reutiliza_turma_existente(): void
    {
        $this->scenario->withTurmasDestinoMesmoCodigo();

        $result = app(AnoLectivoTransferService::class)->executeTransfer(
            TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            TransicaoAnoScenarioBuilder::ANO_DESTINO
        );

        $this->assertTrue($result['success'], $result['message'] ?? $result['error'] ?? 'falhou');
        $this->assertGreaterThan(0, $result['stats']['skipped'] ?? 0);
    }

    public function test_transicao_aluno_aprovado_move_para_classe_seguinte(): void
    {
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole('admin_full');
        $this->actingAs($admin);

        $alunoOrigem = Aluno::where('numero_processo', 'PROC-001')->firstOrFail();
        $alunoOrigem->update([
            'bi_numero' => 'BI-123456',
            'foto_path' => 'alunos/foto-teste.jpg',
            'consulta_notas_portal' => true,
        ]);
        $encarregado = Encarregado::create([
            'nome' => 'Pai Transicao',
            'telefone' => '900123123',
            'activo' => true,
        ]);
        $alunoOrigem->encarregados()->attach($encarregado->id, [
            'parentesco' => 'pai',
            'principal' => true,
            'pode_ver_notas' => true,
            'pode_assinar_boletim' => true,
        ]);

        $transicao = app(AlunoTransicaoService::class);
        $turmasOrigem = Turma::with('classe')->where('ano_lectivo_id', $this->scenario->anos->origem->id)->get();
        $turmasDestino = Turma::with('classe')->where('ano_lectivo_id', $this->scenario->anos->destino->id)->get();

        $mapPromocao = $transicao->criarMapeamentoPromocao($turmasOrigem, $turmasDestino);
        $mapRepetencia = $transicao->criarMapeamentoRepetencia($turmasOrigem, $turmasDestino);

        $this->assertSame(
            $this->scenario->turmas->destino_11->id,
            $mapPromocao[$this->scenario->turmas->origem_10->id]
        );

        $result = $transicao->executeTransicao(
            TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            TransicaoAnoScenarioBuilder::ANO_DESTINO,
            $mapPromocao,
            $mapRepetencia,
            false,
            null
        );

        $this->assertTrue($result['success'], json_encode($result['stats']['erros'] ?? []));
        $this->assertSame(1, $result['stats']['alunos_transitados']);
        $this->assertSame(1, $result['stats']['vinculos_encarregado']);

        $alunoDestino = Aluno::where('numero_processo', 'PROC-001')
            ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $this->scenario->anos->destino->id))
            ->first();

        $this->assertNotNull($alunoDestino);
        $this->assertSame($this->scenario->turmas->destino_11->id, $alunoDestino->turma_id);
        $this->assertSame('BI-123456', $alunoDestino->bi_numero);
        $this->assertSame('alunos/foto-teste.jpg', $alunoDestino->foto_path);
        $this->assertTrue((bool) $alunoDestino->consulta_notas_portal);
        $this->assertTrue($alunoDestino->encarregados()->where('encarregados.id', $encarregado->id)->exists());
        $pivot = $alunoDestino->encarregados()->where('encarregados.id', $encarregado->id)->first()?->pivot;
        $this->assertTrue((bool) $pivot?->principal);
        $this->assertSame('pai', $pivot?->parentesco);
    }

    public function test_transicao_falha_sem_turma_de_promocao_no_destino(): void
    {
        $admin = User::factory()->create(['estado' => 'activo']);
        $this->actingAs($admin);

        $this->scenario->turmas->destino_11->delete();

        $transicao = app(AlunoTransicaoService::class);
        $turmasOrigem = Turma::with('classe')->where('ano_lectivo_id', $this->scenario->anos->origem->id)->get();
        $turmasDestino = Turma::with('classe')->where('ano_lectivo_id', $this->scenario->anos->destino->id)->get();

        $result = $transicao->executeTransicao(
            TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            TransicaoAnoScenarioBuilder::ANO_DESTINO,
            $transicao->criarMapeamentoPromocao($turmasOrigem, $turmasDestino),
            $transicao->criarMapeamentoRepetencia($turmasOrigem, $turmasDestino),
            false,
            null
        );

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['stats']['alunos_transitados']);
        $this->assertNotEmpty($result['stats']['erros']);
    }

    public function test_activar_ano_marca_anterior_e_actualiza_configuracao(): void
    {
        AnoLectivo::setActual(TransicaoAnoScenarioBuilder::ANO_DESTINO);

        $this->assertSame(AnoLectivo::ESTADO_ANTERIOR, $this->scenario->anos->origem->fresh()->estado);
        $this->assertSame(AnoLectivo::ESTADO_ACTUAL, $this->scenario->anos->destino->fresh()->estado);
        $this->assertSame(TransicaoAnoScenarioBuilder::ANO_DESTINO, Configuracao::getAnoLectivoActual());
    }

    public function test_wizard_detecta_necessidade_turmas_classe_seguinte(): void
    {
        $estado = app(FechoAnoWizardService::class)->obterEstado(
            TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            TransicaoAnoScenarioBuilder::ANO_DESTINO
        );

        $this->assertTrue($estado['estatisticas']['temTurmasPromocao']);
        $this->assertTrue($estado['passos'][0]['concluido']);
    }

    public function test_wizard_passo_transfer_incompleto_se_faltam_turmas_copiadas(): void
    {
        Turma::where('ano_lectivo_id', $this->scenario->anos->destino->id)->delete();

        $estado = app(FechoAnoWizardService::class)->obterEstado(
            TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            TransicaoAnoScenarioBuilder::ANO_DESTINO
        );

        $this->assertFalse($estado['passos'][2]['concluido']);
    }

    public function test_preview_detecta_conflito_codigo_turma(): void
    {
        $this->scenario->withTurmasDestinoMesmoCodigo();

        $preview = app(AnoLectivoTransferService::class)->previewTransfer(
            TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            TransicaoAnoScenarioBuilder::ANO_DESTINO
        );

        $this->assertNotEmpty($preview['conflicts']);
        $this->assertStringContainsString('TI10AT-O', implode(' ', $preview['conflicts']));
    }

    public function test_transicao_reprovado_ignorado_sem_opcao(): void
    {
        $this->scenario->withAlunoReprovado();

        $admin = User::factory()->create(['estado' => 'activo']);
        $this->actingAs($admin);

        $transicao = app(AlunoTransicaoService::class);
        $turmasOrigem = Turma::with('classe')->where('ano_lectivo_id', $this->scenario->anos->origem->id)->get();
        $turmasDestino = Turma::with('classe')->where('ano_lectivo_id', $this->scenario->anos->destino->id)->get();

        $result = $transicao->executeTransicao(
            TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            TransicaoAnoScenarioBuilder::ANO_DESTINO,
            $transicao->criarMapeamentoPromocao($turmasOrigem, $turmasDestino),
            $transicao->criarMapeamentoRepetencia($turmasOrigem, $turmasDestino),
            false,
            null
        );

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['stats']['alunos_transitados']);
        $this->assertSame(1, $result['stats']['alunos_ignorados']);

        $this->assertNull(
            Aluno::where('numero_processo', 'PROC-REP')
                ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $this->scenario->anos->destino->id))
                ->first()
        );
    }

    public function test_transicao_reprovado_fica_na_mesma_classe_com_opcao(): void
    {
        $this->scenario->withAlunoReprovado();

        $admin = User::factory()->create(['estado' => 'activo']);
        $this->actingAs($admin);

        $transicao = app(AlunoTransicaoService::class);
        $turmasOrigem = Turma::with('classe')->where('ano_lectivo_id', $this->scenario->anos->origem->id)->get();
        $turmasDestino = Turma::with('classe')->where('ano_lectivo_id', $this->scenario->anos->destino->id)->get();

        $result = $transicao->executeTransicao(
            TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            TransicaoAnoScenarioBuilder::ANO_DESTINO,
            $transicao->criarMapeamentoPromocao($turmasOrigem, $turmasDestino),
            $transicao->criarMapeamentoRepetencia($turmasOrigem, $turmasDestino),
            true,
            null
        );

        $this->assertTrue($result['success'], json_encode($result['stats']['erros'] ?? []));
        $this->assertSame(2, $result['stats']['alunos_transitados']);
        $this->assertSame(1, $result['stats']['alunos_aprovados']);
        $this->assertSame(1, $result['stats']['alunos_reprovados']);

        $reprovadoDestino = Aluno::where('numero_processo', 'PROC-REP')
            ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $this->scenario->anos->destino->id))
            ->first();

        $this->assertNotNull($reprovadoDestino);
        $this->assertSame($this->scenario->turmas->destino_10->id, $reprovadoDestino->turma_id);
    }

    public function test_mapeamento_multiplas_turmas_mesmo_curso(): void
    {
        $this->scenario->withSegundaTurma();

        $transicao = app(AlunoTransicaoService::class);
        $turmasOrigem = Turma::with('classe')->where('ano_lectivo_id', $this->scenario->anos->origem->id)->get();
        $turmasDestino = Turma::with('classe')->where('ano_lectivo_id', $this->scenario->anos->destino->id)->get();

        $mapPromocao = $transicao->criarMapeamentoPromocao($turmasOrigem, $turmasDestino);
        $mapRepetencia = $transicao->criarMapeamentoRepetencia($turmasOrigem, $turmasDestino);

        $this->assertSame($this->scenario->turmas->destino_11->id, $mapPromocao[$this->scenario->turmas->origem_10->id]);
        $this->assertSame($this->scenario->turmas->destino_11_b->id, $mapPromocao[$this->scenario->turmas->origem_10_b->id]);
        $this->assertSame($this->scenario->turmas->destino_10->id, $mapRepetencia[$this->scenario->turmas->origem_10->id]);
        $this->assertSame($this->scenario->turmas->destino_10_b->id, $mapRepetencia[$this->scenario->turmas->origem_10_b->id]);
    }

    public function test_transicao_multiplas_turmas_transita_todos_aprovados(): void
    {
        $this->scenario->withSegundaTurma();

        $admin = User::factory()->create(['estado' => 'activo']);
        $this->actingAs($admin);

        $transicao = app(AlunoTransicaoService::class);
        $turmasOrigem = Turma::with('classe')->where('ano_lectivo_id', $this->scenario->anos->origem->id)->get();
        $turmasDestino = Turma::with('classe')->where('ano_lectivo_id', $this->scenario->anos->destino->id)->get();

        $result = $transicao->executeTransicao(
            TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            TransicaoAnoScenarioBuilder::ANO_DESTINO,
            $transicao->criarMapeamentoPromocao($turmasOrigem, $turmasDestino),
            $transicao->criarMapeamentoRepetencia($turmasOrigem, $turmasDestino),
            false,
            null
        );

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['stats']['alunos_transitados']);

        $this->assertNotNull(Aluno::where('numero_processo', 'PROC-001')
            ->whereHas('turma', fn ($q) => $q->where('id', $this->scenario->turmas->destino_11->id))
            ->first());

        $this->assertNotNull(Aluno::where('numero_processo', 'PROC-002')
            ->whereHas('turma', fn ($q) => $q->where('id', $this->scenario->turmas->destino_11_b->id))
            ->first());
    }
}
