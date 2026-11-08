<?php

namespace Tests\Feature\Secretaria;

use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\NotificacaoCampanha;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\StaffAlertaAcademicoNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CampanhaNotificacaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        AnoLectivo::create(['nome' => '2025-2026', 'estado' => AnoLectivo::ESTADO_ACTUAL]);
        Configuracao::set('ano_lectivo_actual', '2025-2026');
    }

    public function test_secretaria_envia_campanha_a_professores(): void
    {
        Notification::fake();

        $secretaria = User::factory()->create(['estado' => 'activo']);
        $secretaria->assignRole('secretaria');

        $professor = User::factory()->create(['estado' => 'activo', 'name' => 'Prof Dest']);
        $professor->assignRole('professor');

        $outro = User::factory()->create(['estado' => 'activo']);
        Role::findByName('director_turma');
        $outro->assignRole('director_turma');

        $this->actingAs($secretaria)
            ->post(route('secretaria.campanhas.store'), [
                'titulo' => 'Reunião geral',
                'mensagem' => 'Compareçam amanhã às 10h.',
                'publico' => NotificacaoCampanha::PUBLICO_PROFESSORES,
            ])
            ->assertRedirect(route('secretaria.campanhas.index'));

        Notification::assertSentTo($professor, StaffAlertaAcademicoNotification::class);
        Notification::assertNotSentTo($outro, StaffAlertaAcademicoNotification::class);

        $this->assertDatabaseHas('notificacao_campanhas', [
            'titulo' => 'Reunião geral',
            'publico' => 'professores',
            'enviados' => 1,
        ]);
    }

    public function test_professor_nao_acede_campanhas(): void
    {
        $professor = User::factory()->create(['estado' => 'activo']);
        $professor->assignRole('professor');

        $this->actingAs($professor)
            ->get(route('secretaria.campanhas.create'))
            ->assertForbidden();
    }

    public function test_campanha_turma_exige_turma(): void
    {
        $secretaria = User::factory()->create(['estado' => 'activo']);
        $secretaria->assignRole('secretaria');

        $this->actingAs($secretaria)
            ->post(route('secretaria.campanhas.store'), [
                'titulo' => 'Aviso turma',
                'mensagem' => 'Tragam documentos.',
                'publico' => NotificacaoCampanha::PUBLICO_TURMA,
            ])
            ->assertSessionHasErrors('turma_id');
    }

    public function test_campanha_turma_envia_a_alunos_da_turma(): void
    {
        Notification::fake();

        $secretaria = User::factory()->create(['estado' => 'activo']);
        $secretaria->assignRole('secretaria');

        $ano = AnoLectivo::query()->where('nome', '2025-2026')->first();
        $curso = Curso::create(['nome' => 'C', 'codigo' => 'C'.uniqid(), 'activo' => true]);
        $classe = Classe::firstOrCreate(['codigo' => 'T10'], ['nome' => '10ª', 'activo' => true]);
        $turma = Turma::create([
            'codigo' => 'TX'.uniqid(),
            'nome' => 'TX',
            'curso_id' => $curso->id,
            'classe_id' => $classe->id,
            'ano_lectivo_id' => $ano->id,
            'ano_lectivo' => '2025-2026',
            'activo' => true,
        ]);

        $alunoModel = \App\Models\Aluno::create([
            'numero_processo' => 'CAMP-1',
            'nome' => 'Aluno Camp',
            'turma_id' => $turma->id,
            'activo' => true,
            'situacao_academica' => \App\Models\Aluno::SITUACAO_MATRICULADO,
        ]);

        $userAluno = User::factory()->create([
            'estado' => 'activo',
            'aluno_id' => $alunoModel->id,
        ]);
        $userAluno->assignRole('aluno');

        $this->actingAs($secretaria)
            ->post(route('secretaria.campanhas.store'), [
                'titulo' => 'Aviso da turma',
                'mensagem' => 'Tragam o BI.',
                'publico' => NotificacaoCampanha::PUBLICO_TURMA,
                'turma_id' => $turma->id,
            ])
            ->assertRedirect();

        Notification::assertSentTo($userAluno, StaffAlertaAcademicoNotification::class);
    }
}
