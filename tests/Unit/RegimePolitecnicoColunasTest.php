<?php

namespace Tests\Unit;

use App\Services\RegimePauta\RegimePolitecnico;
use PHPUnit\Framework\TestCase;

class RegimePolitecnicoColunasTest extends TestCase
{
    private RegimePolitecnico $regime;

    protected function setUp(): void
    {
        parent::setUp();
        $this->regime = new RegimePolitecnico();
    }

    public function test_t3_10a_normal_sem_cfd(): void
    {
        $cols = $this->regime->colunasDisciplina(3, 10, [
            'classe_inicio' => 10,
            'classe_fim' => 12,
            'classe_max' => 12,
            'terminal_classe_atual' => false,
        ]);
        $keys = array_column($cols, 'key');
        $this->assertSame(
            ['mft2', 'mac', 'pp', 'mt', 'cf', 'pg', 'ca'],
            $keys
        );
    }

    public function test_t3_10a_terminal_tic_ca_e_cfd(): void
    {
        $cols = $this->regime->colunasDisciplina(3, 10, [
            'classe_inicio' => 10,
            'classe_fim' => 10,
            'classe_max' => 12,
            'terminal_classe_atual' => true,
        ]);
        $keys = array_column($cols, 'key');
        $this->assertSame(
            ['mft2', 'mac', 'pp', 'mt', 'cf', 'pg', 'ca', 'cfd'],
            $keys
        );
    }

    public function test_t3_11a_desde_10a_sem_cfd(): void
    {
        $cols = $this->regime->colunasDisciplina(3, 11, [
            'classe_inicio' => 10,
            'classe_fim' => 12,
            'classe_max' => 12,
            'terminal_classe_atual' => false,
        ]);
        $keys = array_column($cols, 'key');
        $this->assertSame(
            ['ca10', 'mft2', 'mac', 'pp', 'mt', 'cf', 'pg', 'ca'],
            $keys
        );
    }

    public function test_t3_11a_lp_terminal_10_11_ca_e_cfd(): void
    {
        $cols = $this->regime->colunasDisciplina(3, 11, [
            'classe_inicio' => 10,
            'classe_fim' => 11,
            'classe_max' => 12,
            'terminal_classe_atual' => true,
        ]);
        $keys = array_column($cols, 'key');
        $this->assertSame(
            ['ca10', 'mft2', 'mac', 'pp', 'mt', 'cf', 'pg', 'ca', 'cfd'],
            $keys
        );
    }

    public function test_t3_11a_so_11a_sem_ca10_nem_cfd(): void
    {
        $cols = $this->regime->colunasDisciplina(3, 11, [
            'classe_inicio' => 11,
            'classe_fim' => 12,
            'classe_max' => 12,
            'terminal_classe_atual' => false,
        ]);
        $keys = array_column($cols, 'key');
        $this->assertSame(
            ['mft2', 'mac', 'pp', 'mt', 'cf', 'pg', 'ca'],
            $keys
        );
    }

    public function test_t3_11a_desenho_terminal_ca_e_cfd(): void
    {
        $cols = $this->regime->colunasDisciplina(3, 11, [
            'classe_inicio' => 11,
            'classe_fim' => 11,
            'classe_max' => 12,
            'terminal_classe_atual' => true,
        ]);
        $keys = array_column($cols, 'key');
        $this->assertSame(
            ['mft2', 'mac', 'pp', 'mt', 'cf', 'pg', 'ca', 'cfd'],
            $keys
        );
    }

    public function test_t3_12a_matematica_ca_e_cfd(): void
    {
        $cols = $this->regime->colunasDisciplina(3, 12, [
            'classe_inicio' => 10,
            'classe_fim' => 12,
            'classe_max' => 12,
            'terminal_classe_atual' => false,
        ]);
        $keys = array_column($cols, 'key');
        $this->assertSame(
            ['ca10', 'ca11', 'mft2', 'mac', 'pp', 'mt', 'cf', 'pg', 'ca', 'cfd'],
            $keys
        );
    }

    public function test_t3_12a_disciplina_11_12_ca_e_cfd(): void
    {
        $cols = $this->regime->colunasDisciplina(3, 12, [
            'classe_inicio' => 11,
            'classe_fim' => 12,
            'classe_max' => 12,
            'terminal_classe_atual' => false,
        ]);
        $keys = array_column($cols, 'key');
        $this->assertSame(
            ['ca11', 'mft2', 'mac', 'pp', 'mt', 'cf', 'pg', 'ca', 'cfd'],
            $keys
        );
    }

    public function test_t3_12a_ogi_ca_e_cfd(): void
    {
        $cols = $this->regime->colunasDisciplina(3, 12, [
            'classe_inicio' => 12,
            'classe_fim' => 12,
            'classe_max' => 12,
            'terminal_classe_atual' => false,
            'projeto_tecnologico' => false,
        ]);
        $keys = array_column($cols, 'key');
        $this->assertSame(
            ['mft2', 'mac', 'pp', 'mt', 'cf', 'pg', 'ca', 'cfd'],
            $keys
        );
    }

