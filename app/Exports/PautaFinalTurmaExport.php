<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PautaFinalTurmaExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    protected array $dados;

    public function __construct(array $dados)
    {
        $this->dados = $dados;
    }

    public function array(): array
    {
        $rows = [];
        $disciplinas = $this->dados['disciplinas'];

        foreach ($this->dados['alunos'] as $alunoData) {
            $row = [
                $alunoData['numero_ordem'],
                $alunoData['nome'],
            ];

            foreach ($disciplinas as $disc) {
                $notaDisc = $alunoData['disciplinas'][$disc->id] ?? null;
                $valor = $notaDisc['nota_final'] ?? null;
                $row[] = $valor !== null ? number_format($valor, 0) : '-';
            }

            $row[] = $alunoData['media_geral'] !== null ? number_format($alunoData['media_geral'], 0) : '-';
            $row[] = $this->traduzirSituacao($alunoData['situacao_geral'] ?? 'pendente');

            $rows[] = $row;
        }

        return $rows;
    }

    public function headings(): array
    {
        $headings = ['N.o', 'Nome do Aluno'];

        foreach ($this->dados['disciplinas'] as $disc) {
            $headings[] = $disc->codigo ?? substr($disc->nome, 0, 6);
        }

        $headings[] = 'Media';
        $headings[] = 'Situacao';

        return $headings;
    }

    public function styles(Worksheet $sheet): array
    {
        $turma = $this->dados['turma']->nome;
        $curso = $this->dados['turma']->curso->nome;
        $ano = $this->dados['ano_lectivo'];

        $sheet->insertNewRowBefore(1, 4);
        $sheet->setCellValue('A1', 'Pauta Final da Turma');
        $sheet->setCellValue('A2', "Turma: {$turma} | Curso: {$curso}");
        $sheet->setCellValue('A3', "Ano Lectivo {$ano}");

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
        return 'Pauta Final Turma';
    }

    protected function traduzirSituacao(string $situacao): string
    {
        return match($situacao) {
            'aprovado' => 'Aprovado',
            'reprovado' => 'Reprovado',
            default => 'Pendente',
        };
    }
}
