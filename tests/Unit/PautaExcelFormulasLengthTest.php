<?php

namespace Tests\Unit;

use App\Support\PautaExcelFormulasTransicao;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PHPUnit\Framework\TestCase;

class PautaExcelFormulasLengthTest extends TestCase
{
    private const EXCEL_FORMULA_MAX = 8192;

    public function test_formula_observacao_respeita_limite_excel_com_muitas_disciplinas(): void
    {
        foreach ([15, 18, 20] as $totalDisciplinas) {
            $builder = $this->builderComDisciplinas($totalDisciplinas);
            $formula = $builder->formulaObservacao(15);

            $this->assertLessThanOrEqual(
                self::EXCEL_FORMULA_MAX,
                strlen($formula),
                "Fórmula OBS com {$totalDisciplinas} disciplinas excede o limite Excel (".strlen($formula).' chars)',
            );
        }
    }

    private function builderComDisciplinas(int $total): PautaExcelFormulasTransicao
    {
        $cells = [];
        $meta = [];

        for ($i = 1; $i <= $total; $i++) {
            $col = 30 + $i * 3;
            $cells[$i] = [
                'pg' => Coordinate::stringFromColumnIndex($col),
                'ca' => Coordinate::stringFromColumnIndex($col + 1),
                'cfd' => Coordinate::stringFromColumnIndex($col + 2),
            ];
            $meta[$i] = [
                'codigo' => 'D'.$i,
                'classe_fim' => $i % 3 === 0 ? 10 : 12,
                'terminal' => $i % 3 === 0,
                'usa_cfd' => $i % 3 === 0,
                'continua' => $i % 3 !== 0,
            ];
        }

        return new PautaExcelFormulasTransicao($cells, $meta, [], 10);
    }
}