    public function test_t3_12a_projeto_tecnologico_so_ca(): void
    {
        $cols = $this->regime->colunasDisciplina(3, 12, [
            'classe_inicio' => 12,
            'classe_fim' => 12,
            'classe_max' => 12,
            'projeto_tecnologico' => true,
        ]);
        $keys = array_column($cols, 'key');
        $this->assertSame(
            ['mft2', 'mac', 'pp', 'mt', 'cf', 'pg', 'ca'],
            $keys
        );
    }

    public function test_t3_mt_somente_mac_pp_sem_pt(): void
    {
        $this->assertSame(
            [\App\Models\Nota::TIPO_MAC, \App\Models\Nota::TIPO_PP],
            $this->regime->componentesMediaTrimestral(3)
        );
        $this->assertSame(2, $this->regime->divisorMediaTrimestral(3));
        $this->assertFalse($this->regime->usaProvaTrimestral(3));
        $this->assertSame(3, $this->regime->divisorMediaTrimestral(1));
    }

    public function test_cfd_preview_11a_com_ca10(): void
    {
        $cfd = $this->regime->calcularCfdPreview([
            'ca' => 14,
            'ca10' => 12,
        ], 11, [
            'classe_inicio' => 10,
            'classe_fim' => 11,
            'classe_max' => 12,
            'terminal_classe_atual' => true,
        ]);

        $this->assertSame(13.0, $cfd);
    }

    public function test_cfd_preview_11a_terminal_lp_media_cas(): void
    {
        $cfd = $this->regime->calcularCfdPreview([
            'ca' => 14,
            'ca10' => 12,
        ], 11, [
            'classe_inicio' => 10,
            'classe_fim' => 11,
            'classe_max' => 12,
            'terminal_classe_atual' => true,
        ]);

        $this->assertSame(13.0, $cfd);
    }

    public function test_cfd_preview_11a_desenho_solo_classe(): void
    {
        $cfd = $this->regime->calcularCfdPreview(['ca' => 16], 11, [
            'classe_inicio' => 11,
            'classe_fim' => 11,
            'classe_max' => 12,
            'terminal_classe_atual' => true,
        ]);

        $this->assertSame(16.0, $cfd);
    }

    public function test_cfd_preview_12a_ogi_solo_classe(): void
    {
        $cfd = $this->regime->calcularCfdPreview(['ca' => 15], 12, [
            'classe_inicio' => 12,
            'classe_fim' => 12,
            'classe_max' => 12,
        ]);

        $this->assertSame(15.0, $cfd);
    }

    public function test_cfd_preview_12a_10_a_12_media_tres(): void
    {
        $cfd = $this->regime->calcularCfdPreview([
            'ca' => 12,
            'ca10' => 10,
            'ca11' => 14,
        ], 12, [
            'classe_inicio' => 10,
            'classe_fim' => 12,
            'classe_max' => 12,
        ]);

        $this->assertSame(12.0, $cfd);
    }

    public function test_cfd_preview_12a_10_a_12_exige_todas_cas(): void
    {
        $this->assertNull($this->regime->calcularCfdPreview([
            'ca' => 12,
            'ca11' => 14,
        ], 12, [
            'classe_inicio' => 10,
            'classe_fim' => 12,
            'classe_max' => 12,
        ]));
    }

    public function test_cfd_preview_12a_11_a_12_media_duas(): void
    {
        $cfd = $this->regime->calcularCfdPreview([
            'ca' => 16,
            'ca11' => 12,
        ], 12, [
            'classe_inicio' => 11,
            'classe_fim' => 12,
            'classe_max' => 12,
        ]);

        $this->assertSame(14.0, $cfd);
    }

    public function test_cfd_preview_12a_11_a_12_sem_ca11_nao_reduz_denominador(): void
    {
        $this->assertNull($this->regime->calcularCfdPreview([
            'ca' => 16,
        ], 12, [
            'classe_inicio' => 11,
            'classe_fim' => 12,
            'classe_max' => 12,
        ]));
    }

    public function test_cfd_preview_11a_sem_ca10_em_intervalo_10_11(): void
    {
        $cfd = $this->regime->calcularCfdPreview(['ca' => 14], 11, [
            'classe_inicio' => 10,
            'classe_fim' => 11,
            'classe_max' => 12,
        ]);

        $this->assertNull($cfd);
    }

    public function test_cfd_preview_10a_terminal(): void
    {
        $cfd = $this->regime->calcularCfdPreview(['ca' => 15], 10, [
            'classe_inicio' => 10,
            'classe_fim' => 10,
            'classe_max' => 12,
        ]);

        $this->assertSame(15.0, $cfd);
    }

    public function test_cfd_preview_solo_cf_pg(): void
    {
        $cfd = $this->regime->calcularCfdPreview(['cf' => 12, 'pg' => 16], 10);

        $this->assertSame(14.0, $cfd);
    }

    public function test_t1_12a_faltas_fundidas_e_mtp(): void
    {
        $cols = $this->regime->colunasDisciplina(1, 12, [
            'faltas_fundidas' => true,
            'usa_mtp' => true,
        ]);
        $headers = array_column($cols, 'header');
        $this->assertContains('FALTAS', $headers);
        $this->assertContains('MTP1', $headers);
        $this->assertCount(5, $cols);
    }
}
