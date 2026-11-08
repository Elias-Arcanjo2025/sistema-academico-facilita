<?php

namespace Tests\Unit;

use App\Models\Nota;
use App\Models\Pedagogico\PedProva;
use App\Services\ConfiguracaoService;
use App\Services\Pedagogico\ProvaPdfService;
use Mockery;
use Tests\TestCase;

class ProvaPdfServiceTest extends TestCase
{
    public function test_monta_titulo_pt_com_trimestre(): void
    {
        $config = Mockery::mock(ConfiguracaoService::class);
        $service = new ProvaPdfService($config);

        $prova = new PedProva([
            'tipo' => Nota::TIPO_PT,
            'tempo_minutos' => 120,
            'conteudo_json' => [],
        ]);
        $prova->setRelation('planificacao', (object) ['trimestre' => 2, 'professor' => (object) ['name' => 'Prof. Teste']]);
        $prova->setRelation('turma', (object) [
            'nome' => 'A',
            'codigo' => 'TI11AM',
            'classe' => (object) ['nome' => '12ª Classe'],
            'turno' => (object) ['nome' => 'Manhã'],
            'curso' => (object) ['nome' => 'Informática'],
            'ano_lectivo' => '2025/2026',
        ]);
        $prova->setRelation('disciplina', (object) ['codigo' => 'TLP', 'nome' => 'Técnicas e Linguagens de Programação']);

        $cab = $service->montarCabecalho($prova);

        $this->assertSame('PROVA FINAL DO 2º TRIMESTRE DE TLP', $cab['titulo']);
        $this->assertSame('12ª Classe/Manhã', $cab['classe_turno']);
        $this->assertSame('INFORMÁTICA', $cab['curso']);
        $this->assertSame('Prof. Teste', $cab['professor']);
    }
}
