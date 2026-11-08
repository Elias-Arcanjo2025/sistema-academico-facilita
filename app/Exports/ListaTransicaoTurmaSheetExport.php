<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ListaTransicaoTurmaSheetExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    /**
     * @param  array<string, mixed>  $contexto
     * @param  array<int, array<string, mixed>>  $linhas
     */
    public function __construct(
        protected string $grupo,
        protected array $contexto,
        protected array $linhas,
    ) {}

    public function array(): array
    {
        if ($this->linhas === []) {
            return [['—', '', '', '', '', '', '', 0]];
        }

        return array_map(fn (array $l) => [
            $l['numero_ordem'],
            $l['numero_processo'],
            $l['nome'],
            $l['resultado'],
            $l['observacao'],
            $l['deficiencias'],
            $l['recuperacao'],
            $l['todas_negativas'],
            $l['total_negativas'],
        ], $this->linhas);
    }

    public function headings(): array
    {
        return [
            'N.º Ordem',
            'N.º Processo',
            'Nome Completo',
            'Resultado',
            'Observação',
            'Deficiências (contínuas)',
            'Recuperação (terminais)',
            'Todas as negativas',
            'Total',
        ];
    }

    public function title(): string
    {
        return mb_substr($this->grupo, 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        $turma = $this->contexto['turma']->nome ?? '';
        $trim = $this->contexto['trimestre'] ?? '';
        $ano = $this->contexto['ano_lectivo'] ?? '';
        $classe = $this->contexto['classe_numero'] ?? '';

        $sheet->insertNewRowBefore(1, 5);
        $sheet->setCellValue('A1', 'Lista por resultado — '.$this->grupo);
        $sheet->setCellValue('A2', "Turma: {$turma} · {$classe}ª classe · T{$trim} · {$ano}");
        $sheet->setCellValue('A3', ((int) ($classe ?? 0) === 12)
            ? 'Resultado 12.ª: APTO / RECURSO / N/APTO'
            : 'Resultado 10.ª/11.ª: TRANSITA / TRANSITA/C DEF / RECUPERAÇÃO / N/TRANSITA');
        $sheet->setCellValue('A4', 'Total nesta lista: '.count($this->linhas));

        $corCabecalho = match ($this->grupo) {
            'TRANSITA', 'APTO' => 'D9E8F7',
            'TRANSITA/C DEF', 'RECURSO', 'RECUPERAÇÃO' => 'FFF3CD',
            'N/TRANSITA', 'N/APTO' => 'F8D7DA',
            'DESISTENTE' => 'E2E3E5',
            default => 'E0E0E0',
        };

        return [
            1 => ['font' => ['bold' => true, 'size' => 13]],
            2 => ['font' => ['bold' => true]],
            3 => ['font' => ['italic' => true, 'size' => 9]],
            6 => ['font' => ['bold' => true], 'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $corCabecalho],
            ]],
        ];
    }
}
