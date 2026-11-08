<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Mini-pauta anual: uma folha Excel por trimestre (1.º, 2.º e 3.º).
 */
class MiniPautaAnualExport implements WithMultipleSheets
{
    /**
     * @param  array<int, array<string, mixed>>  $trimestresDados  chave = 1|2|3
     */
    public function __construct(
        protected array $trimestresDados,
    ) {}

    public function sheets(): array
    {
        $sheets = [];

        foreach ([1, 2, 3] as $trimestre) {
            if (! isset($this->trimestresDados[$trimestre])) {
                continue;
            }

            $sheets[] = new MiniPautaExport($this->trimestresDados[$trimestre]);
        }

        return $sheets;
    }
}
