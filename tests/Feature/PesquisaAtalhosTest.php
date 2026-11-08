<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PesquisaAtalhosTest extends TestCase
{
    use RefreshDatabase;

    public function test_pesquisa_vazia_devolve_accoes_rapidas_com_atalhos(): void
    {
        Permission::firstOrCreate(['name' => 'notas.lancar', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'pautas.ver', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'assistente.usar', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'professor', 'guard_name' => 'web']);

        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole('professor');
        $user->givePermissionTo(['notas.lancar', 'pautas.ver', 'assistente.usar']);

        $this->actingAs($user)
            ->getJson(route('pesquisa.global', ['q' => '']))
            ->assertOk()
            ->assertJsonFragment(['shortcut' => 'Ctrl+Shift+D'])
            ->assertJsonFragment(['shortcut' => 'Ctrl+Shift+N'])
            ->assertJsonFragment(['shortcut' => 'Ctrl+Shift+A']);
    }
}
