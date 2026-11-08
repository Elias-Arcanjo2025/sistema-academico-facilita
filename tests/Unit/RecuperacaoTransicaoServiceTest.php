<?php

namespace Tests\Unit;

use App\Services\RecuperacaoTransicaoService;
use App\Support\PautaResultadoTransicao;
use PHPUnit\Framework\TestCase;

class RecuperacaoTransicaoServiceTest extends TestCase
{
    private RecuperacaoTransicaoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecuperacaoTransicaoService();
    }

    public function test_tres_negativas_continuidade_n_transita(): void
    {
        $meta = $this->metaContinua(1, 'MAT', 12)
            + $this->metaContinua(2, 'FIS', 12)
            + $this->metaContinua(3, 'QUI', 12);

        $aluno = [
            'aluno' => (object) ['id' => 1],
            'disciplinas' => [
                1 => ['ca' => 8, 'tem_coluna_cfd' => false],
                2 => ['ca' => 7, 'tem_coluna_cfd' => false],
                3 => ['ca' => 6, 'tem_coluna_cfd' => false],
            ],
        ];

        $r = $this->service->analisarAluno($aluno, 1, 10, $meta, []);

        $this->assertTrue($r['reprova_directamente']);
        $this->assertSame('N/TRANSITA', $r['resultado']);
    }

    public function test_tres_negativas_total_n_transita(): void
    {
        $meta = $this->metaContinua(1, 'MAT', 12)
            + $this->metaContinua(2, 'FIS', 12)
            + $this->metaTerminal(3, 'LP', 10);

        $aluno = [
            'aluno' => (object) ['id' => 1],
            'disciplinas' => [
                1 => ['ca' => 8, 'tem_coluna_cfd' => false],
                2 => ['ca' => 9, 'tem_coluna_cfd' => false],
                3 => ['cfd' => 7, 'ca' => 12, 'tem_coluna_cfd' => true],
            ],
        ];

        $r = $this->service->analisarAluno($aluno, 1, 10, $meta, []);

        $this->assertSame('N/TRANSITA', $r['resultado']);
    }

    public function test_duas_negativas_continuas_transita_com_deficiencia(): void
    {
        $meta = $this->metaContinua(1, 'MAT', 12) + $this->metaContinua(2, 'FIS', 12);

        $aluno = [
            'aluno' => (object) ['id' => 2],
            'disciplinas' => [
                1 => ['ca' => 8, 'tem_coluna_cfd' => false],
                2 => ['ca' => 9, 'tem_coluna_cfd' => false],
            ],
        ];

        $r = $this->service->analisarAluno($aluno, 1, 11, $meta, []);

        $this->assertSame('TRANSITA/C DEF', $r['resultado']);
        $this->assertStringContainsString('Def.:', $r['observacao']);
    }

    public function test_negativa_terminal_recuperacao(): void
    {
        $meta = [
            4 => [
                'disciplina_id' => 4,
                'nome' => 'LP',
                'codigo' => 'LP',
                'classe_fim' => 11,
                'blindada' => true,
                'terminal' => true,
                'continua' => false,
                'usa_cfd' => true,
            ],
            1 => $this->metaContinua(1, 'MAT', 12)[1],
        ];

        $aluno = [
            'aluno' => (object) ['id' => 7],
            'disciplinas' => [
                4 => ['cfd' => 8, 'ca' => 14, 'tem_coluna_cfd' => true],
                1 => ['ca' => 14, 'tem_coluna_cfd' => false],
            ],
        ];

        $r = $this->service->analisarAluno($aluno, 1, 11, $meta, [1, 4]);

        $this->assertSame('RECUPERAÇÃO', $r['resultado']);
        $this->assertStringContainsString('Rec.: LP', $r['observacao']);
    }

    public function test_sem_negativas_transita(): void
    {
        $meta = $this->metaContinua(1, 'MAT', 12);

        $aluno = [
            'aluno' => (object) ['id' => 5],
            'disciplinas' => [
                1 => ['ca' => 14, 'tem_coluna_cfd' => false],
            ],
        ];

        $r = $this->service->analisarAluno($aluno, 1, 11, $meta, [1]);

        $this->assertSame('TRANSITA', $r['resultado']);
        $this->assertSame('', $r['observacao']);
    }

    public function test_continua_nao_usa_cf_como_ca(): void
    {
        $meta = $this->metaContinua(1, 'MAT', 12);

        $aluno = [
            'aluno' => (object) ['id' => 6],
            'disciplinas' => [
                1 => ['ca' => 14, 'cf' => 8, 'pg' => null, 'tem_coluna_cfd' => false],
            ],
        ];

        $r = $this->service->analisarAluno($aluno, 1, 11, $meta, [1]);

        $this->assertSame('TRANSITA', $r['resultado']);
    }

    public function test_uma_negativa_continua_transita_com_deficiencia(): void
    {
        $meta = $this->metaContinua(1, 'MAT', 12);

        $aluno = [
            'aluno' => (object) ['id' => 4],
            'disciplinas' => [
                1 => ['ca' => 8, 'tem_coluna_cfd' => false],
            ],
        ];

        $r = $this->service->analisarAluno($aluno, 1, 11, $meta, [1]);

        $this->assertSame('TRANSITA/C DEF', $r['resultado']);
        $this->assertSame('Def.: MAT', $r['observacao']);
    }

    public function test_cfd_terminal_de_ano_anterior_conta_na_11_classe(): void
    {
        $meta = $this->metaContinua(1, 'MAT', 12);

        $aluno = [
            'aluno' => (object) ['id' => 10],
            'disciplinas' => [
                1 => ['ca' => 14, 'tem_coluna_cfd' => false],
            ],
            'terminais_anteriores' => [
                ['disciplina_id' => 99, 'codigo' => 'TIC', 'nome' => 'TIC'],
            ],
            'cfd_terminais' => [
                99 => 5,
            ],
        ];

        $r = $this->service->analisarAluno($aluno, 1, 11, $meta, []);

        $this->assertSame('RECUPERAÇÃO', $r['resultado']);
        $this->assertStringContainsString('Rec.: TIC', $r['observacao']);
    }

    public function test_cfd_terminal_de_ano_anterior_ignorado_na_10_classe(): void
    {
        $meta = $this->metaContinua(1, 'MAT', 12);

        $aluno = [
            'aluno' => (object) ['id' => 11],
            'disciplinas' => [
                1 => ['ca' => 14, 'tem_coluna_cfd' => false],
            ],
            'terminais_anteriores' => [
                ['disciplina_id' => 99, 'codigo' => 'TIC', 'nome' => 'TIC'],
            ],
            'cfd_terminais' => [
                99 => 5,
            ],
        ];

        $r = $this->service->analisarAluno($aluno, 1, 10, $meta, []);

        $this->assertSame('TRANSITA', $r['resultado']);
    }

    public function test_mista_continua_e_terminal_apenas_terminal_em_recuperacao(): void
    {
        $meta = $this->metaContinua(1, 'MAT', 12) + $this->metaTerminal(4, 'LP', 10);

        $aluno = [
            'aluno' => (object) ['id' => 8],
            'disciplinas' => [
                1 => ['ca' => 8, 'tem_coluna_cfd' => false],
                4 => ['cfd' => 7, 'ca' => 12, 'tem_coluna_cfd' => true],
            ],
        ];

        $r = $this->service->analisarAluno($aluno, 1, 10, $meta, [1, 4]);

        $this->assertSame('RECUPERAÇÃO', $r['resultado']);
        $this->assertCount(2, $r['disciplinas_recuperacao']);
        $this->assertCount(1, $r['continuas_deficientes']);
        $this->assertCount(1, $r['terminais_recuperacao']);
        $this->assertSame('MAT', $r['continuas_deficientes'][0]['disciplina_nome']);
        $this->assertSame('LP', $r['terminais_recuperacao'][0]['disciplina_nome']);
        $this->assertTrue(PautaResultadoTransicao::elegivelRecuperacao(
            count($r['disciplinas_recuperacao']),
            count($r['terminais_recuperacao']),
            count($r['continuas_deficientes']),
        ));
    }

    public function test_apenas_continua_nao_elegivel_para_exame_recuperacao(): void
    {
        $meta = $this->metaContinua(1, 'MAT', 12);

        $aluno = [
            'aluno' => (object) ['id' => 4],
            'disciplinas' => [
                1 => ['ca' => 8, 'tem_coluna_cfd' => false],
            ],
        ];

        $r = $this->service->analisarAluno($aluno, 1, 11, $meta, [1]);

        $this->assertSame('TRANSITA/C DEF', $r['resultado']);
        $this->assertCount(0, $r['terminais_recuperacao']);
        $this->assertFalse(PautaResultadoTransicao::elegivelRecuperacao(
            count($r['disciplinas_recuperacao']),
            count($r['terminais_recuperacao']),
            count($r['continuas_deficientes']),
        ));
    }

    public function test_pap_condicao_nao_reunida_na_12_para_curso_13(): void
    {
        $meta = $this->metaContinua(1, 'MAT', 13);

        $aluno = [
            'aluno' => (object) ['id' => 13],
            'disciplinas' => [
                1 => ['ca' => 8, 'tem_coluna_cfd' => false],
            ],
        ];

        $r = $this->service->analisarAluno($aluno, 1, 12, $meta, [], 13);

        $this->assertSame('RECURSO', $r['resultado']);
        $this->assertStringContainsString('Rec.:', $r['observacao']);
        $this->assertStringContainsString('PAP: condição não reunida', $r['observacao']);
    }

    public function test_classifica_com_notas_parciais_sem_bloquear(): void
    {
        // Meta tem 2 disciplinas; só 1 tem nota — antigo bloqueava como Pendente
        $meta = $this->metaContinua(1, 'MAT', 12) + $this->metaContinua(2, 'FIS', 12);

        $aluno = [
            'aluno' => (object) ['id' => 20],
            'disciplinas' => [
                1 => ['ca' => 8, 'tem_coluna_cfd' => false],
                // FIS sem CA
            ],
        ];

        $r = $this->service->analisarAluno($aluno, 1, 11, $meta, []);

        $this->assertSame('TRANSITA/C DEF', $r['resultado']);
        $this->assertStringContainsString('Def.: MAT', $r['observacao']);
        $this->assertFalse($r['avaliacao_completa']);
    }

    public function test_disciplina_sem_professor_nao_conta_negativa(): void
    {
        $meta = $this->metaContinua(1, 'MAT', 12);
        $meta[1]['sem_professor'] = true;

        $aluno = [
            'aluno' => (object) ['id' => 12],
            'disciplinas' => [
                1 => ['ca' => 5, 'tem_coluna_cfd' => false],
            ],
        ];

        $r = $this->service->analisarAluno($aluno, 1, 11, $meta, [], 12);

        $this->assertSame('sem_avaliacao', $r['situacao']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function metaContinua(int $id, string $codigo, int $classeFim): array
    {
        return [
            $id => [
                'disciplina_id' => $id,
                'nome' => $codigo,
                'codigo' => $codigo,
                'classe_fim' => $classeFim,
                'blindada' => false,
                'terminal' => false,
                'continua' => true,
                'usa_cfd' => false,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function metaTerminal(int $id, string $codigo, int $classeFim): array
    {
        return [
            $id => [
                'disciplina_id' => $id,
                'nome' => $codigo,
                'codigo' => $codigo,
                'classe_fim' => $classeFim,
                'blindada' => false,
                'terminal' => true,
                'continua' => false,
                'usa_cfd' => true,
            ],
        ];
    }
}

class PautaResultadoTransicaoTest extends TestCase
{
    public function test_resolver_regras_ministerio(): void
    {
        $this->assertSame('TRANSITA', PautaResultadoTransicao::resolverResultado(0, [], [])['resultado']);
        $this->assertSame('N/TRANSITA', PautaResultadoTransicao::resolverResultado(3, [], [])['resultado']);
        $this->assertSame(
            'N/TRANSITA',
            PautaResultadoTransicao::resolverResultado(3, [], [
                ['disciplina_nome' => 'MAT'],
                ['disciplina_nome' => 'FIS'],
                ['disciplina_nome' => 'QUI'],
            ])['resultado'],
        );
        $this->assertSame(
            'TRANSITA/C DEF',
            PautaResultadoTransicao::resolverResultado(2, [], [
                ['disciplina_nome' => 'MAT'],
                ['disciplina_nome' => 'FIS'],
            ])['resultado'],
        );
        $this->assertSame(
            'TRANSITA/C DEF',
            PautaResultadoTransicao::resolverResultado(1, [], [
                ['disciplina_nome' => 'MAT'],
            ])['resultado'],
        );
    }

    public function test_elegivel_recuperacao_exige_terminal_negativa(): void
    {
        $this->assertFalse(PautaResultadoTransicao::elegivelRecuperacao(1, 0, 1));
        $this->assertTrue(PautaResultadoTransicao::elegivelRecuperacao(2, 1, 1));
        $this->assertFalse(PautaResultadoTransicao::elegivelRecuperacao(3, 1, 1));
    }

    public function test_nota_oficial_terminal_usa_cfd(): void
    {
        $nota = PautaResultadoTransicao::notaOficialDisciplina(
            ['cfd' => 8, 'ca' => 14, 'tem_coluna_cfd' => true],
            ['classe_fim' => 11, 'terminal' => true, 'usa_cfd' => true],
            11,
        );

        $this->assertSame(8.0, $nota);
    }

    public function test_nota_oficial_continua_ignora_cf(): void
    {
        $nota = PautaResultadoTransicao::notaOficialDisciplina(
            ['ca' => 14, 'cf' => 8, 'tem_coluna_cfd' => false],
            ['classe_fim' => 12, 'continua' => true],
            11,
        );

        $this->assertSame(14.0, $nota);
    }
}
