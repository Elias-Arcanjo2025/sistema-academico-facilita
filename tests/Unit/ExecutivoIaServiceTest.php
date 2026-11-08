<?php

namespace Tests\Unit;

use App\Models\Configuracao;
use App\Models\User;
use App\Services\ExecutivoIaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class ExecutivoIaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'professor']);
        Role::firstOrCreate(['name' => 'admin_full']);
        config(['assistente.enabled' => false]);
    }

    public function test_gera_resumo_local_para_professor(): void
    {
        $scenario = (new TransicaoAnoScenarioBuilder)->build();
        $professor = $scenario->users->professor;
        $professor->assignRole('professor');

        $resultado = app(ExecutivoIaService::class)->gerarResumo($professor);

        $this->assertSame('local', $resultado['fonte']);
        $this->assertSame('Professor', $resultado['perfil']);
        $this->assertStringContainsString('Resumo Executivo', $resultado['resumo']);
        $this->assertStringContainsString('Saúde académica', $resultado['resumo']);
    }

    public function test_invalidar_cache_forca_novo_resumo(): void
    {
        $scenario = (new TransicaoAnoScenarioBuilder)->build();
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole('admin_full');

        $service = app(ExecutivoIaService::class);
        $primeiro = $service->gerarResumo($admin);
        $service->invalidarCache($admin);
        Configuracao::set('trimestre_actual', '2', 'test');
        $segundo = $service->gerarResumo($admin);

        $this->assertSame('local', $primeiro['fonte']);
        $this->assertSame('Administrador', $segundo['perfil']);
        $this->assertNotEmpty($segundo['resumo']);
    }
}
