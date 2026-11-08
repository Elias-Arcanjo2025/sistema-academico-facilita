<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class FechoAnoWizardHttpTest extends TestCase
{
    use RefreshDatabase;

    private TransicaoAnoScenarioBuilder $scenario;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin_full']);
        Role::firstOrCreate(['name' => 'professor']);

        $this->scenario = (new TransicaoAnoScenarioBuilder)->build();
        $this->admin = User::factory()->create(['estado' => 'activo']);
        $this->admin->assignRole('admin_full');
    }

    public function test_nao_admin_recebe_403_no_wizard(): void
    {
        $professor = User::factory()->create(['estado' => 'activo']);
        $professor->assignRole('professor');

        $this->actingAs($professor)
            ->get(route('fecho-ano.index'))
            ->assertForbidden();
    }

    public function test_admin_accede_ao_wizard(): void
    {
        $this->actingAs($this->admin)
            ->get(route('fecho-ano.index'))
            ->assertOk()
            ->assertSee('Fecho de Ano Lectivo');
    }

    public function test_configurar_grava_anos_na_sessao(): void
    {
        $this->actingAs($this->admin)
            ->post(route('fecho-ano.configurar'), [
                'ano_origem' => TransicaoAnoScenarioBuilder::ANO_ORIGEM,
                'ano_destino' => TransicaoAnoScenarioBuilder::ANO_DESTINO,
            ])
            ->assertRedirect(route('fecho-ano.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('fecho_ano_origem', TransicaoAnoScenarioBuilder::ANO_ORIGEM)
            ->assertSessionHas('fecho_ano_destino', TransicaoAnoScenarioBuilder::ANO_DESTINO);
    }

    public function test_transferir_sem_configurar_redirecciona_com_erro(): void
    {
        $this->actingAs($this->admin)
            ->post(route('fecho-ano.transferir'))
            ->assertRedirect(route('fecho-ano.index'))
            ->assertSessionHas('error');
    }

    public function test_fluxo_completo_wizard_fecho_ano(): void
    {
        $this->actingAs($this->admin);

        $this->from(route('fecho-ano.index'))
            ->post(route('fecho-ano.configurar'), [
                'ano_origem' => TransicaoAnoScenarioBuilder::ANO_ORIGEM,
                'ano_destino' => TransicaoAnoScenarioBuilder::ANO_DESTINO,
            ])->assertRedirect(route('fecho-ano.index'));

        $this->from(route('fecho-ano.index'))
            ->post(route('fecho-ano.transferir'), [
                'transferir_associacoes' => '1',
                'transferir_directores' => '1',
                'transferir_coordenadores' => '1',
            ])
            ->assertRedirect(route('fecho-ano.index'))
            ->assertSessionHas('success');

        $this->from(route('fecho-ano.index'))
            ->post(route('fecho-ano.transitar'))
            ->assertRedirect(route('fecho-ano.index'))
            ->assertSessionHas('success');

        $this->from(route('fecho-ano.index'))
            ->post(route('fecho-ano.activar'))
            ->assertRedirect(route('fecho-ano.index'))
            ->assertSessionHas('success');

        $this->assertSame(
            AnoLectivo::ESTADO_ACTUAL,
            $this->scenario->anos->destino->fresh()->estado
        );
        $this->assertSame(
            TransicaoAnoScenarioBuilder::ANO_DESTINO,
            Configuracao::getAnoLectivoActual()
        );

        $this->assertNotNull(
            Aluno::where('numero_processo', 'PROC-001')
                ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $this->scenario->anos->destino->id))
                ->first()
        );
    }

    public function test_transitar_com_reprovados_via_http(): void
    {
        $this->scenario->withAlunoReprovado();

        $this->actingAs($this->admin);

        $this->from(route('fecho-ano.index'))
            ->post(route('fecho-ano.configurar'), [
                'ano_origem' => TransicaoAnoScenarioBuilder::ANO_ORIGEM,
                'ano_destino' => TransicaoAnoScenarioBuilder::ANO_DESTINO,
            ]);

        $this->from(route('fecho-ano.index'))
            ->post(route('fecho-ano.transitar'), [
                'transitar_reprovados' => '1',
            ])
            ->assertRedirect(route('fecho-ano.index'))
            ->assertSessionHas('success');

        $reprovadoDestino = Aluno::where('numero_processo', 'PROC-REP')
            ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $this->scenario->anos->destino->id))
            ->first();

        $this->assertNotNull($reprovadoDestino);
        $this->assertSame($this->scenario->turmas->destino_10->id, $reprovadoDestino->turma_id);
    }
}
