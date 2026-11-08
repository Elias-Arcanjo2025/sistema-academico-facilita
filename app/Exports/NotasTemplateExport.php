<?php

namespace App\Exports;

use App\Models\Aluno;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\Turma;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class NotasTemplateExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $turma;
    protected $disciplinas;
    protected $trimestre;
    protected $anoLectivoId;

    public function __construct(Turma $turma, $disciplinas, int $trimestre, int $anoLectivoId)
    {
        $this->turma = $turma;
        $this->disciplinas = $disciplinas;
        $this->trimestre = $trimestre;
        $this->anoLectivoId = $anoLectivoId;
    }

    public function collection()
    {
        $alunos = Aluno::where('turma_id', $this->turma->id)
            ->activo()
            ->orderBy('numero_ordem')
            ->get();

        // Buscar notas existentes de uma vez
        $notasExistentes = Nota::where('turma_id', $this->turma->id)
            ->where('trimestre', $this->trimestre)
            ->where('ano_lectivo_id', $this->anoLectivoId)
            ->get()
            ->groupBy(function($nota) {
                return $nota->aluno_id . '_' . $nota->disciplina_id . '_' . $nota->tipo;
            });

        $rows = collect();

        foreach ($alunos as $aluno) {
            $row = [
                $aluno->numero_ordem,
                $aluno->nome,
            ];

            // Para cada disciplina, adicionar MAC, PP, PT
            foreach ($this->disciplinas as $disciplina) {
                // MAC
                $keyMAC = $aluno->id . '_' . $disciplina->id . '_' . Nota::TIPO_MAC;
                $notaMAC = $notasExistentes->get($keyMAC)?->first();
                $row[] = $notaMAC ? number_format($notaMAC->valor, 0, ',', '') : '';

                // PP
                $keyPP = $aluno->id . '_' . $disciplina->id . '_' . Nota::TIPO_PP;
                $notaPP = $notasExistentes->get($keyPP)?->first();
                $row[] = $notaPP ? number_format($notaPP->valor, 0, ',', '') : '';

                // PT
                $keyPT = $aluno->id . '_' . $disciplina->id . '_' . Nota::TIPO_PT;
                $notaPT = $notasExistentes->get($keyPT)?->first();
                $row[] = $notaPT ? number_format($notaPT->valor, 0, ',', '') : '';
            }

            $rows->push($row);
        }

        return $rows;
    }

    public function headings(): array
    {
        $headers = ['Nº', 'Nome do Aluno'];

        foreach ($this->disciplinas as $disciplina) {
            $headers[] = $disciplina->codigo . ' - MAC';
            $headers[] = $disciplina->codigo . ' - PP';
            $headers[] = $disciplina->codigo . ' - PT';
        }

        return $headers;
    }

    public function styles(Worksheet $sheet)
    {
        // Estilo do cabeçalho
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Altura da linha do cabeçalho
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Alinhar colunas de notas ao centro
        $lastColumn = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();

        if ($lastRow > 1) {
            $sheet->getStyle('C2:' . $lastColumn . $lastRow)->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ]);

            // Bordas em todas as células
            $sheet->getStyle('A1:' . $lastColumn . $lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
            ]);
        }

        return [];
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 6,  // Nº
            'B' => 35, // Nome
        ];

        // Largura para cada disciplina (MAC, PP, PT)
        $column = 'C';
        foreach ($this->disciplinas as $disciplina) {
            $widths[$column] = 12; // MAC
            $column++;
            $widths[$column] = 12; // PP
            $column++;
            $widths[$column] = 12; // PT
            $column++;
        }

        return $widths;
    }

    public function title(): string
    {
        return $this->turma->nome . ' - ' . $this->trimestre . 'º Trim';
    }
}
