<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class AssistenteContextualNotasTest extends TestCase
{
    use RefreshDatabase;

    public function test_lancar_notas_mostra_dica_contextual(): void
    {
        Permission::firstOrCreate(['name' => 'notas.lancar']);
        Role::firstOrCreate(['name' => 'professor'])->givePermissionTo('notas.lancar');

        $scenario = (new TransicaoAnoScenarioBuilder)->build();
        $professor = $scenario->users->professor;
        $professor->assignRole('professor');

        $this->actingAs($professor)
            ->get(route('notas.lancar'))
            ->assertOk()
            ->assertSee('facilita-contextual-hint', false)
            ->assertSee('trimestre aberto', false);
    }
}
