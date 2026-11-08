<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\StaffAlertaAcademicoNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffNotificacoesTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin_full']);

        $this->staff = User::factory()->create(['estado' => 'activo']);
        $this->staff->assignRole('admin_full');
    }

    public function test_abrir_notificacao_marca_como_lida_e_redirecciona(): void
    {
        $destino = route('dashboard');

        $this->staff->notify(new StaffAlertaAcademicoNotification('alunos_criticos', [
            'titulo' => 'Alunos em situação crítica',
            'mensagem' => '127 aluno(s) com 3 ou mais negativas nas suas turmas.',
            'url' => $destino,
        ]));

        $notification = $this->staff->unreadNotifications()->first();
        $this->assertNotNull($notification);

        $this->actingAs($this->staff)
            ->get(route('notificacoes.abrir', $notification->id))
            ->assertRedirect($destino);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_campainha_usa_rota_abrir(): void
    {
        $this->staff->notify(new StaffAlertaAcademicoNotification('teste', [
            'titulo' => 'Teste',
            'mensagem' => 'Mensagem de teste',
            'url' => route('dashboard'),
        ]));

        $notification = $this->staff->unreadNotifications()->first();

        $this->actingAs($this->staff)
            ->get(route('notificacoes.index'))
            ->assertOk()
            ->assertSee(route('notificacoes.abrir', $notification->id), false);
    }

    public function test_marcar_todas_como_lidas(): void
    {
        $this->staff->notify(new StaffAlertaAcademicoNotification('a', [
            'titulo' => 'A',
            'mensagem' => 'A',
            'url' => route('dashboard'),
        ]));
        $this->staff->notify(new StaffAlertaAcademicoNotification('b', [
            'titulo' => 'B',
            'mensagem' => 'B',
            'url' => route('dashboard'),
        ]));

        $this->assertSame(2, $this->staff->unreadNotifications()->count());

        $this->actingAs($this->staff)
            ->post(route('notificacoes.marcar-todas-lidas'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, $this->staff->fresh()->unreadNotifications()->count());
    }
}
