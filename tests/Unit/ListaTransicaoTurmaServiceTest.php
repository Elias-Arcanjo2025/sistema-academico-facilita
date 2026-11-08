<?php

namespace Tests\Unit;

use App\Services\ListaTransicaoTurmaService;
use App\Services\PautaService;
use App\Services\RecuperacaoTransicaoService;
use PHPUnit\Framework\TestCase;

class ListaTransicaoTurmaServiceTest extends TestCase
{
    public function test_montar_linha_inclui_disciplinas_negativas(): void
    {
        $service = new class(
            $this->createMock(PautaService::class),
            $this->createMock(RecuperacaoTransicaoService::class),
        ) extends ListaTransicaoTurmaService {
            public function linhaPublica(array $aluno, array $analise): array
            {
                return $this->montarLinha($aluno, $analise);
            }

            public function grupoPublico(array $aluno, array $analise): string
            {
                return $this->chaveGrupo($aluno, $analise);
            }
        };

        $aluno = [
            'numero_ordem' => 5,
            'numero_processo' => '2025001',
            'nome' => 'Ana Silva',
            'desistente' => false,
        ];

        $analise = [
            'resultado' => 'TRANSITA/C DEF',
            'observacao' => 'Def.: MAT | Rec.: LP',
            'continuas_deficientes' => [
                ['disciplina_id' => 1, 'disciplina_nome' => 'MAT', 'ca' => 8],
            ],
            'disciplinas_recuperacao' => [
                ['disciplina_id' => 1, 'disciplina_nome' => 'MAT', 'ca' => 8],
                ['disciplina_id' => 4, 'disciplina_nome' => 'LP', 'ca' => 7],
            ],
        ];

        $linha = $service->linhaPublica($aluno, $analise);

        $this->assertSame('MAT (8)', $linha['deficiencias']);
        $this->assertSame('LP (7)', $linha['recuperacao']);
        $this->assertSame('MAT (8), LP (7)', $linha['todas_negativas']);
        $this->assertSame(2, $linha['total_negativas']);
        $this->assertSame('TRANSITA/C DEF', $service->grupoPublico($aluno, $analise));
    }

    public function test_resumo_conta_linhas_por_grupo(): void
    {
        $grupos = [
            'TRANSITA' => [['numero_ordem' => 1], ['numero_ordem' => 2]],
            'N/TRANSITA' => [['numero_ordem' => 3]],
        ];

        $resumo = collect($grupos)->map(fn (array $linhas) => count($linhas))->all();

        $this->assertSame(2, $resumo['TRANSITA']);
        $this->assertSame(1, $resumo['N/TRANSITA']);
    }
}
