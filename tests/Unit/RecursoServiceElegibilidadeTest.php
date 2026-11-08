<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RecursoServiceElegibilidadeTest extends TestCase
{
    /**
     * Replica a lógica de categorização do RecursoService (sem BD).
     *
     * @param  array<int, float>  $cfdsPorDisc
     * @param  array<int>  $blindadasIds
     * @return array{elegivel: bool, motivo: ?string, disciplinas_elegiveis: array<int, array{disciplina_id: int, cfd: float}>}
     */
    private function avaliar(array $cfdsPorDisc, array $blindadasIds, array $cfg = []): array
    {
        $cfg = array_merge([
            'nota_min' => 6,
            'nota_max' => 9,
            'max_disciplinas' => 3,
        ], $cfg);

        $negativasNaFaixa = [];
        $negativasForaFaixa = [];
        foreach ($cfdsPorDisc as $discId => $cfd) {
            if ($cfd < $cfg['nota_min']) {
                $negativasForaFaixa[$discId] = $cfd;
            } elseif ($cfd <= $cfg['nota_max']) {
                $negativasNaFaixa[$discId] = $cfd;
            }
        }

        $elegivel = true;
        $motivo = null;

        if ($cfdsPorDisc === []) {
            $elegivel = false;
            $motivo = 'Sem CFDs lançadas para este aluno.';
        } elseif ($negativasForaFaixa !== []) {
            $elegivel = false;
            $motivo = 'CFD abaixo do mínimo para recurso.';
        } elseif (count($negativasNaFaixa) === 0) {
            $elegivel = false;
            $motivo = 'Sem disciplinas na faixa de recurso (6–9).';
        } elseif (count($negativasNaFaixa) > $cfg['max_disciplinas']) {
            $elegivel = false;
            $motivo = 'Mais de 3 disciplinas na faixa de recurso.';
        } elseif ($blindadasIds !== []) {
            $blindadasNeg = array_filter(
                $blindadasIds,
                fn ($id) => isset($negativasNaFaixa[$id]) || isset($negativasForaFaixa[$id])
            );
            if (count($blindadasNeg) === count($blindadasIds)) {
                $elegivel = false;
                $motivo = 'Todas as disciplinas blindadas negativas simultaneamente.';
            }
        }

        $disciplinasElegiveis = [];
        if ($elegivel) {
            foreach ($negativasNaFaixa as $discId => $cfd) {
                $disciplinasElegiveis[] = ['disciplina_id' => $discId, 'cfd' => $cfd];
            }
        }

        return [
            'elegivel' => $elegivel,
            'motivo' => $motivo,
            'disciplinas_elegiveis' => $disciplinasElegiveis,
        ];
    }

    public function test_recurso_elegivel_com_ate_tres_disciplinas_na_faixa(): void
    {
        $r = $this->avaliar([1 => 7.0, 2 => 8.0], []);

        $this->assertTrue($r['elegivel']);
        $this->assertCount(2, $r['disciplinas_elegiveis']);
    }

    public function test_recurso_bloqueado_com_cfd_abaixo_de_seis(): void
    {
        $r = $this->avaliar([1 => 5.0, 2 => 8.0], []);

        $this->assertFalse($r['elegivel']);
    }

    public function test_recurso_bloqueado_com_mais_de_tres_negativas(): void
    {
        $r = $this->avaliar([1 => 7.0, 2 => 8.0, 3 => 6.0, 4 => 9.0], []);

        $this->assertFalse($r['elegivel']);
    }

    public function test_recurso_bloqueado_quando_todas_blindadas_negativas(): void
    {
        $r = $this->avaliar([1 => 7.0, 2 => 8.0], [1, 2]);

        $this->assertFalse($r['elegivel']);
    }
}
