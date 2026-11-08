<?php

namespace Tests\Unit;

use App\Models\Configuracao;
use App\Support\NotaLancamentoRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotaLancamentoExameSemElegibilidadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_permite_lancamento_quando_configuracao_activa(): void
    {
        Configuracao::set('exames_permitir_lancamento_nao_elegivel', '1', 'test');

        $this->assertTrue(NotaLancamentoRules::permiteLancamentoExameSemElegibilidade());
        $this->assertTrue(NotaLancamentoRules::podeLancarExameAlunoDisciplina(
            ['elegivel' => false, 'disciplinas_elegiveis' => []],
            9,
            false,
        ));
    }

    public function test_bloqueia_lancamento_quando_configuracao_desactivada(): void
    {
        Configuracao::set('exames_permitir_lancamento_nao_elegivel', '0', 'test');

        $this->assertFalse(NotaLancamentoRules::permiteLancamentoExameSemElegibilidade());
        $this->assertFalse(NotaLancamentoRules::podeLancarExameAlunoDisciplina(
            ['elegivel' => false, 'disciplinas_elegiveis' => []],
            9,
            false,
        ));
    }

    public function test_elegivel_formal_sempre_pode_lancar(): void
    {
        Configuracao::set('exames_permitir_lancamento_nao_elegivel', '0', 'test');

        $this->assertTrue(NotaLancamentoRules::podeLancarExameAlunoDisciplina(
            [
                'elegivel' => true,
                'disciplinas_elegiveis' => [['disciplina_id' => 9]],
            ],
            9,
            false,
        ));
    }
}
