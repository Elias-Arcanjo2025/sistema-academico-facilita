<?php

namespace Tests\Feature;

use App\Models\Configuracao;
use App\Models\CoordenadorDisciplina;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\OrientacaoMetodologica;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\User;
use App\Notifications\StaffAlertaAcademicoNotification;
use App\Services\Pedagogico\DisciplinaCoordEscopoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class CoordenadorDisciplinaEscopoTest extends TestCase
{
    use RefreshDatabase;

    private object $scenario;

    private User $coord;

    private User $professor;

    private User $outroProfessor;

    private Disciplina $outraDisciplina;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'pedagogico.ver', 'pedagogico.dashboard.ver', 'pedagogico.aprovar',
            'notas.supervisionar', 'relatorios.disciplina', 'disciplina.avisar',
            'orientacoes.ver', 'orientacoes.gerir',
            'disciplinas.ver', 'turmas.ver', 'alunos.ver',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        foreach (['professor', 'coordenador_disciplina'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        Role::findByName('coordenador_disciplina')->syncPermissions([
            'pedagogico.ver', 'pedagogico.dashboard.ver', 'pedagogico.aprovar',
            'notas.supervisionar', 'relatorios.disciplina', 'disciplina.avisar',
            'orientacoes.ver', 'orientacoes.gerir',
            'disciplinas.ver', 'turmas.ver', 'alunos.ver',
        ]);
        Role::findByName('professor')->syncPermissions([
            'orientacoes.ver', 'pedagogico.ver',
        ]);

        $this->scenario = (new TransicaoAnoScenarioBuilder)->build();
        Configuracao::set('trimestre_actual', '1', 'test');

        $this->professor = $this->scenario->users->professor;
        $this->professor->assignRole('professor');

        ProfessorDisciplinaTurma::firstOrCreate([
            'user_id' => $this->professor->id,
            'disciplina_id' => $this->scenario->disciplina->id,
            'turma_id' => $this->scenario->turmas->origem_10->id,
            'ano_lectivo_id' => $this->scenario->anos->origem->id,
        ], [
            'ano_lectivo' => TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            'activo' => true,
        ]);

        $this->outraDisciplina = Disciplina::create([
            'codigo' => 'OUT',
            'nome' => 'Outra Disciplina',
            'activo' => true,
        ]);

        $this->outroProfessor = User::factory()->create(['estado' => 'activo']);
        $this->outroProfessor->assignRole('professor');

        ProfessorDisciplinaTurma::create([
            'user_id' => $this->outroProfessor->id,
            'disciplina_id' => $this->outraDisciplina->id,
            'turma_id' => $this->scenario->turmas->origem_10->id,
            'ano_lectivo' => TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            'ano_lectivo_id' => $this->scenario->anos->origem->id,
            'activo' => true,
        ]);

        $this->coord = User::factory()->create(['estado' => 'activo', 'name' => 'Coord Disc']);
        $this->coord->assignRole('coordenador_disciplina');

        $atr = CoordenadorDisciplina::create([
            'user_id' => $this->coord->id,
            'disciplina_id' => $this->scenario->disciplina->id,
            'ano_lectivo' => TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            'ano_lectivo_id' => $this->scenario->anos->origem->id,
            'activo' => true,
        ]);
        $atr->classes()->sync([$this->scenario->classes->c10->id]);
    }

    public function test_escopo_so_pares_coordenados(): void
    {
        $escopo = app(DisciplinaCoordEscopoService::class);

        $this->assertTrue($escopo->coordena(
            $this->coord,
            $this->scenario->disciplina->id,
            $this->scenario->classes->c10->id,
        ));
        $this->assertFalse($escopo->coordena(
            $this->coord,
            $this->scenario->disciplina->id,
            $this->scenario->classes->c11->id,
        ));
        $this->assertFalse($escopo->coordena(
            $this->coord,
            $this->outraDisciplina->id,
            $this->scenario->classes->c10->id,
        ));

        $turmaIds = $escopo->turmaIds($this->coord);
        $this->assertTrue($turmaIds->contains($this->scenario->turmas->origem_10->id));
    }

    public function test_lancamentos_e_relatorio_no_ambito(): void
    {
        $aluno = $this->scenario->alunos->aprovado;
        Nota::create([
            'aluno_id' => $aluno->id,
            'turma_id' => $this->scenario->turmas->origem_10->id,
            'disciplina_id' => $this->scenario->disciplina->id,
            'ano_lectivo_id' => $this->scenario->anos->origem->id,
            'ano_lectivo' => TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            'trimestre' => 1,
            'tipo' => Nota::TIPO_MAC,
            'valor' => 12,
            'professor_id' => $this->professor->id,
        ]);

        $this->actingAs($this->coord)
            ->get(route('pedagogico.coordenacao.lancamentos', ['trimestre' => 1]))
            ->assertOk()
            ->assertSee($this->scenario->disciplina->nome);

        $this->actingAs($this->coord)
            ->get(route('pedagogico.coordenacao.relatorio', ['trimestre' => 1]))
            ->assertOk()
            ->assertSee('Rendimento');
    }

    public function test_aviso_so_professores_da_disciplina(): void
    {
        Notification::fake();

        $this->actingAs($this->coord)
            ->post(route('pedagogico.coordenacao.avisos.enviar'), [
                'disciplina_id' => $this->scenario->disciplina->id,
                'classe_id' => $this->scenario->classes->c10->id,
                'titulo' => 'Reunião de área',
                'mensagem' => 'Tragam as planificações.',
            ])
            ->assertRedirect(route('pedagogico.coordenacao.avisos'));

        Notification::assertSentTo($this->professor, StaffAlertaAcademicoNotification::class);
        Notification::assertNotSentTo($this->outroProfessor, StaffAlertaAcademicoNotification::class);
    }

    public function test_aviso_outra_disciplina_proibido(): void
    {
        $this->actingAs($this->coord)
            ->post(route('pedagogico.coordenacao.avisos.enviar'), [
                'disciplina_id' => $this->outraDisciplina->id,
                'classe_id' => $this->scenario->classes->c10->id,
                'titulo' => 'Hack',
                'mensagem' => 'Não deve passar',
            ])
            ->assertForbidden();
    }

    public function test_orientacao_so_no_par_coordenado(): void
    {
        $this->actingAs($this->coord)
            ->post(route('pedagogico.orientacoes.store'), [
                'disciplina_id' => $this->outraDisciplina->id,
                'classe_id' => $this->scenario->classes->c10->id,
                'titulo' => 'Inválida',
                'conteudo' => 'Texto',
            ])
            ->assertForbidden();

        $response = $this->actingAs($this->coord)
            ->post(route('pedagogico.orientacoes.store'), [
                'disciplina_id' => $this->scenario->disciplina->id,
                'classe_id' => $this->scenario->classes->c10->id,
                'titulo' => 'Método de resolução de problemas',
                'conteudo' => 'Usar exemplos concretos em cada aula.',
            ]);

        $orientacao = OrientacaoMetodologica::first();
        $this->assertNotNull($orientacao);
        $response->assertRedirect(route('pedagogico.orientacoes.show', $orientacao));

        Notification::fake();
        $this->actingAs($this->coord)
            ->post(route('pedagogico.orientacoes.publicar', $orientacao))
            ->assertRedirect();

        $this->assertTrue($orientacao->fresh()->isPublicado());
        Notification::assertSentTo($this->professor, StaffAlertaAcademicoNotification::class);
    }

    public function test_relatorio_inclui_negativas_e_evolucao(): void
    {
        $aluno = $this->scenario->alunos->aprovado;
        Nota::create([
            'aluno_id' => $aluno->id,
            'turma_id' => $this->scenario->turmas->origem_10->id,
            'disciplina_id' => $this->scenario->disciplina->id,
            'ano_lectivo_id' => $this->scenario->anos->origem->id,
            'ano_lectivo' => TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            'trimestre' => 1,
            'tipo' => Nota::TIPO_MT,
            'valor' => 8,
            'professor_id' => $this->professor->id,
        ]);

        $this->actingAs($this->coord)
            ->get(route('pedagogico.coordenacao.relatorio', ['trimestre' => 1]))
            ->assertOk()
            ->assertSee('Negativas')
            ->assertSee('Evolução')
            ->assertSee('Aproveitamento por professor');
    }

    public function test_agenda_reuniao_disciplina_no_ambito(): void
    {
        Permission::firstOrCreate(['name' => 'agenda.gerir']);
        Permission::firstOrCreate(['name' => 'agenda.ver']);
        $this->coord->givePermissionTo(['agenda.gerir', 'agenda.ver']);

        $this->actingAs($this->coord)
            ->post(route('agenda.store'), [
                'titulo' => 'Reunião de área',
                'tipo' => 'reuniao',
                'inicia_em' => now()->addDay()->format('Y-m-d H:i:s'),
                'ano_lectivo_id' => $this->scenario->anos->origem->id,
                'visibilidade' => 'disciplina',
                'disciplina_id' => $this->scenario->disciplina->id,
            ])
            ->assertRedirect(route('agenda.index'));

        $this->assertDatabaseHas('agenda_eventos', [
            'titulo' => 'Reunião de área',
            'visibilidade' => 'disciplina',
            'disciplina_id' => $this->scenario->disciplina->id,
        ]);
    }
}
