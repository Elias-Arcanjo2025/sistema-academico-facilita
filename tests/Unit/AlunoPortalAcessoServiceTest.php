<?php

namespace Tests\Unit;

use App\Models\Aluno;
use App\Services\Portal\AlunoPortalAcessoService;
use App\Services\ConfiguracaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AlunoPortalAcessoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_aluno_bloqueado_nao_pode_consultar_notas(): void
    {
        $config = Mockery::mock(ConfiguracaoService::class);
        $config->shouldReceive('get')->with('portal_aluno_notas_activo', '1')->andReturn('1');
        $config->shouldReceive('get')->andReturn('Mensagem padrão');

        $service = new AlunoPortalAcessoService($config);

        $aluno = new Aluno(['consulta_notas_portal' => false]);

        $this->assertFalse($service->podeConsultarNotas($aluno));
    }

    public function test_global_desactivado_bloqueia_todos(): void
    {
        $config = Mockery::mock(ConfiguracaoService::class);
        $config->shouldReceive('get')->with('portal_aluno_notas_activo', '1')->andReturn('0');

        $service = new AlunoPortalAcessoService($config);

        $aluno = new Aluno(['consulta_notas_portal' => true]);

        $this->assertFalse($service->podeConsultarNotas($aluno));
    }
}
