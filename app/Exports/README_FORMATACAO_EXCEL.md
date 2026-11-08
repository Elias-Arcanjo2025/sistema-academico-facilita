# Formatação Profissional para Excels - Facilita

Guia de padronização para exportações Excel com formatação moderna e profissional.

## 🎨 Padrão de Formatação

### Cores Padrão

```php
// Definir no início da classe
protected const COLORS = [
    'header_bg' => 'D0D0D0',        // Cinza claro (cabeçalho tabela)
    'header_text' => '000000',       // Preto
    'info_bg' => 'FAFAFA',           // Cinza muito claro (info box)
    'info_label_bg' => 'E9ECEF',     // Cinza claro (labels)
    'positive' => '006600',          // Verde escuro (notas positivas)
    'negative' => 'D00000',          // Vermelho (notas negativas)
    'border' => '333333',            // Cinza escuro (bordas)
    'zebra_even' => 'FCFCFC',        // Cinza muito claro (linhas pares)
];
```

### Estrutura Recomendada

```
Linha 1: [LOGO] REPÚBLICA DE ANGOLA
Linha 2: Ministério da Educação
Linha 3: NOME DA INSTITUIÇÃO
Linha 4: Endereço | Tel: XXX
Linha 5: (em branco)
Linha 6: ═══════════════════════════
Linha 7: (em branco)
Linha 8: TÍTULO DO DOCUMENTO
Linha 9: Subtítulo (trimestre, ano)
Linha 10: (em branco)
Linha 11: ┌─────────────────────┐
Linha 12: │ Info Box: Turma, etc│
Linha 13: └─────────────────────┘
Linha 14: (em branco)
Linha 15+: TABELA DE DADOS
...
Final: Estatísticas
Final+1: Assinaturas
Final+2: Informações de Contato
Final+3: Data de Geração
```

## 📝 Exemplo Completo

