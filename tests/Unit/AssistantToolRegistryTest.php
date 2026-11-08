<?php

namespace Tests\Unit;

use App\Services\Assistente\AssistantToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantToolRegistryTest extends TestCase
{
    use RefreshDatabase;
    public function test_catalogo_tem_tools_documentadas(): void
    {
        $catalogo = app(AssistantToolRegistry::class)->catalogo();

        $this->assertArrayHasKey('horario', $catalogo);
        $this->assertArrayHasKey('calendario_provas', $catalogo);
        $this->assertArrayHasKey('rankings', $catalogo);
        $this->assertArrayHasKey('aluno_sessao', $catalogo);
    }

    public function test_resolver_inclui_horario_quando_mencionado(): void
    {
        Role::firstOrCreate(['name' => 'professor', 'guard_name' => 'web']);

        $user = \App\Models\User::factory()->create(['estado' => 'activo']);
        $user->assignRole('professor');

        $tools = app(AssistantToolRegistry::class)->resolverTools($user, 'Qual é o horário da turma TI10?');

        $this->assertContains('sistema', $tools);
        $this->assertContains('horario', $tools);
    }
}
