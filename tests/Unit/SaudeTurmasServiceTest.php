<?php

namespace Tests\Unit;

use App\Models\Configuracao;
use App\Models\User;
use App\Services\ExecutivoIaService;
use App\Services\SaudeTurmasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class SaudeTurmasServiceTest extends TestCase
{
    use RefreshDatabase;

    private object $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'professor']);
        $this->scenario = (new TransicaoAnoScenarioBuilder)->build();
        Configuracao::set('trimestre_actual', '1', 'test');
    }

    public function test_professor_recebe_indicadores_das_suas_turmas(): void
    {
        $professor = $this->scenario->users->professor;
        $professor->assignRole('professor');

        $indicadores = app(SaudeTurmasService::class)->indicadores($professor);

        $this->assertSame('Saúde das suas turmas', $indicadores['titulo']);
        $this->assertGreaterThanOrEqual(0, $indicadores['score']);
        $this->assertArrayHasKey('transicao', $indicadores);
        $this->assertArrayHasKey('lancamento', $indicadores);
        $this->assertNotEmpty($indicadores['turmas']);
    }

    public function test_utilizador_sem_turmas_recebe_estado_vazio(): void
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole('professor');

        $indicadores = app(SaudeTurmasService::class)->indicadores($user);

        $this->assertSame(0, $indicadores['score']);
        $this->assertSame('0 turmas', $indicadores['escopo']);
        $this->assertSame([], $indicadores['turmas']);
    }
}
