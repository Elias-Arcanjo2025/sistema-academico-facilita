<?php

namespace Tests\Feature;

use App\Models\AssistenteConversa;
use App\Models\AssistenteMensagem;
use App\Models\Encarregado;
use App\Models\User;
use App\Services\Assistente\VirtualAssistantService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistenteMelhoriasTest extends TestCase
{
    use RefreshDatabase;

    public function test_utilizador_lista_e_reabre_conversas(): void
    {
        Permission::firstOrCreate(['name' => 'assistente.usar', 'guard_name' => 'web']);
        $user = User::factory()->create(['estado' => 'activo']);
        $user->givePermissionTo('assistente.usar');

        $conversa = AssistenteConversa::create([
            'user_id' => $user->id,
            'titulo' => 'Pergunta sobre notas',
        ]);
        AssistenteMensagem::create([
            'conversa_id' => $conversa->id,
            'papel' => 'user',
            'conteudo' => 'Como estão as minhas notas?',
        ]);
        AssistenteMensagem::create([
            'conversa_id' => $conversa->id,
            'papel' => 'assistant',
            'conteudo' => 'As suas notas estão disponíveis no portal.',
        ]);

        $this->actingAs($user)
            ->getJson(route('assistente.conversas'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('conversas.0.id', $conversa->id);

        $this->actingAs($user)
            ->getJson(route('assistente.conversa.historico', $conversa))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'mensagens');
    }

    public function test_feedback_util_e_inutil(): void
    {
        Permission::firstOrCreate(['name' => 'assistente.usar', 'guard_name' => 'web']);
        $user = User::factory()->create(['estado' => 'activo']);
        $user->givePermissionTo('assistente.usar');

        $conversa = AssistenteConversa::create(['user_id' => $user->id, 'titulo' => 'Teste']);
        $msg = AssistenteMensagem::create([
            'conversa_id' => $conversa->id,
            'papel' => 'assistant',
            'conteudo' => 'Resposta',
        ]);

        $this->actingAs($user)
            ->postJson(route('assistente.feedback', $msg), ['feedback' => 'util'])
            ->assertOk()
            ->assertJsonPath('feedback', 'util');

        $this->assertDatabaseHas('assistente_mensagens', [
            'id' => $msg->id,
            'feedback' => 'util',
        ]);
    }

    public function test_encarregado_pode_usar_assistente_com_contexto(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $encarregado = Encarregado::query()->create([
            'nome' => 'Ana Encarregada',
            'telefone' => '900111222',
            'email' => 'ana.enc@example.com',
            'activo' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Ana Encarregada',
            'email' => 'ana.enc@example.com',
            'encarregado_id' => $encarregado->id,
            'estado' => 'activo',
            'must_change_password' => false,
        ]);
        $user->syncRoles(['encarregado']);

        $this->assertTrue($user->can('assistente.usar'));

        $this->mock(VirtualAssistantService::class, function ($mock) {
            $mock->shouldReceive('responder')
                ->once()
                ->andReturn([
                    'conversa_id' => 1,
                    'mensagem_id' => 10,
                    'resposta' => 'Os seus educandos estão no portal.',
                    'acoes' => [],
                    'tokens_in' => 0,
                    'tokens_out' => 0,
                ]);
        });

        $this->actingAs($user)
            ->postJson(route('assistente.mensagem'), [
                'mensagem' => 'Como estão as notas dos meus educandos?',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_meta_devolve_sugestoes_contextuais(): void
    {
        Permission::firstOrCreate(['name' => 'assistente.usar', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'aluno', 'guard_name' => 'web']);

        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole('aluno');
        $user->givePermissionTo('assistente.usar');

        $this->actingAs($user)
            ->getJson(route('assistente.meta', ['route' => 'portal.aluno.notas']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['sugestoes', 'acoes']);
    }

    public function test_auditoria_assistente_requer_permissao(): void
    {
        $user = User::factory()->create(['estado' => 'activo']);

        $this->actingAs($user)
            ->get(route('assistente.auditoria'))
            ->assertForbidden();
    }
}
