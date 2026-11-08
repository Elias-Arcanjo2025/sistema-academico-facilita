<?php

namespace Tests\Feature;

use App\Models\Configuracao;
use App\Models\CoordenadorDisciplina;
use App\Models\Pedagogico\PedBancoQuestao;
use App\Models\Pedagogico\PedPlanificacao;
use App\Models\Pedagogico\PedProva;
use App\Models\Turma;
use App\Models\User;
use App\Services\Pedagogico\BancoQuestaoService;
use App\Services\Pedagogico\PedagogicoAprovacaoService;
use App\Services\Pedagogico\PlanificacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class FaseCCoordenadorDisciplinaTest extends TestCase
{
    use RefreshDatabase;

    private object $scenario;

    private User $professor;

    private User $coord;

    private User $outroProfessor;

    private PedPlanificacao $planificacao;

    private CoordenadorDisciplina $atribuicaoCoord;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'pedagogico.ver', 'pedagogico.planificacao.criar', 'pedagogico.planificacao.editar',
            'pedagogico.aprovar', 'pedagogico.banco.ver', 'pedagogico.banco.gerir',
            'pedagogico.dashboard.ver', 'disciplinas.ver', 'turmas.ver', 'alunos.ver',
            'coordenadores_disciplina.ver', 'coordenadores_disciplina.criar', 'coordenadores_disciplina.eliminar',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        foreach (['professor', 'coordenador_disciplina', 'admin_full'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        Role::findByName('professor')->syncPermissions([
            'pedagogico.ver', 'pedagogico.planificacao.criar', 'pedagogico.planificacao.editar',
            'pedagogico.banco.ver', 'pedagogico.banco.gerir',
        ]);
        Role::findByName('coordenador_disciplina')->syncPermissions([
            'pedagogico.ver', 'pedagogico.dashboard.ver', 'pedagogico.aprovar',
            'pedagogico.banco.ver', 'pedagogico.banco.gerir',
            'disciplinas.ver', 'turmas.ver', 'alunos.ver',
        ]);
        Role::findByName('admin_full')->syncPermissions(Permission::all());

        $this->scenario = (new TransicaoAnoScenarioBuilder)->build();
        Configuracao::set('trimestre_actual', '1', 'test');

        $this->professor = $this->scenario->users->professor;
        $this->professor->assignRole('professor');

        $this->outroProfessor = User::factory()->create(['estado' => 'activo']);
        $this->outroProfessor->assignRole('professor');

        $this->coord = User::factory()->create(['estado' => 'activo', 'name' => 'Coord Disc']);
        $this->coord->assignRole('coordenador_disciplina');

        $this->atribuicaoCoord = CoordenadorDisciplina::create([
            'user_id' => $this->coord->id,
            'disciplina_id' => $this->scenario->disciplina->id,
            'ano_lectivo' => TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            'ano_lectivo_id' => $this->scenario->anos->origem->id,
            'activo' => true,
        ]);
        $this->atribuicaoCoord->classes()->sync([$this->scenario->classes->c10->id]);

        $this->planificacao = PedPlanificacao::create([
            'ano_lectivo_id' => $this->scenario->anos->origem->id,
            'trimestre' => 1,
            'turma_id' => $this->scenario->turmas->origem_10->id,
            'disciplina_id' => $this->scenario->disciplina->id,
            'user_id' => $this->professor->id,
            'data_inicial' => now()->startOfMonth(),
            'data_final' => now()->endOfMonth(),
            'estado' => PedPlanificacao::ESTADO_RASCUNHO,
            'tipo_periodo' => PedPlanificacao::TIPO_QUINZENAL,
        ]);
    }

    public function test_atribuir_coordenador_disciplina(): void
    {
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole('admin_full');

        $this->actingAs($admin)
            ->post(route('coordenadores-disciplina.store'), [
                'user_id' => $this->outroProfessor->id,
                'disciplina_id' => $this->scenario->disciplina->id,
                'ano_lectivo_id' => $this->scenario->anos->origem->id,
                'classe_ids' => [$this->scenario->classes->c11->id],
                'activo' => 1,
            ])
            ->assertRedirect(route('coordenadores-disciplina.index'));

        $this->assertDatabaseHas('coordenador_disciplina', [
            'user_id' => $this->outroProfessor->id,
            'disciplina_id' => $this->scenario->disciplina->id,
        ]);

        $atribuicao = CoordenadorDisciplina::query()
            ->where('user_id', $this->outroProfessor->id)
            ->first();
        $this->assertTrue($atribuicao->classes->contains('id', $this->scenario->classes->c11->id));
        $this->assertTrue($this->outroProfessor->fresh()->hasRole('coordenador_disciplina'));
    }

    public function test_bloquear_overlap_mesmo_professor_mesma_classe(): void
    {
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole('admin_full');

        $this->actingAs($admin)
            ->from(route('coordenadores-disciplina.create'))
            ->post(route('coordenadores-disciplina.store'), [
                'user_id' => $this->coord->id,
                'disciplina_id' => $this->scenario->disciplina->id,
                'ano_lectivo_id' => $this->scenario->anos->origem->id,
                'classe_ids' => [$this->scenario->classes->c10->id],
                'activo' => 1,
            ])
            ->assertRedirect(route('coordenadores-disciplina.create'))
            ->assertSessionHas('error');
    }

    public function test_professor_submete_e_coord_aprova(): void
    {
        $this->actingAs($this->professor)
            ->post(route('pedagogico.planificacoes.submeter', $this->planificacao))
            ->assertRedirect();

        $this->assertSame(PedPlanificacao::ESTADO_PENDENTE, $this->planificacao->fresh()->estado);

        $this->actingAs($this->outroProfessor)
            ->post(route('pedagogico.planificacoes.aprovar', $this->planificacao))
            ->assertForbidden();

        $this->actingAs($this->coord)
            ->post(route('pedagogico.planificacoes.aprovar', $this->planificacao))
            ->assertRedirect();

        $this->assertSame(PedPlanificacao::ESTADO_APROVADA, $this->planificacao->fresh()->estado);
    }

    public function test_aprovacao_apenas_na_classe_atribuida(): void
    {
        $turma11 = Turma::create([
            'codigo' => 'TI11AT-O',
            'nome' => 'TI11AT',
            'curso_id' => $this->scenario->curso->ti->id,
            'classe_id' => $this->scenario->classes->c11->id,
            'ano_lectivo' => TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            'ano_lectivo_id' => $this->scenario->anos->origem->id,
            'activo' => true,
        ]);

        $plan11 = PedPlanificacao::create([
            'ano_lectivo_id' => $this->scenario->anos->origem->id,
            'trimestre' => 1,
            'turma_id' => $turma11->id,
            'disciplina_id' => $this->scenario->disciplina->id,
            'user_id' => $this->professor->id,
            'data_inicial' => now()->startOfMonth(),
            'data_final' => now()->endOfMonth(),
            'estado' => PedPlanificacao::ESTADO_PENDENTE,
            'tipo_periodo' => PedPlanificacao::TIPO_QUINZENAL,
            'submetido_em' => now(),
        ]);

        // Coord só tem 10ª — não pode aprovar 11ª
        $this->actingAs($this->coord)
            ->post(route('pedagogico.planificacoes.aprovar', $plan11))
            ->assertForbidden();

        $coord11 = User::factory()->create(['estado' => 'activo']);
        $coord11->assignRole('coordenador_disciplina');
        $atr = CoordenadorDisciplina::create([
            'user_id' => $coord11->id,
            'disciplina_id' => $this->scenario->disciplina->id,
            'ano_lectivo' => TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            'ano_lectivo_id' => $this->scenario->anos->origem->id,
            'activo' => true,
        ]);
        $atr->classes()->sync([$this->scenario->classes->c11->id]);

        $this->actingAs($coord11)
            ->post(route('pedagogico.planificacoes.aprovar', $plan11))
            ->assertRedirect();

        $this->assertSame(PedPlanificacao::ESTADO_APROVADA, $plan11->fresh()->estado);
    }

    public function test_activar_bloqueado_sem_aprovacao(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('aprovada');

        app(PlanificacaoService::class)->activar($this->planificacao, $this->professor);
    }

    public function test_banco_questao_e_importar_prova(): void
    {
        $questao = app(BancoQuestaoService::class)->criar($this->professor, [
            'disciplina_id' => $this->scenario->disciplina->id,
            'tipo' => PedBancoQuestao::TIPO_CURTA,
            'enunciado' => 'Quanto é 2+2?',
            'cotacao' => 2,
            'dificuldade' => PedBancoQuestao::DIFF_FACIL,
            'resposta_json' => ['resposta' => '4'],
        ]);

        $prova = PedProva::create([
            'tipo' => 'MAC',
            'planificacao_id' => $this->planificacao->id,
            'turma_id' => $this->scenario->turmas->origem_10->id,
            'disciplina_id' => $this->scenario->disciplina->id,
            'conteudo_json' => ['questoes' => []],
            'tempo_minutos' => 90,
            'cotacao_total' => 20,
            'estado' => PedProva::ESTADO_RASCUNHO,
        ]);

        app(BancoQuestaoService::class)->importarParaProva($this->professor, $prova, [$questao->id]);

        $conteudo = $prova->fresh()->conteudo_json;
        $this->assertNotEmpty($conteudo['questoes'] ?? []);
        $this->assertSame($questao->id, $conteudo['questoes'][0]['banco_questao_id']);
    }

    public function test_rejeitar_planificacao_volta_rascunho(): void
    {
        app(PedagogicoAprovacaoService::class)->submeterPlanificacao($this->professor, $this->planificacao);
        app(PedagogicoAprovacaoService::class)->rejeitarPlanificacao(
            $this->coord,
            $this->planificacao->fresh(),
            'Falta detalhar temas'
        );

        $fresh = $this->planificacao->fresh();
        $this->assertSame(PedPlanificacao::ESTADO_RASCUNHO, $fresh->estado);
        $this->assertSame('Falta detalhar temas', $fresh->motivo_rejeicao);
    }

    public function test_coord_nao_pode_auto_aprovar_planificacao(): void
    {
        $this->professor->assignRole('coordenador_disciplina');
        $this->professor->givePermissionTo('pedagogico.aprovar');
        CoordenadorDisciplina::create([
            'user_id' => $this->professor->id,
            'disciplina_id' => $this->scenario->disciplina->id,
            'ano_lectivo' => TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            'ano_lectivo_id' => $this->scenario->anos->origem->id,
            'activo' => true,
        ])->classes()->sync([$this->scenario->classes->c10->id]);

        app(PedagogicoAprovacaoService::class)->submeterPlanificacao($this->professor, $this->planificacao);

        $this->actingAs($this->professor)
            ->post(route('pedagogico.planificacoes.aprovar', $this->planificacao))
            ->assertForbidden();

        $this->assertSame(PedPlanificacao::ESTADO_PENDENTE, $this->planificacao->fresh()->estado);
    }
}
