<?php

namespace Tests\Unit;

use App\Support\NotaLancamentoRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotaLancamentoRulesTest extends TestCase
{
    use RefreshDatabase;
    public function test_aluno_pertence_turma_retorna_false_sem_dados(): void
    {
        $this->assertFalse(NotaLancamentoRules::alunoPertenceTurma(1, 999));
    }

    public function test_turma_eh_ano_historico_sem_turma(): void
    {
        $this->assertFalse(NotaLancamentoRules::turmaEhAnoHistorico(999, 1));
    }

    public function test_disciplina_pertence_turma_retorna_false_turma_inexistente(): void
    {
        $this->assertFalse(NotaLancamentoRules::disciplinaPertenceTurma(1, 999));
    }
}
