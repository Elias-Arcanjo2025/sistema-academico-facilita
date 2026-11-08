<?php

namespace Tests\Unit;

use App\Services\RelatorioAcademicoService;
use PHPUnit\Framework\TestCase;

class RelatorioAcademicoServiceTest extends TestCase
{
    public function test_classifica_transita(): void
    {
        $this->assertSame('transita', RelatorioAcademicoService::classificarResultadoT3('TRANSITA'));
    }

    public function test_classifica_transita_com_deficiencia(): void
    {
        $this->assertSame('transita_deficiencia', RelatorioAcademicoService::classificarResultadoT3('TRANSITA/C DEF'));
    }

    public function test_classifica_reprova_n_transita(): void
    {
        $this->assertSame('reprova', RelatorioAcademicoService::classificarResultadoT3('N/TRANSITA'));
    }

    public function test_classifica_aprovado_12(): void
    {
        $this->assertSame('aprovado', RelatorioAcademicoService::classificarResultadoT3('APROVADO'));
    }

    public function test_classifica_reprovado_12(): void
    {
        $this->assertSame('reprova', RelatorioAcademicoService::classificarResultadoT3('REPROVADO'));
    }

    public function test_nota_ranking_t1_usa_mt(): void
    {
        $regime = new \App\Services\RegimePauta\RegimePolitecnico;

        $nota = RelatorioAcademicoService::notaRankingDisciplina(
            ['mt' => 14, 'recuperacao' => null],
            1,
            $regime,
        );

        $this->assertSame(14.0, $nota);
    }

    public function test_nota_ranking_t2_politecnico_usa_mft2(): void
    {
        $regime = new \App\Services\RegimePauta\RegimePolitecnico;

        $nota = RelatorioAcademicoService::notaRankingDisciplina(
            ['mft2' => 13, 'mt' => 12, 'mt1' => 14],
            2,
            $regime,
        );

        $this->assertSame(13.0, $nota);
    }

    public function test_nota_ranking_t2_calcula_mft2_se_ausente(): void
    {
        $regime = new \App\Services\RegimePauta\RegimePolitecnico;

        $nota = RelatorioAcademicoService::notaRankingDisciplina(
            ['mt' => 12, 'mt1' => 14],
            2,
            $regime,
        );

        $this->assertSame(13.0, $nota);
    }

    public function test_nota_ranking_t2_admin_usa_mt(): void
    {
        $regime = new \App\Services\RegimePauta\RegimeAdministracao;

        $nota = RelatorioAcademicoService::notaRankingDisciplina(
            ['mft2' => 15, 'mt' => 11],
            2,
            $regime,
        );

        $this->assertSame(11.0, $nota);
    }

    public function test_nota_ranking_t3_usa_ca(): void
    {
        $regime = new \App\Services\RegimePauta\RegimePolitecnico;

        $nota = RelatorioAcademicoService::notaRankingDisciplina(
            ['ca' => 12, 'cfd' => 9, 'cf' => 11, 'pg' => 10],
            3,
            $regime,
        );

        $this->assertSame(12.0, $nota);
    }

    public function test_metrica_ranking_label(): void
    {
        $this->assertSame('MT', RelatorioAcademicoService::metricaRankingLabel(1));
        $this->assertSame('MFT2', RelatorioAcademicoService::metricaRankingLabel(2));
        $this->assertSame('CA', RelatorioAcademicoService::metricaRankingLabel(3));
    }
}
