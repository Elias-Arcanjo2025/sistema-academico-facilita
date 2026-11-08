<?php

namespace Tests\Feature;

use App\Models\Configuracao;
use App\Models\User;
use App\Services\Assistente\VirtualAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class PortalAlunoTest extends TestCase
{
    use RefreshDatabase;

    private User $alunoUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'aluno']);

        $scenario = (new TransicaoAnoScenarioBuilder)->build();
        $aluno = $scenario->alunos->aprovado;

        $this->alunoUser = User::factory()->create([
            'estado' => 'activo',
            'aluno_id' => $aluno->id,
            'email' => '900000001',
        ]);
        $this->alunoUser->assignRole('aluno');

        Configuracao::set('portal_aluno_notas_activo', '1', 'test');
    }

    public function test_aluno_acede_dashboard(): void
    {
        $this->actingAs($this->alunoUser)
            ->get(route('portal.aluno.dashboard'))
            ->assertOk()
            ->assertSee('Portal do Aluno', false)
            ->assertSee('Aluno Aprovado', false)
            ->assertSee('Regras de avaliação', false);
    }

    public function test_aluno_acede_notas(): void
    {
        $this->actingAs($this->alunoUser)
            ->get(route('portal.aluno.notas'))
            ->assertOk()
            ->assertSee('As Minhas Notas', false);
    }

    public function test_aluno_acede_perfil(): void
    {
        $this->actingAs($this->alunoUser)
            ->get(route('portal.aluno.perfil'))
            ->assertOk()
            ->assertSee('Meu perfil', false)
            ->assertSee('Alterar palavra-passe', false);
    }

    public function test_utilizador_sem_cargo_aluno_nao_acede_portal(): void
    {
        $outro = User::factory()->create(['estado' => 'activo']);

        $this->actingAs($outro)
            ->get(route('portal.aluno.dashboard'))
            ->assertForbidden();
    }

    public function test_aluno_pode_enviar_mensagem_ao_assistente_sem_redireccionamento(): void
    {
        Permission::firstOrCreate(['name' => 'assistente.usar', 'guard_name' => 'web']);
        $this->alunoUser->givePermissionTo('assistente.usar');

        $this->mock(VirtualAssistantService::class, function ($mock) {
            $mock->shouldReceive('responder')
                ->once()
                ->andReturn([
                    'conversa_id' => 1,
                    'resposta' => 'As suas notas deste trimestre estão disponíveis no portal.',
                ]);
        });

        $this->actingAs($this->alunoUser)
            ->postJson(route('assistente.mensagem'), [
                'mensagem' => 'Como estão as minhas notas este trimestre?',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'conversa_id' => 1,
            ]);
    }
}
