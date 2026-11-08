<?php

namespace Tests\Feature;

use App\Models\Configuracao;
use App\Models\Aluno;
use App\Models\Encarregado;
use App\Models\User;
use App\Notifications\AlunoNotaPublicadaNotification;
use App\Notifications\EncarregadoNotaPublicadaNotification;
use App\Services\NotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class PortalNotificacoesTest extends TestCase
{
    use RefreshDatabase;

    private User $alunoUser;

    private object $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'aluno']);

        $this->scenario = (new TransicaoAnoScenarioBuilder)->build();
        $aluno = $this->scenario->alunos->aprovado;

        $this->alunoUser = User::factory()->create([
            'estado' => 'activo',
            'aluno_id' => $aluno->id,
            'email' => '900000001',
        ]);
        $this->alunoUser->assignRole('aluno');

        Configuracao::set('portal_aluno_notas_activo', '1', 'test');
    }

    public function test_campainha_aparece_no_portal_com_contagem(): void
    {
        $nota = $this->alunoUser->aluno->notas()->first();
        $this->assertNotNull($nota);

        $this->alunoUser->notify(new AlunoNotaPublicadaNotification($nota));

        $this->actingAs($this->alunoUser)
            ->get(route('portal.aluno.dashboard'))
            ->assertOk()
            ->assertSee('id="notificacoesPortal"', false)
            ->assertSee('portal-notificacao-count', false);
    }

    public function test_lista_notificacoes_paginada(): void
    {
        $nota = $this->alunoUser->aluno->notas()->first();
        $this->alunoUser->notify(new AlunoNotaPublicadaNotification($nota));

        $this->actingAs($this->alunoUser)
            ->get(route('portal.aluno.notificacoes'))
            ->assertOk()
            ->assertSee('Notificações', false)
            ->assertSee('Nova nota em', false);
    }

    public function test_abrir_notificacao_marca_como_lida_e_redirecciona(): void
    {
        $nota = $this->alunoUser->aluno->notas()->first();
        $this->alunoUser->notify(new AlunoNotaPublicadaNotification($nota));

        $notification = $this->alunoUser->unreadNotifications()->first();
        $this->assertNotNull($notification);

        $this->actingAs($this->alunoUser)
            ->get(route('portal.aluno.notificacoes.abrir', $notification->id))
            ->assertRedirect(route('portal.aluno.notas'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_marcar_todas_como_lidas(): void
    {
        $nota = $this->alunoUser->aluno->notas()->first();
        $this->alunoUser->notify(new AlunoNotaPublicadaNotification($nota));
        $this->alunoUser->notify(new AlunoNotaPublicadaNotification($nota));

        $this->assertSame(2, $this->alunoUser->unreadNotifications()->count());

        $this->actingAs($this->alunoUser)
            ->post(route('portal.aluno.notificacoes.marcar-todas-lidas'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, $this->alunoUser->fresh()->unreadNotifications()->count());
    }

    public function test_aluno_nao_pode_abrir_notificacao_de_outro_utilizador(): void
    {
        $outroAluno = Aluno::create([
            'numero_processo' => 'PROC-ISO',
            'nome' => 'Outro Aluno',
            'data_nascimento' => '2008-01-01',
            'genero' => 'F',
            'turma_id' => $this->alunoUser->aluno->turma_id,
            'numero_ordem' => 99,
            'activo' => true,
        ]);
        $outroUser = User::factory()->create([
            'estado' => 'activo',
            'aluno_id' => $outroAluno->id,
            'email' => '900000002',
        ]);
        $outroUser->assignRole('aluno');

        $nota = $this->alunoUser->aluno->notas()->first();
        $this->alunoUser->notify(new AlunoNotaPublicadaNotification($nota));

        $notificationId = $this->alunoUser->unreadNotifications()->first()->id;

        $this->actingAs($outroUser)
            ->get(route('portal.aluno.notificacoes.abrir', $notificationId))
            ->assertNotFound();
    }

    public function test_lancamento_mini_pauta_notifica_aluno(): void
    {
        $aluno = $this->alunoUser->aluno;
        $this->alunoUser->unreadNotifications()->delete();

        app(NotaService::class)->gravarOuRemoverLancamento(
            15,
            $aluno->id,
            $this->scenario->disciplina->id,
            $aluno->turma_id,
            \App\Models\Nota::TIPO_MAC,
            1,
            $this->scenario->users->professor,
            Configuracao::getAnoLectivoActualId(),
        );

        $this->assertSame(1, $this->alunoUser->fresh()->unreadNotifications()->count());
    }

    public function test_lancamento_notifica_encarregado_com_pode_ver_notas(): void
    {
        Role::firstOrCreate(['name' => 'encarregado']);

        $aluno = $this->alunoUser->aluno;
        $encarregado = Encarregado::create([
            'nome' => 'Encarregado Notas',
            'telefone' => '900111222',
        ]);
        $aluno->encarregados()->attach($encarregado->id, [
            'parentesco' => 'pai',
            'principal' => true,
            'pode_ver_notas' => true,
            'pode_assinar_boletim' => true,
        ]);

        $encUser = User::factory()->create([
            'estado' => 'activo',
            'encarregado_id' => $encarregado->id,
            'must_change_password' => false,
        ]);
        $encUser->syncRoles(['encarregado']);

        $this->alunoUser->unreadNotifications()->delete();
        $encUser->unreadNotifications()->delete();

        app(NotaService::class)->gravarOuRemoverLancamento(
            14,
            $aluno->id,
            $this->scenario->disciplina->id,
            $aluno->turma_id,
            \App\Models\Nota::TIPO_PP,
            1,
            $this->scenario->users->professor,
            Configuracao::getAnoLectivoActualId(),
        );

        $this->assertSame(1, $this->alunoUser->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $encUser->fresh()->unreadNotifications()->count());
        $this->assertSame(
            EncarregadoNotaPublicadaNotification::class,
            $encUser->fresh()->unreadNotifications()->first()->type
        );
    }

    public function test_marcar_uma_notificacao_como_lida(): void
    {
        $nota = $this->alunoUser->aluno->notas()->first();
        $this->alunoUser->notify(new AlunoNotaPublicadaNotification($nota));

        $notificationId = $this->alunoUser->unreadNotifications()->first()->id;

        $this->actingAs($this->alunoUser)
            ->from(route('portal.aluno.notificacoes'))
            ->post(route('portal.aluno.notificacoes.marcar-lida', $notificationId))
            ->assertRedirect(route('portal.aluno.notificacoes'))
            ->assertSessionHas('success');

        $this->assertNotNull(
            $this->alunoUser->notifications()->where('id', $notificationId)->first()->read_at
        );
    }
}
