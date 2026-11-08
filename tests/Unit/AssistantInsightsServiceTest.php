<?php

namespace Tests\Unit;

use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\User;
use App\Services\Assistente\AssistantAccess;
use App\Services\Assistente\AssistantInsightsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantInsightsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'aluno']);
    }

    public function test_analisa_comparacao_entre_trimestres(): void
    {
        $service = app(AssistantInsightsService::class);

        $notas = new Collection([
            $this->notaFake(1, 'Física', Nota::TIPO_MT, 1, 12.0),
            $this->notaFake(1, 'Física', Nota::TIPO_MT, 2, 14.0),
            $this->notaFake(2, 'Matemática', Nota::TIPO_MT, 1, 16.0),
            $this->notaFake(2, 'Matemática', Nota::TIPO_MT, 2, 15.0),
        ]);

        $analise = $service->analisarDesempenhoAluno($notas, 10.0, 1);

        $this->assertCount(2, $analise['comparacao_por_disciplina']);
        $fisica = collect($analise['comparacao_por_disciplina'])->firstWhere('disciplina', 'Física');
        $this->assertSame(2.0, $fisica['variacao_t1_para_t2']);
        $this->assertNotEmpty($analise['melhores_disciplinas']);
        $this->assertNotEmpty($analise['maior_evolucao_t1_t2']);
    }

    public function test_aluno_pode_comparar_proprias_notas_entre_trimestres(): void
    {
        $user = User::factory()->create();
        $user->assignRole('aluno');

        $access = app(AssistantAccess::class);

        $pergunta = 'Quero que faças uma comparação das minhas notas de cada disciplina, do primeiro com o segundo trimestre.';

        $this->assertTrue($access->avaliarPergunta($user, $pergunta)['permitida']);
    }

    protected function notaFake(int $disciplinaId, string $nome, string $tipo, int $trimestre, float $valor): Nota
    {
        $nota = new Nota([
            'disciplina_id' => $disciplinaId,
            'tipo' => $tipo,
            'trimestre' => $trimestre,
            'valor' => $valor,
            'ano_lectivo_id' => 1,
            'archived_at' => null,
        ]);

        $nota->setRelation('disciplina', new Disciplina(['id' => $disciplinaId, 'nome' => $nome]));

        return $nota;
    }
}
