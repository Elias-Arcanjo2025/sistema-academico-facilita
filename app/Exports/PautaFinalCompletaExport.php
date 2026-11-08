<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PautaFinalCompletaExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    protected array $dados;

    protected int $colsPorDisc;

    protected bool $usaPtT3;

    public function __construct(array $dados)
    {
        $this->dados = $dados;
        $this->usaPtT3 = (bool) ($dados['usa_pt_t3'] ?? true);
        $colsT3 = $this->usaPtT3 ? 4 : 3;
        $this->colsPorDisc = $dados['cols_por_disciplina'] ?? (4 + 4 + $colsT3 + 5);
    }

    public function array(): array
    {
        $rows = [];
        $disciplinas = $this->dados['disciplinas'];

        foreach ($this->dados['alunos'] as $alunoData) {
            $row = [
                $alunoData['numero_ordem'],
                $alunoData['numero_processo'] ?? '-',
                $alunoData['nome'],
                strtoupper(substr($alunoData['genero'] ?? '—', 0, 1)),
            ];

            foreach ($disciplinas as $disc) {
                $dadosDisc = $alunoData['disciplinas'][$disc->id] ?? null;

                for ($trim = 1; $trim <= 2; $trim++) {
                    $trimData = $dadosDisc['trimestres'][$trim] ?? [];
                    $row[] = $this->fmt($trimData['mac'] ?? null);
                    $row[] = $this->fmt($trimData['pp'] ?? null);
                    $row[] = $this->fmt($trimData['pt'] ?? null);
                    $row[] = $this->fmt($trimData['mt'] ?? null);
                }

                $t3 = $dadosDisc['trimestres'][3] ?? [];
                $row[] = $this->fmt($t3['mac'] ?? null);
                $row[] = $this->fmt($t3['pp'] ?? null);
                if ($this->usaPtT3) {
                    $row[] = $this->fmt($t3['pt'] ?? null);
                }
                $row[] = $this->fmt($t3['mt'] ?? null);

                $fin = $dadosDisc['finais'] ?? [];
                foreach (['mft2', 'cf', 'pg', 'ca', 'cfd'] as $k) {
                    $row[] = $this->fmt($fin[$k] ?? null);
                }
            }

            $row[] = $alunoData['observacoes'] ?? '-';
            $row[] = $alunoData['resultado'] ?? 'Pendente';

            $rows[] = $row;
        }

        return $rows;
    }

    private function fmt(mixed $val): string
    {
        if ($val === null || $val === '') {
            return '-';
        }

        return (string) (int) round((float) $val);
    }

    public function headings(): array
    {
        $disciplinas = $this->dados['disciplinas'];
        $linha1 = ['N.º', 'Proc.', 'Nome Completo', 'Sexo'];

        foreach ($disciplinas as $disc) {
            $linha1[] = $disc->nome;
            for ($i = 1; $i < $this->colsPorDisc; $i++) {
                $linha1[] = '';
            }
        }

        $linha1[] = 'Observações';
        $linha1[] = 'Resultado';

        $linha2 = ['', '', '', ''];

        foreach ($disciplinas as $disc) {
            $linha2[] = 'T1 MAC';
            $linha2[] = 'T1 PP';
            $linha2[] = 'T1 PT';
            $linha2[] = 'T1 MT';
            $linha2[] = 'T2 MAC';
            $linha2[] = 'T2 PP';
            $linha2[] = 'T2 PT';
            $linha2[] = 'T2 MT';
            $linha2[] = 'T3 MAC';
            $linha2[] = 'T3 PP';
            if ($this->usaPtT3) {
                $linha2[] = 'T3 PT';
            }
            $linha2[] = 'T3 MT';
            foreach (['MFT2', 'CF', 'PG', 'CA', 'CFD'] as $h) {
                $linha2[] = $h;
            }
        }

        $linha2[] = '';
        $linha2[] = '';

        return [$linha1, $linha2];
    }

    public function styles(Worksheet $sheet): array
    {
        $turma = $this->dados['turma']->nome;
        $curso = $this->dados['turma']->curso->nome;
        $classe = $this->dados['turma']->classe->nome ?? '-';
        $ano = $this->dados['ano_lectivo'];
        $disciplinas = $this->dados['disciplinas'];

        $sheet->insertNewRowBefore(1, 5);
        $sheet->setCellValue('A1', 'PAUTA FINAL COMPLETA');
        $sheet->setCellValue('A2', "Turma: {$turma} | Classe: {$classe}");
        $sheet->setCellValue('A3', "Curso: {$curso} | Ano Lectivo: {$ano}");

        $currentCol = 5;
        foreach ($disciplinas as $disc) {
            $startCol = Coordinate::stringFromColumnIndex($currentCol);
            $endCol = Coordinate::stringFromColumnIndex($currentCol + $this->colsPorDisc - 1);
            $sheet->mergeCells("{$startCol}6:{$endCol}6");
            $currentCol += $this->colsPorDisc;
        }

        $sheet->mergeCells('A6:A7');
        $sheet->mergeCells('B6:B7');
        $sheet->mergeCells('C6:C7');
        $sheet->mergeCells('D6:D7');

        $totalCols = 4 + (count($disciplinas) * $this->colsPorDisc) + 2;
        $obsCol = Coordinate::stringFromColumnIndex($totalCols - 1);
        $resCol = Coordinate::stringFromColumnIndex($totalCols);
        $sheet->mergeCells("{$obsCol}6:{$obsCol}7");
        $sheet->mergeCells("{$resCol}6:{$resCol}7");

        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['bold' => true]],
            3 => ['font' => ['bold' => true]],
            6 => [
                'font' => ['bold' => true, 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            7 => [
                'font' => ['bold' => true, 'size' => 9],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E0E0']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function title(): string
    {
        return 'Pauta Final Completa';
    }
}
