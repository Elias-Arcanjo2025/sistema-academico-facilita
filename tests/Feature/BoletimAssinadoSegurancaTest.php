<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Services\SignedDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class BoletimAssinadoSegurancaTest extends TestCase
{
    use RefreshDatabase;

    public function test_boletim_assinado_bloqueado_quando_aluno_sem_acesso_portal(): void
    {
        (new TransicaoAnoScenarioBuilder)->build();

        $aluno = Aluno::first();
        $aluno->update([
            'consulta_notas_portal' => false,
            'motivo_bloqueio_portal' => 'Pagamento do boletim pendente na secretaria.',
        ]);

        $url = app(SignedDocumentService::class)->urlBoletim($aluno);

        $this->get($url)
            ->assertForbidden()
            ->assertSee('Pagamento do boletim pendente', false);
    }

    public function test_arquivos_publicos_rejeitam_pastas_nao_permitidas(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('backups/segredo.zip', 'dados');

        $this->get('/arquivos/backups/segredo.zip')->assertNotFound();
    }

    public function test_arquivos_publicos_servem_pasta_permitida(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('pagina-publica/teste.txt', 'ok');

        $this->get('/arquivos/pagina-publica/teste.txt')->assertOk();
    }

    public function test_registo_publico_desactivado_retorna_404(): void
    {
        Configuracao::set('registo_publico_activo', '0');

        $this->get(route('register'))->assertNotFound();
    }
}
