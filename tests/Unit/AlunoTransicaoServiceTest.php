<?php

namespace Tests\Unit;

use App\Models\Classe;
use App\Models\Turma;
use App\Services\AlunoTransicaoService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AlunoTransicaoServiceTest extends TestCase
{
    private AlunoTransicaoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AlunoTransicaoService::class);
    }

    public function test_mapeamento_promocao_avanca_uma_classe(): void
    {
        [$origem, $destino] = $this->criarTurmasExemplo();

        $mapping = $this->service->criarMapeamentoPromocao($origem, $destino);

        $this->assertSame(102, $mapping[1]);
        $this->assertSame(103, $mapping[2]);
    }

    public function test_mapeamento_repetencia_mantem_mesma_classe(): void
    {
        [$origem, $destino] = $this->criarTurmasExemplo();

        $mapping = $this->service->criarMapeamentoRepetencia($origem, $destino);

        $this->assertSame(101, $mapping[1]);
        $this->assertSame(102, $mapping[2]);
    }

    public function test_mapeamento_promocao_respeita_curso_e_turno(): void
    {
        $classe10 = new Classe(['codigo' => '10', 'nome' => '10ª Classe']);
        $classe11 = new Classe(['codigo' => '11', 'nome' => '11ª Classe']);

        $turmaOrigem = new Turma(['curso_id' => 5, 'turno_id' => 2]);
        $turmaOrigem->id = 10;
        $turmaOrigem->setRelation('classe', $classe10);

        $turmaDestinoErrada = new Turma(['curso_id' => 5, 'turno_id' => 3]);
        $turmaDestinoErrada->id = 20;
        $turmaDestinoErrada->setRelation('classe', $classe11);

        $turmaDestinoCerta = new Turma(['curso_id' => 5, 'turno_id' => 2]);
        $turmaDestinoCerta->id = 21;
        $turmaDestinoCerta->setRelation('classe', $classe11);

        $mapping = $this->service->criarMapeamentoPromocao(
            collect([$turmaOrigem]),
            collect([$turmaDestinoErrada, $turmaDestinoCerta])
        );

        $this->assertSame(21, $mapping[10]);
    }

    public function test_mapeamento_promocao_distingue_turmas_paralelas_pelo_sufixo(): void
    {
        $classe10 = new Classe(['codigo' => '10', 'nome' => '10ª Classe']);
        $classe11 = new Classe(['codigo' => '11', 'nome' => '11ª Classe']);

        $turmaAt = new Turma(['curso_id' => 1, 'turno_id' => null, 'codigo' => 'TI10AT-O']);
        $turmaAt->id = 1;
        $turmaAt->setRelation('classe', $classe10);

        $turmaBt = new Turma(['curso_id' => 1, 'turno_id' => null, 'codigo' => 'TI10BT-O']);
        $turmaBt->id = 2;
        $turmaBt->setRelation('classe', $classe10);

        $dest11At = new Turma(['curso_id' => 1, 'turno_id' => null, 'codigo' => 'TI11AT-D']);
        $dest11At->id = 11;
        $dest11At->setRelation('classe', $classe11);

        $dest11Bt = new Turma(['curso_id' => 1, 'turno_id' => null, 'codigo' => 'TI11BT-D']);
        $dest11Bt->id = 12;
        $dest11Bt->setRelation('classe', $classe11);

        $mapping = $this->service->criarMapeamentoPromocao(
            collect([$turmaAt, $turmaBt]),
            collect([$dest11At, $dest11Bt])
        );

        $this->assertSame(11, $mapping[1]);
        $this->assertSame(12, $mapping[2]);
    }

    /**
     * @return array{0: Collection<int, Turma>, 1: Collection<int, Turma>}
     */
    private function criarTurmasExemplo(): array
    {
        $classe10 = new Classe(['codigo' => '10', 'nome' => '10ª Classe']);
        $classe11 = new Classe(['codigo' => '11', 'nome' => '11ª Classe']);
        $classe12 = new Classe(['codigo' => '12', 'nome' => '12ª Classe']);

        $turma10 = new Turma(['curso_id' => 1, 'turno_id' => 1]);
        $turma10->id = 1;
        $turma10->setRelation('classe', $classe10);

        $turma11 = new Turma(['curso_id' => 1, 'turno_id' => 1]);
        $turma11->id = 2;
        $turma11->setRelation('classe', $classe11);

        $dest10 = new Turma(['curso_id' => 1, 'turno_id' => 1]);
        $dest10->id = 101;
        $dest10->setRelation('classe', $classe10);

        $dest11 = new Turma(['curso_id' => 1, 'turno_id' => 1]);
        $dest11->id = 102;
        $dest11->setRelation('classe', $classe11);

        $dest12 = new Turma(['curso_id' => 1, 'turno_id' => 1]);
        $dest12->id = 103;
        $dest12->setRelation('classe', $classe12);

        return [
            collect([$turma10, $turma11]),
            collect([$dest10, $dest11, $dest12]),
        ];
    }
}
