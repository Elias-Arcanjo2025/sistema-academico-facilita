<?php

namespace Tests\Feature;

use App\Models\Configuracao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class LimpezaRedundanciasTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_provas_redireciona_para_calendario(): void
    {
        Role::firstOrCreate(['name' => 'aluno']);
        $scenario = (new TransicaoAnoScenarioBuilder)->build();
        $aluno = $scenario->alunos->aprovado;
        $user = User::factory()->create([
            'estado' => 'activo',
            'aluno_id' => $aluno->id,
        ]);
        $user->assignRole('aluno');
        Configuracao::set('ano_lectivo_actual', TransicaoAnoScenarioBuilder::ANO_ORIGEM, 'test');

        $this->actingAs($user)
            ->get(route('portal.aluno.provas'))
            ->assertRedirect(route('portal.aluno.calendario', ['tab' => 'provas']));
    }

    public function test_secretaria_nao_usa_alunos_create(): void
    {
        foreach (['alunos.criar', 'secretaria.matriculas.gerir', 'secretaria.ver', 'alunos.ver'] as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }
        Role::firstOrCreate(['name' => 'secretaria'])->syncPermissions([
            'secretaria.ver', 'secretaria.matriculas.gerir', 'alunos.ver',
        ]);

        (new TransicaoAnoScenarioBuilder)->build();
        Configuracao::set('ano_lectivo_actual', TransicaoAnoScenarioBuilder::ANO_ORIGEM, 'test');

        $secretaria = User::factory()->create(['estado' => 'activo']);
        $secretaria->assignRole('secretaria');

        // Sem alunos.criar — policy bloqueia create directo
        $this->actingAs($secretaria)
            ->get(route('alunos.create'))
            ->assertForbidden();
    }
}
