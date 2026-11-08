<?php

namespace Tests\Unit;

use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Services\RegimePauta\RegimePautaInterface;
use App\Services\RegimePauta\RegimePolitecnico;
use App\Support\BoletimColunas;
use App\Support\BoletimNotasBuilder;
use PHPUnit\Framework\TestCase;

class BoletimNotasBuilderTest extends TestCase
{
    public function test_chave_resultado_t2_politecnico_usa_mft2(): void
    {
        $regime = new RegimePolitecnico();

        $this->assertSame('mft2', BoletimColunas::chaveResultado(2, $regime));
        $this->assertSame('mt', BoletimColunas::chaveResultado(1, $regime));
        $this->assertSame('ca', BoletimColunas::chaveResultado(3, $regime));
    }

    public function test_colunas_t3_politecnico_incluem_cf_antes_de_pg(): void
    {
        $regime = new RegimePolitecnico();
        $colunas = BoletimColunas::paraTrimestre(3, $regime, 12);
        $keys = array_column($colunas, 'key');

        $this->assertSame(
            ['mft2', 'mac', 'pp', 'mt', 'cf', 'pg', 'ca', 'recurso', 'nota_final'],
            $keys
        );
        $this->assertSame('CA-12ª', collect($colunas)->firstWhere('key', 'ca')['header']);
    }

    public function test_calcular_mft2_automaticamente(): void
    {
        $builder = new class(1, 1, 1, '2025-2026') extends BoletimNotasBuilder {
            public function exporMFT2(?float $mt1, ?float $mt2): ?float
            {
                return $this->calcularMFT2($mt1, $mt2);
            }

            public function exporMT(?float $mac, ?float $pp, ?float $pt, int $trimestre): ?float
            {
                return $this->calcularMT($mac, $pp, $pt, new RegimePolitecnico(), $trimestre);
            }
        };

        $this->assertSame(14.0, $builder->exporMFT2(14, 14));
        $this->assertSame(10.0, $builder->exporMFT2(8, 12));
        $this->assertSame(9.0, $builder->exporMFT2(6, 12));
        $this->assertNull($builder->exporMFT2(null, 12));
        $this->assertNull($builder->exporMFT2(10, null));
    }

    public function test_calcular_mt3_somente_mac_pp(): void
    {
        $builder = new class(1, 1, 1, '2025-2026') extends BoletimNotasBuilder {
            public function exporMT(?float $mac, ?float $pp, ?float $pt, int $trimestre): ?float
            {
                return $this->calcularMT($mac, $pp, $pt, new RegimePolitecnico(), $trimestre);
            }
        };

        $this->assertSame(13.0, $builder->exporMT(12, 14, null, 3));
        $this->assertSame(14.0, $builder->exporMT(14, 14, 14, 2));
    }

    public function test_mt3_cf_ca_ignoram_mt_persistida_desactualizada(): void
    {
        $disciplina = new Disciplina();
        $disciplina->id = 99;
        $disciplina->exists = true;

        $builder = new class(1, 1, 1, '2025-2026') extends BoletimNotasBuilder {
            protected function valor(int $disciplinaId, int $trimestre, string $tipo): ?float
            {
                return match (true) {
                    $trimestre === 3 && $tipo === Nota::TIPO_MAC => 11.0,
                    $trimestre === 3 && $tipo === Nota::TIPO_PP => 10.0,
                    $trimestre === 3 && $tipo === Nota::TIPO_MT => 7.0, // desactualizada
                    $trimestre === 1 && $tipo === Nota::TIPO_MT => 11.0,
                    $trimestre === 2 && $tipo === Nota::TIPO_MAC => 11.0,
                    $trimestre === 2 && $tipo === Nota::TIPO_PP => 11.0,
                    $trimestre === 2 && $tipo === Nota::TIPO_PT => 11.0,
                    $trimestre === 2 && $tipo === Nota::TIPO_MT => 11.0,
                    default => null,
                };
            }

            protected function valorAnual(int $disciplinaId, string $tipo): ?float
            {
                return null;
            }

            protected function valorPg(int $disciplinaId, RegimePautaInterface $regime): ?float
            {
                return 10.0;
            }

            protected function valorRecuperacaoAnual(int $disciplinaId): ?float
            {
                return null;
            }

            protected function valorRecurso(int $disciplinaId): ?float
            {
                return null;
            }
        };

        $linha = $builder->linhaTrimestre($disciplina, 3, new RegimePolitecnico(), 12);

        // MT3 = ROUND((11+10)/2) = 11 — não a MT persistida 7
        $this->assertSame(11.0, $linha['mt']);
        // CF = ROUND((11+11)/2) = 11
        $this->assertSame(11.0, $linha['cf']);
        // CA = ROUND(0.6*11 + 0.4*10) = ROUND(10.6) = 11
        $this->assertSame(11.0, $linha['ca']);
    }

    public function test_com_notas_prefere_pos_conselho_e_evita_query(): void
    {
        $pre = (object) [
            'aluno_id' => 1,
            'disciplina_id' => 5,
            'trimestre' => 1,
            'tipo' => Nota::TIPO_MAC,
            'valor' => 8,
            'estado_conselho' => Nota::ESTADO_PRE_CONSELHO,
            'archived_at' => null,
        ];
        $pos = (object) [
            'aluno_id' => 1,
            'disciplina_id' => 5,
            'trimestre' => 1,
            'tipo' => Nota::TIPO_MAC,
            'valor' => 14,
            'estado_conselho' => Nota::ESTADO_POS_CONSELHO,
            'archived_at' => null,
        ];

        $builder = new class(1, 1, 1, '2025-2026') extends BoletimNotasBuilder {
            public function exporValor(int $disciplinaId, int $trimestre, string $tipo): ?float
            {
                return $this->valor($disciplinaId, $trimestre, $tipo);
            }
        };

        $builder->comNotas([$pre, $pos]);

        $this->assertSame(14.0, $builder->exporValor(5, 1, Nota::TIPO_MAC));
        $this->assertNull($builder->exporValor(5, 1, Nota::TIPO_PP));
    }
}
