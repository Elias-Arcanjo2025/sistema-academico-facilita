<?php

namespace Tests\Feature;

use App\Models\Configuracao;
use App\Models\DirectorTurma;
use App\Models\Ocorrencia;
use App\Models\PresencaRegisto;
use App\Models\TurmaHorario;
use App\Models\User;
use App\Services\PresencaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class AssiduidadeOcorrenciasTest extends TestCase
{
    use RefreshDatabase;

    private object $scenario;

    private TurmaHorario $slot;

    private User $professor;

    private User $outroProfessor;

    private User $director;

    private User $inspetor;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'presencas.ver', 'presencas.lancar', 'presencas.justificar',
            'ocorrencias.ver', 'ocorrencias.registar', 'ocorrencias.resolver',
            'alunos.ver', 'turmas.ver',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        foreach (['professor', 'director_turma', 'inspetor', 'aluno', 'admin_full'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        Role::findByName('professor')->syncPermissions([
            'presencas.ver', 'presencas.lancar',
            'ocorrencias.ver', 'ocorrencias.registar',
            'alunos.ver',
        ]);
        Role::findByName('director_turma')->syncPermissions([
            'presencas.ver', 'presencas.lancar', 'presencas.justificar',
            'ocorrencias.ver', 'ocorrencias.registar', 'ocorrencias.resolver',
            'alunos.ver', 'turmas.ver',
        ]);
        Role::findByName('inspetor')->syncPermissions([
            'presencas.ver', 'presencas.lancar', 'presencas.justificar',
            'ocorrencias.ver', 'ocorrencias.registar', 'ocorrencias.resolver',
            'alunos.ver', 'turmas.ver',
        ]);

        $this->scenario = (new TransicaoAnoScenarioBuilder)->build();
        Configuracao::set('trimestre_actual', '1', 'test');

        $this->professor = $this->scenario->users->professor;
        $this->professor->assignRole('professor');

        $this->outroProfessor = User::factory()->create(['estado' => 'activo']);
        $this->outroProfessor->assignRole('professor');

        $this->director = User::factory()->create(['estado' => 'activo']);
        $this->director->assignRole('director_turma');
        DirectorTurma::create([
            'user_id' => $this->director->id,
            'turma_id' => $this->scenario->turmas->origem_10->id,
            'ano_lectivo' => TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            'activo' => true,
        ]);

        $this->inspetor = User::factory()->create(['estado' => 'activo']);
        $this->inspetor->assignRole('inspetor');

        // Segunda-feira 08:00 — alinhar data do teste a uma segunda
        $this->slot = TurmaHorario::create([
            'turma_id' => $this->scenario->turmas->origem_10->id,
            'disciplina_id' => $this->scenario->disciplina->id,
            'professor_id' => $this->professor->id,
            'ano_lectivo_id' => $this->scenario->anos->origem->id,
            'dia_semana' => 1,
            'hora_inicio' => '08:00:00',
            'hora_fim' => '08:45:00',
        ]);
    }

    private function proximaSegunda(): string
    {
        return Carbon::now()->next(Carbon::MONDAY)->toDateString();
    }

    public function test_professor_lanca_chamada_por_aula(): void
    {
        $data = $this->proximaSegunda();
        $aluno = $this->scenario->alunos->aprovado;

        $this->actingAs($this->professor)
            ->post(route('presencas.store', $this->slot), [
                'data' => $data,
                'estados' => [
                    $aluno->id => PresencaRegisto::FALTA_INJUSTIFICADA,
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('presenca_registos', [
            'aluno_id' => $aluno->id,
            'estado' => PresencaRegisto::FALTA_INJUSTIFICADA,
        ]);
    }

    public function test_professor_nao_lanca_aula_alheia(): void
    {
        $data = $this->proximaSegunda();

        $this->actingAs($this->outroProfessor)
            ->post(route('presencas.store', $this->slot), [
                'data' => $data,
                'estados' => [
                    $this->scenario->alunos->aprovado->id => PresencaRegisto::PRESENTE,
                ],
            ])
            ->assertForbidden();
    }

    public function test_director_justifica_falta(): void
    {
        $data = $this->proximaSegunda();
        $aluno = $this->scenario->alunos->aprovado;

        $aula = app(PresencaService::class)->gravarChamada(
            $this->professor,
            $this->slot,
            $data,
            [$aluno->id => PresencaRegisto::FALTA_INJUSTIFICADA],
        );

        $registo = $aula->registos()->where('aluno_id', $aluno->id)->firstOrFail();

        $this->actingAs($this->director)
            ->post(route('presencas.justificar', $registo), [
                'justificacao' => 'Atestado médico',
            ])
            ->assertRedirect();

        $this->assertSame(PresencaRegisto::FALTA_JUSTIFICADA, $registo->fresh()->estado);
        $this->assertSame('Atestado médico', $registo->fresh()->justificacao);
    }

    public function test_ocorrencia_criar_e_resolver(): void
    {
        $aluno = $this->scenario->alunos->aprovado;

        $this->actingAs($this->professor)
            ->post(route('ocorrencias.store'), [
                'aluno_id' => $aluno->id,
                'data' => now()->toDateString(),
                'tipo' => Ocorrencia::TIPO_INDISCIPLINA,
                'severidade' => Ocorrencia::SEV_MEDIA,
                'descricao' => 'Conversas excessivas na aula.',
            ])
            ->assertRedirect();

        $ocorrencia = Ocorrencia::query()->where('aluno_id', $aluno->id)->firstOrFail();

        $this->actingAs($this->director)
            ->post(route('ocorrencias.resolver', $ocorrencia), [
                'medida' => 'Advertência verbal',
            ])
            ->assertRedirect();

        $this->assertSame(Ocorrencia::ESTADO_RESOLVIDA, $ocorrencia->fresh()->estado);
    }

    public function test_inspetor_ve_painel(): void
    {
        $this->actingAs($this->inspetor)
            ->get(route('inspetor.painel'))
            ->assertOk()
            ->assertSee('Painel do Inspetor', false);
    }

    public function test_contagem_faltas_ji_para_pauta(): void
    {
        $data = $this->proximaSegunda();
        $aluno = $this->scenario->alunos->aprovado;

        app(PresencaService::class)->gravarChamada(
            $this->professor,
            $this->slot,
            $data,
            [$aluno->id => PresencaRegisto::FALTA_INJUSTIFICADA],
        );

        $mapa = app(PresencaService::class)->mapaFaltasTurma(
            (int) $this->scenario->turmas->origem_10->id,
            1,
            (int) $this->scenario->anos->origem->id,
        );

        $this->assertSame(1, $mapa[$aluno->id][$this->scenario->disciplina->id]['i'] ?? 0);
        $this->assertSame(0, $mapa[$aluno->id][$this->scenario->disciplina->id]['j'] ?? 0);
    }

    public function test_portal_aluno_consulta_assiduidade(): void
    {
        Role::firstOrCreate(['name' => 'aluno']);
        $aluno = $this->scenario->alunos->aprovado;
        $alunoUser = User::factory()->create([
            'estado' => 'activo',
            'aluno_id' => $aluno->id,
            'email' => '900099001',
        ]);
        $alunoUser->assignRole('aluno');

        $data = $this->proximaSegunda();
        app(PresencaService::class)->gravarChamada(
            $this->professor,
            $this->slot,
            $data,
            [$aluno->id => PresencaRegisto::FALTA_INJUSTIFICADA],
        );

        $this->actingAs($alunoUser)
            ->get(route('portal.aluno.assiduidade'))
            ->assertOk()
            ->assertSee('Assiduidade', false)
            ->assertSee('Faltas (I)', false);
    }
}
