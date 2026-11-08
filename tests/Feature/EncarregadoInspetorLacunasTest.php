<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\Encarregado;
use App\Models\Ocorrencia;
use App\Models\PortalConversa;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\StaffAlertaAcademicoNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EncarregadoInspetorLacunasTest extends TestCase
{
    use RefreshDatabase;

    private AnoLectivo $ano;

    private Turma $turma;

    private Aluno $aluno;

    private User $encarregadoUser;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->ano = AnoLectivo::create(['nome' => '2025-2026', 'estado' => AnoLectivo::ESTADO_ACTUAL]);
        Configuracao::set('ano_lectivo_actual', '2025-2026');

        $curso = Curso::create(['nome' => 'C', 'codigo' => 'C'.uniqid(), 'activo' => true]);
        $classe = Classe::firstOrCreate(['codigo' => 'E10'], ['nome' => '10ª', 'activo' => true]);
        $this->turma = Turma::create([
            'codigo' => 'TE'.uniqid(),
            'nome' => 'TE',
            'curso_id' => $curso->id,
            'classe_id' => $classe->id,
            'ano_lectivo_id' => $this->ano->id,
            'ano_lectivo' => '2025-2026',
            'activo' => true,
        ]);

        $this->aluno = Aluno::create([
            'numero_processo' => 'ENC-1',
            'nome' => 'Filho Teste',
            'turma_id' => $this->turma->id,
            'activo' => true,
            'situacao_academica' => Aluno::SITUACAO_MATRICULADO,
        ]);

        $encarregado = Encarregado::create([
            'nome' => 'Pai Teste',
            'telefone' => '900000000',
            'email' => 'pai@exemplo.ao',
            'activo' => true,
        ]);
        $encarregado->alunos()->attach($this->aluno->id, [
            'parentesco' => 'pai',
            'principal' => true,
            'pode_ver_notas' => true,
            'pode_assinar_boletim' => true,
        ]);

        $this->encarregadoUser = User::factory()->create([
            'estado' => 'activo',
            'encarregado_id' => $encarregado->id,
            'name' => 'Pai Portal',
        ]);
        $this->encarregadoUser->assignRole('encarregado');

        $this->director = User::factory()->create(['estado' => 'activo', 'name' => 'DT Teste']);
        $this->director->assignRole('director_turma');
        $this->director->turmasComoDirector()->attach($this->turma->id, [
            'ano_lectivo' => '2025-2026',
            'ano_lectivo_id' => $this->ano->id,
            'activo' => true,
        ]);
    }

    public function test_encarregado_ve_inbox_notificacoes(): void
    {
        $this->encarregadoUser->notify(new StaffAlertaAcademicoNotification('campanha_sistema', [
            'titulo' => 'Aviso escola',
            'mensagem' => 'Reunião de pais.',
            'url' => route('portal.encarregado.dashboard'),
        ]));

        $this->actingAs($this->encarregadoUser)
            ->get(route('portal.encarregado.notificacoes'))
            ->assertOk()
            ->assertSee('Aviso escola');
    }

    public function test_encarregado_e_dt_conversam(): void
    {
        Notification::fake();

        $this->actingAs($this->encarregadoUser)
            ->post(route('portal.encarregado.mensagens.store', $this->aluno), [
                'corpo' => 'Olá, gostaria de falar sobre o meu filho.',
            ])
            ->assertRedirect(route('portal.encarregado.mensagens.show', $this->aluno));

        $conversa = PortalConversa::query()
            ->where('aluno_id', $this->aluno->id)
            ->where('encarregado_user_id', $this->encarregadoUser->id)
            ->first();

        $this->assertNotNull($conversa);
        $this->assertDatabaseHas('portal_mensagens', [
            'conversa_id' => $conversa->id,
            'user_id' => $this->encarregadoUser->id,
            'corpo' => 'Olá, gostaria de falar sobre o meu filho.',
        ]);
        Notification::assertSentTo($this->director, StaffAlertaAcademicoNotification::class);

        $this->actingAs($this->encarregadoUser)
            ->get(route('portal.encarregado.mensagens.show', $this->aluno))
            ->assertOk()
            ->assertSee('Olá, gostaria de falar sobre o meu filho.', false)
            ->assertSee('enc-chat__thread', false);

        $this->actingAs($this->director)
            ->post(route('mensagens-encarregados.store', $conversa), [
                'corpo' => 'Combinado, podemos falar amanhã.',
            ])
            ->assertRedirect();

        Notification::assertSentTo($this->encarregadoUser, StaffAlertaAcademicoNotification::class);

        $this->actingAs($this->director)
            ->get(route('mensagens-encarregados.show', $conversa))
            ->assertOk()
            ->assertSee('Combinado, podemos falar amanhã.', false);
    }

    public function test_encarregado_nao_envia_sem_director_de_turma(): void
    {
        \App\Models\DirectorTurma::query()->where('turma_id', $this->turma->id)->delete();

        $this->actingAs($this->encarregadoUser)
            ->from(route('portal.encarregado.mensagens.show', $this->aluno))
            ->post(route('portal.encarregado.mensagens.store', $this->aluno), [
                'corpo' => 'Mensagem sem destinatário.',
            ])
            ->assertRedirect(route('portal.encarregado.mensagens.show', $this->aluno))
            ->assertSessionHasErrors('corpo');

        $this->assertDatabaseMissing('portal_mensagens', [
            'corpo' => 'Mensagem sem destinatário.',
        ]);
    }

    public function test_director_inicia_conversa_com_encarregado(): void
    {
        Notification::fake();

        $this->actingAs($this->director)
            ->get(route('mensagens-encarregados.create'))
            ->assertOk()
            ->assertSee($this->aluno->nome, false);

        $this->actingAs($this->director)
            ->post(route('mensagens-encarregados.iniciar'), [
                'aluno_ids' => [$this->aluno->id],
                'corpo' => 'Bom dia, gostaria de falar consigo sobre o comportamento do aluno.',
            ])
            ->assertRedirect();

        $conversa = PortalConversa::query()
            ->where('aluno_id', $this->aluno->id)
            ->where('encarregado_user_id', $this->encarregadoUser->id)
            ->first();

        $this->assertNotNull($conversa);
        $this->assertDatabaseHas('portal_mensagens', [
            'conversa_id' => $conversa->id,
            'user_id' => $this->director->id,
            'corpo' => 'Bom dia, gostaria de falar consigo sobre o comportamento do aluno.',
        ]);
        Notification::assertSentTo($this->encarregadoUser, StaffAlertaAcademicoNotification::class);
    }

    public function test_inspetor_regista_advertencia_e_notifica_encarregado(): void
    {
        Notification::fake();

        $inspetor = User::factory()->create(['estado' => 'activo']);
        $inspetor->assignRole('inspetor');

        $this->assertArrayHasKey(Ocorrencia::TIPO_ADVERTENCIA, Ocorrencia::tipos());
        $this->assertArrayHasKey(Ocorrencia::TIPO_COMPORTAMENTO, Ocorrencia::tipos());

        $this->actingAs($inspetor)
            ->post(route('ocorrencias.store'), [
                'aluno_id' => $this->aluno->id,
                'data' => now()->toDateString(),
                'tipo' => Ocorrencia::TIPO_ADVERTENCIA,
                'severidade' => Ocorrencia::SEV_MEDIA,
                'descricao' => 'Comportamento inadequado na aula.',
                'medida' => 'Advertência escrita',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ocorrencias', [
            'aluno_id' => $this->aluno->id,
            'tipo' => 'advertencia',
            'medida' => 'Advertência escrita',
        ]);

        Notification::assertSentTo($this->encarregadoUser, StaffAlertaAcademicoNotification::class);
    }
}
