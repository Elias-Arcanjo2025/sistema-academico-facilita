<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\User;
use App\Services\EstatisticasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class ConsolidarSituacoesAcademicasTest extends TestCase
{
    use RefreshDatabase;

    private TransicaoAnoScenarioBuilder $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin_full']);
        $this->scenario = (new TransicaoAnoScenarioBuilder)->build();
        Configuracao::set('max_disciplinas_reprovacao', '0', 'test');
    }

    public function test_consolidacao_aprova_aluno_com_cfd_suficiente(): void
    {
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole('admin_full');
        $this->actingAs($admin);

        $result = app(EstatisticasService::class)->consolidarSituacoesAcademicas(
            $this->scenario->anos->origem->id
        );

        $this->assertGreaterThan(0, $result['atualizados']);
        $this->assertSame(
            Aluno::SITUACAO_APROVADO,
            $this->scenario->alunos->aprovado->fresh()->situacao_academica
        );
    }

    public function test_consolidacao_reprova_aluno_com_cfd_negativa(): void
    {
        $this->scenario->withAlunoReprovado();

        $admin = User::factory()->create(['estado' => 'activo']);
        $this->actingAs($admin);

        $result = app(EstatisticasService::class)->consolidarSituacoesAcademicas(
            $this->scenario->anos->origem->id
        );

        $this->assertSame(
            Aluno::SITUACAO_REPROVADO,
            $this->scenario->alunos->reprovado->fresh()->situacao_academica
        );
        $this->assertGreaterThan(0, $result['reprovados']);
    }

    public function test_consolidacao_mantem_aluno_sem_cfd(): void
    {
        Nota::where('aluno_id', $this->scenario->alunos->aprovado->id)->delete();

        $admin = User::factory()->create(['estado' => 'activo']);
        $this->actingAs($admin);

        $result = app(EstatisticasService::class)->consolidarSituacoesAcademicas(
            $this->scenario->anos->origem->id
        );

        $this->assertSame(Aluno::SITUACAO_MATRICULADO, $this->scenario->alunos->aprovado->fresh()->situacao_academica);
        $this->assertGreaterThan(0, $result['mantidos']);
    }

    public function test_http_consolidar_requer_admin(): void
    {
        $professor = User::factory()->create(['estado' => 'activo']);
        Role::firstOrCreate(['name' => 'professor']);
        $professor->assignRole('professor');

        $this->actingAs($professor)
            ->post(route('estatisticas.consolidar'), [
                'ano_lectivo_id' => $this->scenario->anos->origem->id,
                'confirmar' => '1',
            ])
            ->assertForbidden();
    }
}