```php
<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class MiniPautaExportMelhorado implements
    FromArray,
    WithHeadings,
    WithStyles,
    ShouldAutoSize,
    WithTitle,
    WithEvents
{
    protected const COLORS = [
        'header_bg' => 'D0D0D0',
        'info_bg' => 'FAFAFA',
        'positive' => '006600',
        'negative' => 'D00000',
        'border' => '333333',
    ];

    protected array $dados;
    protected int $headerStartRow = 11; // Linha onde começa o cabeçalho da tabela

    public function __construct(array $dados)
    {
        $this->dados = $dados;
    }

    public function array(): array
    {
        $rows = [];

        // Dados dos alunos
        foreach ($this->dados['alunos'] as $aluno) {
            $rows[] = [
                $aluno['numero_ordem'],
                $aluno['nome'],
                $aluno['mac'] !== null ? number_format($aluno['mac'], 0) : '-',
                $aluno['pp'] !== null ? number_format($aluno['pp'], 0) : '-',
                $aluno['pt'] !== null ? number_format($aluno['pt'], 0) : '-',
                $aluno['mt'] !== null ? number_format($aluno['mt'], 0) : '-',
                $aluno['situacao'] === 'aprovado' ? 'Positiva' :
                    ($aluno['situacao'] === 'reprovado' ? 'Negativa' : ''),
            ];
        }

        // Estatísticas
        $rows[] = [];
        $rows[] = ['RESUMO ESTATÍSTICO DA TURMA'];
        $rows[] = [];
        $rows[] = ['Total de Alunos:', $this->dados['estatisticas']['total_alunos']];
        $rows[] = ['Positivas:', $this->dados['estatisticas']['aprovados']];
        $rows[] = ['Negativas:', $this->dados['estatisticas']['reprovados']];
        $rows[] = ['Média da Turma:', $this->dados['estatisticas']['media_turma'] ?? '-'];

        return $rows;
    }

    public function headings(): array
    {
        return [
            'N.º',
            'Nome Completo do Aluno',
            'MAC',
            'PP',
            'PT',
            'MT',
            'Obs.',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // CABEÇALHO INSTITUCIONAL
                $this->adicionarCabecalhoInstitucional($sheet);

                // TÍTULO DO DOCUMENTO
                $this->adicionarTitulo($sheet);

                // INFO BOX
                $this->adicionarInfoBox($sheet);

                // FORMATAR TABELA
                $this->formatarTabela($sheet);

                // FORMATAR ESTATÍSTICAS
                $this->formatarEstatisticas($sheet);

                // RODAPÉ
                $this->adicionarRodape($sheet);
            },
        ];
    }

    protected function adicionarCabecalhoInstitucional(Worksheet $sheet): void
    {
        // Inserir linhas no topo
        $sheet->insertNewRowBefore(1, $this->headerStartRow - 1);

        // República de Angola
        $sheet->setCellValue('A1', 'REPÚBLICA DE ANGOLA');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Ministério
        $sheet->setCellValue('A2', 'Ministério da Educação');
        $sheet->getStyle('A2')->getFont()->setSize(10);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Nome da Instituição
        $instituicao = $this->dados['instituicao']['nome'] ?? 'Instituto Politécnico';
        $sheet->setCellValue('A3', strtoupper($instituicao));
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Contato
        if (!empty($this->dados['instituicao']['endereco']) || !empty($this->dados['instituicao']['telefone'])) {
            $contato = '';
            if (!empty($this->dados['instituicao']['endereco'])) {
                $contato .= $this->dados['instituicao']['endereco'];
            }
            if (!empty($this->dados['instituicao']['telefone'])) {
                $contato .= ($contato ? ' | ' : '') . 'Tel: ' . $this->dados['instituicao']['telefone'];
            }
            $sheet->setCellValue('A4', $contato);
            $sheet->getStyle('A4')->getFont()->setSize(9);
            $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Mesclar células para centralização
        $lastColumn = 'G';
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->mergeCells("A3:{$lastColumn}3");
        if ($sheet->getCell('A4')->getValue()) {
            $sheet->mergeCells("A4:{$lastColumn}4");
        }
    }

    protected function adicionarTitulo(Worksheet $sheet): void
    {
        $tituloRow = 7;

        // Título principal
        $sheet->setCellValue("A{$tituloRow}", 'MINI-PAUTA TRIMESTRAL');
        $sheet->getStyle("A{$tituloRow}")->getFont()->setBold(true)->setSize(15);
        $sheet->getStyle("A{$tituloRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$tituloRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF5F5F5');

        // Subtítulo
        $subtitulo = "{$this->dados['trimestre']}.º Trimestre - Ano Lectivo {$this->dados['ano_lectivo']}";
        $sheet->setCellValue("A" . ($tituloRow + 1), $subtitulo);
        $sheet->getStyle("A" . ($tituloRow + 1))->getFont()->setSize(11);
        $sheet->getStyle("A" . ($tituloRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A" . ($tituloRow + 1))->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF5F5F5');

        // Mesclar células
        $sheet->mergeCells("A{$tituloRow}:G{$tituloRow}");
        $sheet->mergeCells("A" . ($tituloRow + 1) . ":G" . ($tituloRow + 1));
    }

    protected function adicionarInfoBox(Worksheet $sheet): void
    {
        $infoRow = 10;

        $turma = $this->dados['turma']->nome;
        $disciplina = $this->dados['disciplina']->nome;
        $curso = $this->dados['turma']->curso->nome ?? '-';
        $classe = $this->dados['turma']->classe->nome ?? '-';

        $sheet->setCellValue("A{$infoRow}", "Turma: {$turma}");
        $sheet->setCellValue("D{$infoRow}", "Curso: {$curso}");
        $sheet->setCellValue("A" . ($infoRow + 1), "Disciplina: {$disciplina}");
        $sheet->setCellValue("D" . ($infoRow + 1), "Classe: {$classe}");

        // Estilo
        foreach ([$infoRow, $infoRow + 1] as $row) {
            $sheet->getStyle("A{$row}:G{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFFAFAFA');
            $sheet->getStyle("A{$row}:G{$row}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()->setARGB('FF' . self::COLORS['border']);
        }

        // Negrito nos labels
        foreach (['A', 'D'] as $col) {
            $sheet->getStyle("{$col}{$infoRow}")->getFont()->setBold(true);
            $sheet->getStyle("{$col}" . ($infoRow + 1))->getFont()->setBold(true);
        }
    }

    protected function formatarTabela(Worksheet $sheet): void
    {
        $headerRow = $this->headerStartRow + 1;
        $dataStartRow = $headerRow + 1;
        $dataEndRow = $dataStartRow + count($this->dados['alunos']) - 1;

        // Cabeçalho da tabela
        $sheet->getStyle("A{$headerRow}:G{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF' . self::COLORS['header_bg']],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['argb' => 'FF' . self::COLORS['border']],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Dados da tabela
        $sheet->getStyle("A{$dataStartRow}:G{$dataEndRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF' . self::COLORS['border']],
                ],
            ],
        ]);

        // Zebra striping (linhas pares)
        for ($row = $dataStartRow; $row <= $dataEndRow; $row++) {
            if (($row - $dataStartRow) % 2 == 0) {
                $sheet->getStyle("A{$row}:G{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF' . self::COLORS['zebra_even']);
            }
        }

        // Formatação condicional para notas
        foreach (range($dataStartRow, $dataEndRow) as $row) {
            foreach (['C', 'D', 'E', 'F'] as $col) {
                $valor = $sheet->getCell("{$col}{$row}")->getValue();
                if (is_numeric($valor)) {
                    if ($valor >= 10) {
                        $sheet->getStyle("{$col}{$row}")->getFont()
                            ->setBold(true)
                            ->getColor()->setARGB('FF' . self::COLORS['positive']);
                    } else {
                        $sheet->getStyle("{$col}{$row}")->getFont()
                            ->setBold(true)
                            ->getColor()->setARGB('FF' . self::COLORS['negative']);
                    }
                }
            }

            // Coluna MT (F) com destaque adicional
            $sheet->getStyle("F{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFF0F0F0');
            $sheet->getStyle("F{$row}")->getFont()->setSize(12);
        }

        // Coluna N.º (A) com fundo cinza
        $sheet->getStyle("A{$dataStartRow}:A{$dataEndRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF8F8F8');
        $sheet->getStyle("A{$dataStartRow}:A{$dataEndRow}")->getFont()->setBold(true);

        // Alinhar colunas
        $sheet->getStyle("A{$dataStartRow}:A{$dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B{$dataStartRow}:B{$dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("C{$dataStartRow}:G{$dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    protected function formatarEstatisticas(Worksheet $sheet): void
    {
        $estatRow = $this->headerStartRow + 2 + count($this->dados['alunos']) + 2;

        // Título
        $sheet->getStyle("A{$estatRow}")->getFont()->setBold(true)->setSize(11);
        $sheet->mergeCells("A{$estatRow}:G{$estatRow}");

        // Dados
        for ($i = 0; $i < 4; $i++) {
            $row = $estatRow + 2 + $i;
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        }
    }

    protected function adicionarRodape(Worksheet $sheet): void
    {
        $lastRow = $sheet->getHighestRow() + 3;

        // Informações de contato
        if (!empty($this->dados['instituicao']['email']) || !empty($this->dados['instituicao']['website'])) {
            $contato = [];
            if (!empty($this->dados['instituicao']['telefone'])) {
                $contato[] = 'Tel: ' . $this->dados['instituicao']['telefone'];
            }
            if (!empty($this->dados['instituicao']['email'])) {
                $contato[] = 'Email: ' . $this->dados['instituicao']['email'];
            }
            if (!empty($this->dados['instituicao']['website'])) {
                $contato[] = 'Website: ' . $this->dados['instituicao']['website'];
            }

            $sheet->setCellValue("A{$lastRow}", implode(' | ', $contato));
            $sheet->getStyle("A{$lastRow}")->getFont()->setSize(8)->setItalic(true);
            $sheet->getStyle("A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->mergeCells("A{$lastRow}:G{$lastRow}");
            $lastRow++;
        }

        // Data de geração
        $geradoEm = $this->dados['gerado_em'] ?? now();
        $sheet->setCellValue("A{$lastRow}", "Documento gerado em " . $geradoEm->format('d/m/Y \à\s H:i'));
        $sheet->getStyle("A{$lastRow}")->getFont()->setSize(8)->setItalic(true);
        $sheet->getStyle("A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells("A{$lastRow}:G{$lastRow}");
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Largura das colunas
            'A' => ['width' => 8],
            'B' => ['width' => 40],
            'C' => ['width' => 10],
            'D' => ['width' => 10],
            'E' => ['width' => 10],
            'F' => ['width' => 10],
            'G' => ['width' => 12],
        ];
    }

    public function title(): string
    {
        return 'Mini-Pauta';
    }

    protected function traduzirSituacao(?string $situacao): string
    {
        return match($situacao) {
            'aprovado' => 'Positiva',
            'reprovado' => 'Negativa',
            default => '',
        };
    }
}
```

## 🔧 Aplicar nos Outros Exports

Para aplicar este padrão nos outros exports:

1. Copie as constantes de cores
2. Copie os métodos de formatação
3. Ajuste `$headerStartRow` conforme necessário
4. Implemente `WithEvents` e `registerEvents()`
5. Chame os métodos de formatação no `AfterSheet`

## ✅ Checklist de Formatação

- [ ] Cabeçalho institucional (República, Ministério, Nome)
- [ ] Título do documento (fundo cinza, centralizado)
- [ ] Info box (informações da turma, etc)
- [ ] Cabeçalho da tabela (fundo cinza, bordas grossas)
- [ ] Zebra striping (linhas pares cinza claro)
- [ ] Notas coloridas (verde positivo, vermelho negativo)
- [ ] Coluna MT destacada (fundo diferente, fonte maior)
- [ ] Estatísticas formatadas
- [ ] Rodapé com contatos e data

## 📦 Exports a Atualizar

- [ ] MiniPautaExport.php
- [ ] ListaAlunosExport.php
- [ ] PautaFinalDisciplinaExport.php
- [ ] PautaFinalTurmaExport.php
- [ ] PautaFinalCompletaExport.php
- [ ] BoletimExport.php
