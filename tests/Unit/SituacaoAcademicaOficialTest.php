<?php

namespace Tests\Unit;

use App\Models\Aluno;
use App\Support\PautaResultadoTransicao;
use App\Support\SituacaoAcademicaOficial;
use Tests\TestCase;

class SituacaoAcademicaOficialTest extends TestCase
{
    public function test_transita_mapeia_para_aprovado(): void
    {
        $aluno = new Aluno(['situacao_academica' => Aluno::SITUACAO_MATRICULADO]);

        $this->assertSame(
            Aluno::SITUACAO_APROVADO,
            SituacaoAcademicaOficial::situacaoAcademicaDeAnalise([
                'situacao' => 'transita',
                'resultado' => PautaResultadoTransicao::RESULTADO_TRANSITA,
            ], $aluno)
        );
    }

    public function test_transita_com_deficiencia_mapeia_para_aprovado(): void
    {
        $aluno = new Aluno(['situacao_academica' => Aluno::SITUACAO_MATRICULADO]);

        $this->assertSame(
            Aluno::SITUACAO_APROVADO,
            SituacaoAcademicaOficial::situacaoAcademicaDeAnalise([
                'situacao' => 'transita_deficiencia',
                'resultado' => PautaResultadoTransicao::RESULTADO_TRANSITA_COM_DEFICIENCIA,
            ], $aluno)
        );
    }

    public function test_nao_transita_mapeia_para_reprovado(): void
    {
        $aluno = new Aluno(['situacao_academica' => Aluno::SITUACAO_MATRICULADO]);

        $this->assertSame(
            Aluno::SITUACAO_REPROVADO,
            SituacaoAcademicaOficial::situacaoAcademicaDeAnalise([
                'situacao' => 'reprova',
                'resultado' => PautaResultadoTransicao::RESULTADO_N_TRANSITA,
            ], $aluno)
        );
    }

    public function test_pauta_incompleta_mantem_situacao(): void
    {
        $aluno = new Aluno(['situacao_academica' => Aluno::SITUACAO_MATRICULADO]);

        $this->assertNull(
            SituacaoAcademicaOficial::situacaoAcademicaDeAnalise([
                'situacao' => 'incompleta',
                'resultado' => '',
            ], $aluno)
        );
    }
}
