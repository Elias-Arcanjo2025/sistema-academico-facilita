<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UtilizadorTipoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'aluno']);
        Role::firstOrCreate(['name' => 'professor']);
        Role::firstOrCreate(['name' => 'encarregado']);
    }

    public function test_e_utilizador_aluno_com_cargo_ou_vinculo(): void
    {
        $comCargo = User::factory()->create(['aluno_id' => null]);
        $comCargo->assignRole('aluno');

        $professor = User::factory()->create(['aluno_id' => null]);
        $professor->assignRole('professor');

        $this->assertTrue($comCargo->eUtilizadorAluno());
        $this->assertFalse($professor->eUtilizadorAluno());
    }

    public function test_scopes_separam_alunos_funcionarios_e_encarregados(): void
    {
        $aluno = User::factory()->create(['aluno_id' => null, 'encarregado_id' => null]);
        $aluno->assignRole('aluno');

        $professor = User::factory()->create(['aluno_id' => null, 'encarregado_id' => null]);
        $professor->assignRole('professor');

        $encarregado = User::factory()->create(['aluno_id' => null, 'encarregado_id' => null]);
        $encarregado->assignRole('encarregado');

        $this->assertSame(1, User::utilizadoresAlunos()->count());
        $this->assertSame(1, User::funcionariosEscola()->count());
        $this->assertSame(1, User::utilizadoresEncarregados()->count());
        $this->assertTrue($encarregado->eUtilizadorEncarregado());
        $this->assertFalse($professor->eUtilizadorEncarregado());
    }
}
