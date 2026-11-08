<?php

namespace Tests\Feature;

use App\Models\AgendaEvento;
use App\Models\Configuracao;
use App\Models\DirectorTurma;
use App\Models\MaterialAcademico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class AgendaMateriaisTest extends TestCase
{
    use RefreshDatabase;

    private object $scenario;

    private User $admin;

    private User $director;

    private User $professor;

    private User $alunoUser;

    private User $outroAlunoUser;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'agenda.ver', 'agenda.gerir',
            'materiais.ver', 'materiais.gerir', 'materiais.download',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        foreach (['admin_full', 'director_turma', 'professor', 'aluno'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        Role::findByName('admin_full')->syncPermissions(Permission::all());
        Role::findByName('director_turma')->syncPermissions([
            'agenda.ver', 'agenda.gerir',
            'materiais.ver', 'materiais.gerir', 'materiais.download',
        ]);
        Role::findByName('professor')->syncPermissions([
            'agenda.ver',
            'materiais.ver', 'materiais.gerir', 'materiais.download',
        ]);

        $this->scenario = (new TransicaoAnoScenarioBuilder)->build()->withSegundaTurma();
        Configuracao::set('ano_lectivo_actual', TransicaoAnoScenarioBuilder::ANO_ORIGEM, 'test');

        $this->admin = User::factory()->create(['estado' => 'activo']);
        $this->admin->assignRole('admin_full');

        $this->director = User::factory()->create(['estado' => 'activo']);
        $this->director->assignRole('director_turma');
        DirectorTurma::create([
            'user_id' => $this->director->id,
            'turma_id' => $this->scenario->turmas->origem_10->id,
            'ano_lectivo' => TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            'ano_lectivo_id' => $this->scenario->anos->origem->id,
            'activo' => true,
        ]);

        $this->professor = User::factory()->create(['estado' => 'activo']);
        $this->professor->assignRole('professor');

        $aluno = $this->scenario->alunos->aprovado;
        $this->alunoUser = User::factory()->create([
            'estado' => 'activo',
            'aluno_id' => $aluno->id,
        ]);
        $this->alunoUser->assignRole('aluno');

        $outroAluno = $this->scenario->alunos->aprovado_b;
        $this->outroAlunoUser = User::factory()->create([
            'estado' => 'activo',
            'aluno_id' => $outroAluno->id,
        ]);
        $this->outroAlunoUser->assignRole('aluno');
    }

    public function test_cria_evento_escola_e_turma(): void
    {
        $anoId = $this->scenario->anos->origem->id;
        $turmaId = $this->scenario->turmas->origem_10->id;

        $this->actingAs($this->admin)
            ->post(route('agenda.store'), [
                'titulo' => 'Feriado nacional',
                'tipo' => 'feriado',
                'inicia_em' => now()->format('Y-m-d\T10:00'),
                'ano_lectivo_id' => $anoId,
                'visibilidade' => 'escola',
            ])
            ->assertRedirect(route('agenda.index'));

        $this->actingAs($this->director)
            ->post(route('agenda.store'), [
                'titulo' => 'Reunião de pais',
                'tipo' => 'reuniao',
                'inicia_em' => now()->format('Y-m-d\T15:00'),
                'ano_lectivo_id' => $anoId,
                'visibilidade' => 'turma',
                'turma_id' => $turmaId,
            ])
            ->assertRedirect(route('agenda.index'));

        $this->assertDatabaseHas('agenda_eventos', [
            'titulo' => 'Feriado nacional',
            'visibilidade' => 'escola',
        ]);
        $this->assertDatabaseHas('agenda_eventos', [
            'titulo' => 'Reunião de pais',
            'visibilidade' => 'turma',
            'turma_id' => $turmaId,
        ]);
    }

    public function test_dt_nao_cria_evento_de_outra_turma(): void
    {
        $this->actingAs($this->director)
            ->post(route('agenda.store'), [
                'titulo' => 'Evento outra turma',
                'tipo' => 'aviso',
                'inicia_em' => now()->format('Y-m-d\T10:00'),
                'ano_lectivo_id' => $this->scenario->anos->origem->id,
                'visibilidade' => 'turma',
                'turma_id' => $this->scenario->turmas->origem_10_b->id,
            ])
            ->assertForbidden();
    }

    public function test_professor_sem_agenda_gerir_nao_cria_eventos(): void
    {
        $this->actingAs($this->professor)
            ->get(route('agenda.create'))
            ->assertForbidden();

        $this->actingAs($this->professor)
            ->post(route('agenda.store'), [
                'titulo' => 'Tentativa',
                'tipo' => 'outro',
                'inicia_em' => now()->format('Y-m-d\T10:00'),
                'ano_lectivo_id' => $this->scenario->anos->origem->id,
                'visibilidade' => 'escola',
            ])
            ->assertForbidden();
    }

    public function test_portal_aluno_ve_eventos_escola_e_sua_turma(): void
    {
        $anoId = $this->scenario->anos->origem->id;

        AgendaEvento::create([
            'titulo' => 'Dia aberto',
            'tipo' => 'actividade',
            'inicia_em' => now(),
            'ano_lectivo_id' => $anoId,
            'visibilidade' => 'escola',
            'criado_por' => $this->admin->id,
            'activo' => true,
        ]);

        AgendaEvento::create([
            'titulo' => 'Reunião TI10AT',
            'tipo' => 'reuniao',
            'inicia_em' => now()->addDay(),
            'ano_lectivo_id' => $anoId,
            'turma_id' => $this->scenario->turmas->origem_10->id,
            'visibilidade' => 'turma',
            'criado_por' => $this->director->id,
            'activo' => true,
        ]);

        AgendaEvento::create([
            'titulo' => 'Só TI10BT',
            'tipo' => 'aviso',
            'inicia_em' => now()->addDays(2),
            'ano_lectivo_id' => $anoId,
            'turma_id' => $this->scenario->turmas->origem_10_b->id,
            'visibilidade' => 'turma',
            'criado_por' => $this->admin->id,
            'activo' => true,
        ]);

        $this->actingAs($this->alunoUser)
            ->get(route('portal.aluno.calendario', ['tab' => 'agenda']))
            ->assertOk()
            ->assertSee('Dia aberto')
            ->assertSee('Reunião TI10AT')
            ->assertDontSee('Só TI10BT');
    }

    public function test_upload_e_download_material_autorizado(): void
    {
        Storage::fake('local');
        $anoId = $this->scenario->anos->origem->id;
        $turmaId = $this->scenario->turmas->origem_10->id;

        $this->actingAs($this->director)
            ->post(route('materiais.store'), [
                'titulo' => 'Ficha MAC',
                'ano_lectivo_id' => $anoId,
                'turma_id' => $turmaId,
                'publicado' => '1',
                'ficheiro' => UploadedFile::fake()->create('ficha.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('materiais.index'));

        $material = MaterialAcademico::first();
        $this->assertNotNull($material);

        $this->actingAs($this->alunoUser)
            ->get(route('portal.aluno.materiais.download', $material))
            ->assertOk();

        $this->actingAs($this->outroAlunoUser)
            ->get(route('portal.aluno.materiais.download', $material))
            ->assertForbidden();
    }

    public function test_soft_delete_mantem_ficheiro_em_disco(): void
    {
        Storage::fake('local');
        $anoId = $this->scenario->anos->origem->id;

        $this->actingAs($this->director)
            ->post(route('materiais.store'), [
                'titulo' => 'Ficha soft-delete',
                'ano_lectivo_id' => $anoId,
                'publicado' => '1',
                'ficheiro' => UploadedFile::fake()->create('soft.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect(route('materiais.index'));

        $material = MaterialAcademico::firstOrFail();
        $path = $material->ficheiro_path;
        $this->assertTrue(Storage::disk('local')->exists($path));

        $this->actingAs($this->director)
            ->from(route('materiais.index'))
            ->delete(route('materiais.destroy', $material))
            ->assertRedirect(route('materiais.index'));

        $this->assertSoftDeleted($material);
        $this->assertTrue(Storage::disk('local')->exists($path));
    }

    public function test_aluno_ve_material_por_disciplina_do_plano_da_turma(): void
    {
        Storage::fake('local');
        $anoId = $this->scenario->anos->origem->id;
        $disciplina = $this->scenario->disciplina;
        $this->assertNotNull($disciplina);

        $disciplina->cursos()->syncWithoutDetaching([$this->scenario->curso->ti->id]);
        $disciplina->classes()->syncWithoutDetaching([$this->scenario->classes->c10->id]);

        $outra = \App\Models\Disciplina::firstOrCreate(
            ['codigo' => 'FIS-TST'],
            ['nome' => 'Física Teste', 'activo' => true]
        );

        $this->actingAs($this->director)
            ->post(route('materiais.store'), [
                'titulo' => 'Material MAC plano',
                'ano_lectivo_id' => $anoId,
                'disciplina_id' => $disciplina->id,
                'publicado' => '1',
                'ficheiro' => UploadedFile::fake()->create('mac.pdf', 40, 'application/pdf'),
            ])
            ->assertRedirect(route('materiais.index'));

        $this->actingAs($this->director)
            ->post(route('materiais.store'), [
                'titulo' => 'Material Física fora',
                'ano_lectivo_id' => $anoId,
                'disciplina_id' => $outra->id,
                'publicado' => '1',
                'ficheiro' => UploadedFile::fake()->create('fis.pdf', 40, 'application/pdf'),
            ])
            ->assertRedirect(route('materiais.index'));

        $doPlano = MaterialAcademico::where('titulo', 'Material MAC plano')->firstOrFail();
        $fora = MaterialAcademico::where('titulo', 'Material Física fora')->firstOrFail();

        $this->actingAs($this->alunoUser)
            ->get(route('portal.aluno.materiais.download', $doPlano))
            ->assertOk();

        $this->actingAs($this->alunoUser)
            ->get(route('portal.aluno.materiais.download', $fora))
            ->assertForbidden();
    }
}
