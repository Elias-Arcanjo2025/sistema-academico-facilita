<?php

namespace Tests\Feature;

use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\StaffConversa;
use App\Models\StaffMensagem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemInternaTest extends TestCase
{
    use RefreshDatabase;

    private User $professor;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        AnoLectivo::create(['nome' => '2025-2026', 'estado' => AnoLectivo::ESTADO_ACTUAL]);
        Configuracao::set('ano_lectivo_actual', '2025-2026');

        $this->professor = User::factory()->create([
            'name' => 'Prof Mensagem',
            'email' => 'prof.msg@exemplo.ao',
            'estado' => 'activo',
        ]);
        $this->professor->assignRole('professor');

        $this->director = User::factory()->create([
            'name' => 'DT Mensagem',
            'email' => 'dt.msg@exemplo.ao',
            'estado' => 'activo',
        ]);
        $this->director->assignRole('director_turma');
    }

    public function test_nova_conversa_mostra_aba_colaborador(): void
    {
        $this->actingAs($this->professor)
            ->get(route('mensagens-encarregados.create', ['tipo' => 'interna']))
            ->assertOk()
            ->assertSee('Colaborador', false)
            ->assertSee('DT Mensagem', false);
    }

    public function test_professor_pode_iniciar_conversa_com_director(): void
    {
        $this->actingAs($this->professor)
            ->post(route('mensagens-internas.iniciar'), [
                'user_ids' => [$this->director->id],
                'corpo' => 'Olá, precisamos falar da turma.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('staff_conversas', [
            'user_low_id' => min($this->professor->id, $this->director->id),
            'user_high_id' => max($this->professor->id, $this->director->id),
        ]);

        $this->assertDatabaseHas('staff_mensagens', [
            'user_id' => $this->professor->id,
            'corpo' => 'Olá, precisamos falar da turma.',
        ]);
    }

    public function test_destinatario_pode_responder(): void
    {
        $conversa = StaffConversa::create([
            'user_low_id' => min($this->professor->id, $this->director->id),
            'user_high_id' => max($this->professor->id, $this->director->id),
            'last_message_at' => now(),
        ]);

        StaffMensagem::create([
            'conversa_id' => $conversa->id,
            'user_id' => $this->professor->id,
            'corpo' => 'Primeira',
        ]);

        $this->actingAs($this->director)
            ->post(route('mensagens-internas.store', $conversa), [
                'corpo' => 'Combinado, falamos amanhã.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('staff_mensagens', [
            'conversa_id' => $conversa->id,
            'user_id' => $this->director->id,
            'corpo' => 'Combinado, falamos amanhã.',
        ]);
    }

    public function test_terceiro_nao_acede_a_conversa_alheia(): void
    {
        $outro = User::factory()->create(['estado' => 'activo']);
        $outro->assignRole('secretaria');

        $conversa = StaffConversa::create([
            'user_low_id' => min($this->professor->id, $this->director->id),
            'user_high_id' => max($this->professor->id, $this->director->id),
        ]);

        $this->actingAs($outro)
            ->get(route('mensagens-internas.show', $conversa))
            ->assertForbidden();
    }
}
