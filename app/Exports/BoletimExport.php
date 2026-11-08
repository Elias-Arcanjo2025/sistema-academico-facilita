<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BoletimExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    protected array $dados;

    public function __construct(array $dados)
    {
        $this->dados = $dados;
    }

    public function array(): array
    {
        $rows = [];
        $isTrimestreEspecifico = isset($this->dados['trimestre']);

        if ($isTrimestreEspecifico) {
            $notas = $this->dados['notas'] ?? [];
            $colunas = $this->dados['colunas'] ?? [];
            $chaveResultado = $this->dados['chave_resultado'] ?? 'mt';
            $resumo = $this->dados['resumo'] ?? [];

            foreach ($notas as $nota) {
                $disciplinaNome = is_string($nota['disciplina']) ? $nota['disciplina'] : $nota['disciplina']->nome;
                $valorResultado = $nota['valor_resultado'] ?? ($nota[$chaveResultado] ?? null);
                $situacao = $valorResultado !== null ? ($valorResultado >= 10 ? 'aprovado' : 'reprovado') : 'pendente';

                $row = [$disciplinaNome];
                foreach ($colunas as $coluna) {
                    $valor = $nota[$coluna['key']] ?? null;
                    $row[] = $valor !== null ? number_format($valor, 0) : '-';
                }
                $row[] = $this->traduzirSituacao($situacao);
                $rows[] = $row;
            }

            $rows[] = [];
            $mediaGeral = $resumo['media_geral'] ?? null;
            $rows[] = array_merge(['Média do Trimestre'], array_fill(0, count($colunas), ''), [
                $mediaGeral !== null ? number_format($mediaGeral, 0) : '-',
                '',
            ]);
            $rows[] = array_merge(['Aprovadas'], array_fill(0, count($colunas), ''), [
                (string) ($resumo['aprovadas'] ?? 0),
                '',
            ]);
            $rows[] = array_merge(['Reprovadas'], array_fill(0, count($colunas), ''), [
                (string) ($resumo['reprovadas'] ?? 0),
                '',
            ]);

            $rows[] = [];
            $rows[] = ['Observação:', $this->gerarObservacaoTrimestre()];
        } else {
            foreach ($this->dados['disciplinas'] as $disc) {
                $disciplinaNome = is_string($disc['disciplina']) ? $disc['disciplina'] : $disc['disciplina']->nome;
                $rows[] = [
                    $disciplinaNome,
                    $disc['mt1'] !== null ? number_format($disc['mt1'], 0) : '-',
                    $disc['mt2'] !== null ? number_format($disc['mt2'], 0) : '-',
                    $disc['mt3'] !== null ? number_format($disc['mt3'], 0) : '-',
                    $disc['nota_final'] !== null ? number_format($disc['nota_final'], 0) : '-',
                    $this->traduzirSituacao($disc['situacao']),
                ];
            }

            $rows[] = [];
            $rows[] = ['Média Geral', '', '', '', $this->dados['media_geral'] ?? '-', ''];
            $rows[] = ['Situação Final', '', '', '', '', $this->traduzirSituacao($this->dados['situacao_final'])];
            $rows[] = ['Disciplinas com Negativa', '', '', '', $this->dados['disciplinas_reprovadas'], ''];

            $rows[] = [];
            $rows[] = ['Observação:', $this->gerarObservacao()];
        }

        return $rows;
    }

    public function headings(): array
    {
        if (isset($this->dados['trimestre'])) {
            $headers = ['Disciplina'];
            foreach ($this->dados['colunas'] ?? [] as $coluna) {
                $headers[] = $coluna['header'];
            }
            $headers[] = 'Situação';

            return $headers;
        }

        return ['Disciplina', '1.º Trim.', '2.º Trim.', '3.º Trim.', 'Final', 'Situação'];
    }

    public function styles(Worksheet $sheet): array
    {
        $aluno = $this->dados['aluno'];
        $turma = $this->dados['turma'];
        $curso = $this->dados['curso'];
        $ano = $this->dados['ano_lectivo'];
        $classe = $turma->classe->nome ?? '-';
        $sala = $turma->sala->nome ?? '-';
        $isTrimestreEspecifico = isset($this->dados['trimestre']);

        $periodo = $isTrimestreEspecifico
            ? "{$this->dados['trimestre']}.º Trimestre"
            : 'Anual';

        $sheet->insertNewRowBefore(1, 8);
        $sheet->setCellValue('A1', 'Boletim de Notas');
        $sheet->setCellValue('A2', "Nome: {$aluno->nome}");
        $sheet->setCellValue('A3', "CURSO: {$curso->nome}");
        $sheet->setCellValue('A4', "Ano Lectivo: {$ano}");
        $sheet->setCellValue('C4', "Classe: {$classe}");
        $sheet->setCellValue('A5', "Turma: {$turma->nome}");
        $sheet->setCellValue('C5', "Sala: {$sala}");
        $sheet->setCellValue('A6', "Período: {$periodo}");
        $sheet->setCellValue('C6', "Nº: {$aluno->numero_ordem}");

        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            2 => ['font' => ['bold' => true, 'size' => 12]],
            3 => ['font' => ['italic' => true]],
            4 => ['font' => ['italic' => true]],
            5 => ['font' => ['italic' => true]],
            7 => ['font' => ['bold' => true], 'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0'],
            ]],
        ];
    }

    public function title(): string
    {
        return 'Boletim';
    }

    protected function traduzirSituacao(string $situacao): string
    {
        return match($situacao) {
            'aprovado' => 'Aprovado',
            'reprovado' => 'Reprovado',
            default => 'Pendente',
        };
    }

    protected function gerarObservacao(): string
    {
        $dados = [
            'situacao' => $this->dados['situacao_final'] ?? 'pendente',
            'media' => $this->dados['media_geral'] ?? null,
            'negativas' => $this->dados['disciplinas_reprovadas'] ?? 0,
            'aluno' => $this->dados['aluno']->nome ?? ($this->dados['aluno']['nome'] ?? 'Aluno'),
            'trimestre' => null,
        ];

        try {
            return app(\App\Services\Pedagogico\BoletimObservacaoIaService::class)
                ->gerar($dados, auth()->user(), fn () => $this->gerarObservacaoRegra());
        } catch (\Throwable) {
            return $this->gerarObservacaoRegra();
        }
    }

    protected function gerarObservacaoRegra(): string
    {
        $situacao = $this->dados['situacao_final'];
        $media = $this->dados['media_geral'];
        $negativas = $this->dados['disciplinas_reprovadas'];

        if ($situacao === 'aprovado') {
            $obs = "O(A) aluno(a) foi APROVADO(A) com média geral de " . number_format($media, 0) . " valores.";
            if ($negativas > 0) {
                $obs .= " Apresenta {$negativas} disciplina(s) com nota negativa.";
            } else {
                $obs .= " Não apresenta disciplinas com nota negativa.";
            }
            return $obs;
        } elseif ($situacao === 'reprovado') {
            $maxReprov = (int) \App\Models\Configuracao::get('max_disciplinas_reprovacao', 2);
            return "O(A) aluno(a) foi REPROVADO(A) com média geral de " . number_format($media, 0) . " valores. Apresenta {$negativas} disciplina(s) com nota negativa, excedendo o limite permitido de {$maxReprov} negativas.";
        }

        return "Situação pendente de avaliação final.";
    }

    protected function gerarObservacaoTrimestre(): string
    {
        $trimestre = $this->dados['trimestre'];
        $resumo = $this->dados['resumo'] ?? [];
        $negativas = $resumo['reprovadas'] ?? 0;

        $dados = [
            'situacao' => ($negativas ?? 0) > 0 ? 'com_negativas' : 'positivo',
            'media' => $resumo['media'] ?? null,
            'negativas' => $negativas,
            'trimestre' => $trimestre,
            'aluno' => $this->dados['aluno']->nome ?? ($this->dados['aluno']['nome'] ?? 'Aluno'),
        ];

        try {
            return app(\App\Services\Pedagogico\BoletimObservacaoIaService::class)
                ->gerar($dados, auth()->user(), fn () => $this->gerarObservacaoTrimestreRegra());
        } catch (\Throwable) {
            return $this->gerarObservacaoTrimestreRegra();
        }
    }

    protected function gerarObservacaoTrimestreRegra(): string
    {
        $trimestre = $this->dados['trimestre'];
        $resumo = $this->dados['resumo'] ?? [];
        $negativas = $resumo['reprovadas'] ?? 0;

        $obs = "Este boletim refere-se exclusivamente ao {$trimestre}.º Trimestre. ";

        if ($negativas == 0) {
            $obs .= "O(A) aluno(a) não apresenta disciplinas com nota negativa neste trimestre.";
        } else {
            $obs .= "O(A) aluno(a) apresenta {$negativas} disciplina(s) com nota negativa neste trimestre.";
        }

        $obs .= " A situação final será determinada após a conclusão de todos os trimestres.";

        return $obs;
    }
}
