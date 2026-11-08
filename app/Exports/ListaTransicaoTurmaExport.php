<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ListaTransicaoTurmaExport implements WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $dados
     * @param  array<int, string>|null  $apenasGrupos
     */
    public function __construct(
        protected array $dados,
        protected ?array $apenasGrupos = null,
    ) {}

    public function sheets(): array
    {
        $sheets = [];
        $grupos = $this->dados['grupos'] ?? [];
        $ordem = \App\Services\ListaTransicaoTurmaService::gruposOrdem(
            (int) ($this->dados['classe_numero'] ?? 10)
        );

        if ($this->apenasGrupos === null) {
            $sheets[] = new ListaTransicaoTurmaResumoSheetExport($this->dados);
        }

        foreach ($ordem as $grupo) {
            if ($this->apenasGrupos !== null && ! in_array($grupo, $this->apenasGrupos, true)) {
                continue;
            }

            $sheets[] = new ListaTransicaoTurmaSheetExport(
                $grupo,
                $this->dados,
                $grupos[$grupo] ?? [],
            );
        }

        return $sheets;
    }
}
