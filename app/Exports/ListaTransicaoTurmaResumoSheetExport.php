<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ListaTransicaoTurmaResumoSheetExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    /**
     * @param  array<string, mixed>  $dados
     */
    public function __construct(protected array $dados) {}

    public function array(): array
    {
        $rows = [];
        $total = 0;

        foreach (\App\Services\ListaTransicaoTurmaService::gruposOrdem(
            (int) ($this->dados['classe_numero'] ?? 10)
        ) as $grupo) {
            $qtd = (int) ($this->dados['resumo'][$grupo] ?? 0);
            $total += $qtd;
            $rows[] = [$grupo, $qtd];
        }

        $rows[] = [];
        $rows[] = ['TOTAL', $total];

        return $rows;
    }

    public function headings(): array
    {
        return ['Resultado', 'N.º alunos'];
    }

    public function title(): string
    {
        return 'Resumo';
    }

    public function styles(Worksheet $sheet): array
    {
        $turma = $this->dados['turma']->nome ?? '';
        $trim = $this->dados['trimestre'] ?? '';
        $ano = $this->dados['ano_lectivo'] ?? '';

        $sheet->insertNewRowBefore(1, 3);
        $sheet->setCellValue('A1', 'Resumo de transição — '.$turma);
        $sheet->setCellValue('A2', "T{$trim} · {$ano} · DE 424/25");

        return [
            1 => ['font' => ['bold' => true, 'size' => 13]],
            4 => ['font' => ['bold' => true], 'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0'],
            ]],
        ];
    }
}
