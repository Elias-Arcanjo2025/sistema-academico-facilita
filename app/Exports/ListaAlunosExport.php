<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ListaAlunosExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    protected array $dados;

    public function __construct(array $dados)
    {
        $this->dados = $dados;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->dados['alunos'] as $aluno) {
            $rows[] = [
                $aluno->numero_ordem,
                $aluno->numero_processo,
                $aluno->nome,
                $aluno->data_nascimento ? $aluno->data_nascimento->format('d/m/Y') : '-',
                $aluno->genero === 'M' ? 'Masculino' : 'Feminino',
                $aluno->activo ? 'Activo' : 'Inactivo',
            ];
        }

        // Linha em branco e total
        $rows[] = [];
        $rows[] = ['Total de Alunos:', $this->dados['total_alunos'], '', '', '', ''];

        return $rows;
    }

    public function headings(): array
    {
        return [
            'N.o Ordem',
            'N.o Processo',
            'Nome Completo',
            'Data Nasc.',
            'Genero',
            'Estado',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $turma = $this->dados['turma']->nome;
        $curso = $this->dados['curso']->nome;

        $sheet->insertNewRowBefore(1, 4);
        $sheet->setCellValue('A1', 'Lista de Alunos');
        $sheet->setCellValue('A2', "Turma: {$turma}");
        $sheet->setCellValue('A3', "Curso: {$curso}");

        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['bold' => true]],
            3 => ['font' => ['italic' => true]],
            5 => ['font' => ['bold' => true], 'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0'],
            ]],
        ];
    }

    public function title(): string
    {
        return 'Lista de Alunos';
    }
}
