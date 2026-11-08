<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PautaFinalDisciplinaExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
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
                $aluno['numero_ordem'],
                $aluno['nome'],
                $aluno['mt1'] !== null ? number_format($aluno['mt1'], 0) : '-',
                $aluno['mt2'] !== null ? number_format($aluno['mt2'], 0) : '-',
                $aluno['mt3'] !== null ? number_format($aluno['mt3'], 0) : '-',
                $aluno['nota_final'] !== null ? number_format($aluno['nota_final'], 0) : '-',
                $this->traduzirSituacao($aluno['situacao']),
            ];
        }

        // Linha em branco e estatisticas
        $rows[] = [];
        $rows[] = ['Estatisticas', '', '', '', '', '', ''];
        $rows[] = ['Total de Alunos', $this->dados['estatisticas']['total_alunos'], '', '', '', '', ''];
        $rows[] = ['Aprovados', $this->dados['estatisticas']['aprovados'], '', '', '', '', ''];
        $rows[] = ['Reprovados', $this->dados['estatisticas']['reprovados'], '', '', '', '', ''];
        $rows[] = ['Media da Turma', $this->dados['estatisticas']['media_turma'] ?? '-', '', '', '', '', ''];

        return $rows;
    }

    public function headings(): array
    {
        return [
            'N.o',
            'Nome do Aluno',
            '1.o T',
            '2.o T',
            '3.o T',
            'Final',
            'Situacao',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $turma = $this->dados['turma']->nome;
        $disciplina = $this->dados['disciplina']->nome;
        $ano = $this->dados['ano_lectivo'];

        $sheet->insertNewRowBefore(1, 4);
        $sheet->setCellValue('A1', 'Pauta Final - Disciplina');
        $sheet->setCellValue('A2', "Turma: {$turma} | Disciplina: {$disciplina}");
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
        return 'Pauta Final';
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
