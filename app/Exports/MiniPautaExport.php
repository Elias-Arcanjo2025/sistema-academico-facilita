<?php

namespace App\Exports;

use App\Support\PautaCores;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Mini-Pauta trimestral em Excel (formato oficial da escola):
 *  - Logo centralizado no topo
 *  - Nome instituição + ÁREA + CURSO centralizados
 *  - "O DIRECTOR" à esquerda + "O (a) Director (a)" à direita
 *  - Título verde "MINI-PAUTA - Nº TRIMESTRE"
 *  - Tabela: Nº | Nome | componentes | médias | Situação
 *  - Médias com FÓRMULA ROUND (arredondamento por defeito/excesso):
 *      MT  = ROUND((MAC+PP[+PT])/N,0)
 *      MFT2 = ROUND((MT1+MT2)/2,0)
 *      CA  = ROUND(0.6*CF+0.4*PG,0)
 *  - Situação é FÓRMULA: =IF(MT<10,"Negativa","Positiva")
 *  - Cores condicionais: notas <10 vermelho, >=10 azul
 *  - Estatísticas no rodapé com fórmulas
 *  - Data do conselho preenchida automaticamente
 *  - Assinatura do professor da disciplina
 */
class MiniPautaExport implements WithEvents, WithDrawings, WithTitle
{
    private const COR_NAVY = '002060';
    private const COR_VERDE = '00B050';
    private const COR_AZUL_CLARO = PautaCores::VERDE_PALIDO;
    private const COR_AZUL_MEDIO = '4F81BD';
    private const COR_PRETO = '000000';
    private const COR_VERMELHO = 'C00000';
    private const COR_AZUL_NOTA = '0070C0';
    private const COR_CINZA_CLARO = 'F2F2F2';

    /** @var array<int, array{key: string, header: string}> */
    protected array $colunasNotas;

    protected string $chaveResultado;

    protected string $ultimaCol;

    protected string $colResultado;

    /** @var int Total de colunas: N.º + Nome + notas + Situação */
    protected int $totalCols = 7;

    /** @var int Linha onde começa o corpo de alunos */
    protected int $linhaDados = 14;

    /** @var int Última linha de dados */
    protected int $ultimaLinha;

    public function __construct(protected array $dados)
    {
        $this->colunasNotas = $this->dados['colunas'] ?? [
            ['key' => 'mac', 'header' => 'MAC'],
            ['key' => 'pp', 'header' => 'PP'],
            ['key' => 'pt', 'header' => 'PT'],
            ['key' => 'mt', 'header' => 'MT'],
        ];
        $this->chaveResultado = $this->dados['chave_resultado'] ?? 'mt';
        $this->totalCols = 2 + count($this->colunasNotas) + 1;
        $this->ultimaCol = Coordinate::stringFromColumnIndex($this->totalCols);
        $this->colResultado = $this->colLetra(3);
        foreach ($this->colunasNotas as $i => $coluna) {
            if (($coluna['key'] ?? '') === $this->chaveResultado) {
                $this->colResultado = $this->colLetra(3 + $i);
                break;
            }
        }

        $this->ultimaLinha = $this->linhaDados + max(0, count($this->dados['alunos']) - 1);
    }

    protected function colLetra(int $index1Based): string
    {
        return Coordinate::stringFromColumnIndex($index1Based);
    }

    protected function colDireitaInicio(): string
    {
        return $this->colLetra(max(6, $this->totalCols - 1));
    }

    public function title(): string
    {
        $disc = $this->dados['disciplina']->codigo ?? mb_substr($this->dados['disciplina']->nome, 0, 10);
        return mb_substr("MP {$disc} T{$this->dados['trimestre']}", 0, 31);
    }

    public function drawings(): array
    {
        $logoPath = $this->getLogoPath();
        if (!$logoPath) {
            return [];
        }

        $colCentro = (int) ceil($this->totalCols / 2);
        $logo = new Drawing();
        $logo->setName('Logotipo');
        $logo->setDescription('Logo da instituição');
        $logo->setPath($logoPath);
        $logo->setHeight(80);
        $logo->setCoordinates(Coordinate::stringFromColumnIndex($colCentro) . '1');
        $logo->setOffsetX(-40);
        $logo->setOffsetY(3);

        return [$logo];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->buildLayout($event->sheet->getDelegate());
            },
        ];
    }

    protected function buildLayout(Worksheet $sheet): void
    {
        $this->configurarLarguraColunas($sheet);
        $this->configurarPagina($sheet);
        $this->desenharCabecalho($sheet);
        $this->desenharTituloEInfo($sheet);
        $this->desenharCabecalhoTabela($sheet);
        $this->desenharLinhasAlunos($sheet);
        $this->desenharEstatisticas($sheet);
        $this->desenharRodape($sheet);
    }

    protected function configurarLarguraColunas(Worksheet $sheet): void
    {
        $sheet->getColumnDimension('A')->setWidth(7);
        $sheet->getColumnDimension('B')->setWidth($this->totalCols > 8 ? 32 : 45);

        for ($i = 0; $i < count($this->colunasNotas); $i++) {
            $sheet->getColumnDimension($this->colLetra(3 + $i))->setWidth(7.5);
        }

        $sheet->getColumnDimension($this->ultimaCol)->setWidth(13);
    }

    protected function configurarPagina(Worksheet $sheet): void
    {
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.4);
        $sheet->getPageMargins()->setRight(0.5);
        $sheet->getPageMargins()->setBottom(0.4);
        $sheet->getPageMargins()->setLeft(0.5);
    }

    /**
     * Cabeçalho institucional:
     *  - Linhas 1-4: Logo centralizado
     *  - Linha 5: "O DIRECTOR" (A:B) | INSTITUIÇÃO (C:E) | "O (a) Director (a)" (F:G)
     *  - Linha 6: linha (A:B) | ÁREA (C:E) | linha (F:G)
     *  - Linha 7: nome dir (A:B) | CURSO (C:E) | nome dir (F:G)
     *  - Linha 8: DATA (A:B)
     */
    protected function desenharCabecalho(Worksheet $sheet): void
    {
        // ===== ESQUERDA: O DIRECTOR =====
        $sheet->mergeCells('A5:B5');
        $sheet->setCellValue('A5', 'O DIRECTOR');
        $sheet->getStyle('A5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->mergeCells('A6:B6');
        $sheet->setCellValue('A6', str_repeat('_', 30));
        $sheet->getStyle('A6')->applyFromArray([
            'font' => ['size' => 8, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->mergeCells('A7:B7');
        $sheet->setCellValue('A7', $this->dados['director_geral_nome'] ?? '');
        $sheet->getStyle('A7')->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->mergeCells('A8:B8');
        $sheet->setCellValue('A8', 'DATA: ____/____/____');
        $sheet->getStyle('A8')->applyFromArray([
            'font' => ['size' => 8, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // ===== CENTRO: Instituição / Área / Curso =====
        $sheet->mergeCells('C5:E5');
        $sheet->setCellValue('C5', $this->dados['instituicao']['nome'] ?? '');
        $sheet->getStyle('C5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        $sheet->mergeCells('C6:E6');
        $area = $this->getAreaNome();
        $sheet->setCellValue('C6', "ÁREA DE {$area}");
        $sheet->getStyle('C6')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->mergeCells('C7:E7');
        $cursoNome = $this->dados['turma']->curso->nome ?? '';
        $sheet->setCellValue('C7', 'CURSO DE ' . mb_strtoupper($cursoNome));
        $sheet->getStyle('C7')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $colDirIni = $this->colDireitaInicio();

        // ===== DIREITA: O (a) Director (a) =====
        $sheet->mergeCells("{$colDirIni}5:{$this->ultimaCol}5");
        $sheet->setCellValue("{$colDirIni}5", 'O (a) Director (a)');
        $sheet->getStyle("{$colDirIni}5")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->mergeCells("{$colDirIni}6:{$this->ultimaCol}6");
        $sheet->setCellValue("{$colDirIni}6", str_repeat('_', 30));
        $sheet->getStyle("{$colDirIni}6")->applyFromArray([
            'font' => ['size' => 8, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->mergeCells("{$colDirIni}7:{$this->ultimaCol}7");
        $sheet->setCellValue("{$colDirIni}7", $this->dados['director_geral_nome'] ?? '');
        $sheet->getStyle("{$colDirIni}7")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Alturas das linhas
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getRowDimension(3)->setRowHeight(22);
        $sheet->getRowDimension(4)->setRowHeight(20);
        $sheet->getRowDimension(5)->setRowHeight(22);
        $sheet->getRowDimension(6)->setRowHeight(15);
        $sheet->getRowDimension(7)->setRowHeight(18);
        $sheet->getRowDimension(8)->setRowHeight(16);
    }

    /**
     * Linhas 10, 11, 12: Título "MINI-PAUTA - Nº TRIMESTRE" + bloco info
     */
    protected function desenharTituloEInfo(Worksheet $sheet): void
    {
        $trimestresOrd = [1 => '1º', 2 => '2º', 3 => '3º'];
        $trimestreLabel = $trimestresOrd[$this->dados['trimestre']] ?? $this->dados['trimestre'] . 'º';

        // Linha 10: título verde
        $sheet->mergeCells("A10:{$this->ultimaCol}10");
        $sheet->setCellValue('A10', "MINI-PAUTA — {$trimestreLabel} TRIMESTRE");
        $sheet->getStyle('A10')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::COR_VERDE]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(10)->setRowHeight(22);

        // Linha 11: ANO LECTIVO | DISCIPLINA
        $sheet->mergeCells('A11:C11');
        $sheet->setCellValue('A11', 'ANO LECTIVO: ' . $this->dados['ano_lectivo']);
        $sheet->getStyle('A11')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COR_PRETO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $colMeioFim = $this->colLetra(max(5, (int) floor($this->totalCols * 0.65)));
        $sheet->mergeCells("D11:{$colMeioFim}11");
        $sheet->setCellValue('D11', 'DISCIPLINA: ' . mb_strtoupper($this->dados['disciplina']->nome ?? ''));
        $sheet->getStyle('D11')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COR_PRETO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(11)->setRowHeight(18);

        // Linha 12: TURMA | CLASSE | PROFESSOR
        $sheet->mergeCells('A12:C12');
        $sheet->setCellValue('A12', 'TURMA: ' . ($this->dados['turma']->nome ?? '')
            . ' — ' . mb_strtoupper($this->dados['turma']->classe->nome ?? ''));
        $sheet->getStyle('A12')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COR_PRETO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->mergeCells("D12:{$this->ultimaCol}12");
        $profNome = $this->dados['professor_nome'] ?? '—';
        $sheet->setCellValue('D12', 'PROFESSOR: ' . $profNome);
        $sheet->getStyle('D12')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COR_PRETO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(12)->setRowHeight(18);
        $sheet->getRowDimension(13)->setRowHeight(8);  // separador
    }

    /**
     * Cabeçalho da tabela (linha 13): Nº | Nome | MAC | PP | PT | MT | Situação
     * (Como só temos 1 trimestre, não precisa de duas linhas)
     */
    protected function desenharCabecalhoTabela(Worksheet $sheet): void
    {
        $sheet->getRowDimension(13)->setRowHeight(22);

        $sheet->setCellValue('A13', "N.º\nORDEM");
        $sheet->setCellValue('B13', 'NOME COMPLETO');

        foreach ($this->colunasNotas as $i => $coluna) {
            $col = $this->colLetra(3 + $i);
            $sheet->setCellValue("{$col}13", $coluna['header']);
        }

        $sheet->setCellValue("{$this->ultimaCol}13", 'SITUAÇÃO');

        $sheet->getStyle("A13:{$this->ultimaCol}13")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COR_CINZA_CLARO]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COR_PRETO]]],
        ]);

        $sheet->getStyle("{$this->colResultado}13")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COR_AZUL_CLARO]],
        ]);
    }

    /**
     * Preenche as linhas de alunos com:
     *  - Componentes (MAC, PP, PT, PG, …) como valores
     *  - Médias (MT, MFT2, CF, CA) como fórmulas ROUND (por defeito/excesso)
     *  - SITUAÇÃO (fórmula = IF resultado < 10 "Negativa" senão "Positiva")
     */
    protected function desenharLinhasAlunos(Worksheet $sheet): void
    {
        $row = $this->linhaDados;
        $colSit = $this->ultimaCol;
        $primeiraNota = $this->colLetra(3);
        $ultimaNota = $this->colLetra(2 + count($this->colunasNotas));
        $colMap = $this->mapaColunasNotas();
        $chavesFormula = $this->chavesComFormulaMedia();

        foreach ($this->dados['alunos'] as $aluno) {
            $isDesistente = ($aluno['situacao'] ?? null) === 'desistente';

            $sheet->setCellValue("A{$row}", $aluno['numero_ordem']);
            $sheet->setCellValue("B{$row}", $aluno['nome']);

            if ($isDesistente) {
                for ($i = 0; $i < count($this->colunasNotas); $i++) {
                    $sheet->setCellValue($this->colLetra(3 + $i) . $row, '');
                }
                $sheet->setCellValue("{$colSit}{$row}", 'Desistente');
            } else {
                foreach ($this->colunasNotas as $i => $coluna) {
                    $col = $this->colLetra(3 + $i);
                    $key = $coluna['key'];
                    $formula = in_array($key, $chavesFormula, true)
                        ? $this->formulaMedia($key, $colMap, $row)
                        : null;

                    if ($formula !== null && $formula !== '') {
                        $sheet->setCellValueExplicit("{$col}{$row}", $formula, DataType::TYPE_FORMULA);
                        continue;
                    }

                    $valor = $aluno[$key] ?? null;
                    $sheet->setCellValue(
                        "{$col}{$row}",
                        $valor !== null ? (int) \App\Support\NotaArredondamento::media($valor) : ''
                    );
                }

                $res = $this->colResultado;
                $formulaSit = "=IF({$res}{$row}=\"\",\"Pendente\",IF({$res}{$row}<10,\"Negativa\",\"Positiva\"))";
                $sheet->setCellValueExplicit("{$colSit}{$row}", $formulaSit, DataType::TYPE_FORMULA);
            }

            $row++;
        }

        $this->ultimaLinha = $row - 1;

        $rangeDados = "A{$this->linhaDados}:{$this->ultimaCol}{$this->ultimaLinha}";
        $sheet->getStyle($rangeDados)->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => self::COR_PRETO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COR_PRETO]]],
        ]);

        $sheet->getStyle("B{$this->linhaDados}:B{$this->ultimaLinha}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle("{$this->colResultado}{$this->linhaDados}:{$this->colResultado}{$this->ultimaLinha}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COR_AZUL_CLARO]],
            'font' => ['bold' => true, 'size' => 10],
        ]);

        for ($r = $this->linhaDados; $r <= $this->ultimaLinha; $r++) {
            $sheet->getRowDimension($r)->setRowHeight(17);
        }

        $this->aplicarFormatacaoCondicional($sheet, $primeiraNota, $ultimaNota);
    }

    /**
     * @return array<string, string> key => letra da coluna (ex.: mac => C)
     */
    protected function mapaColunasNotas(): array
    {
        $mapa = [];
        foreach ($this->colunasNotas as $i => $coluna) {
            $mapa[$coluna['key']] = $this->colLetra(3 + $i);
        }

        return $mapa;
    }

    /**
     * Colunas calculadas com ROUND na mini-pauta.
     *
     * @return array<int, string>
     */
    protected function chavesComFormulaMedia(): array
    {
        $trim = (int) ($this->dados['trimestre'] ?? 1);
        $keys = array_column($this->colunasNotas, 'key');
        $formulas = [];

        if (in_array('mt', $keys, true)) {
            $formulas[] = 'mt';
        }
        if ($trim === 2 && in_array('mft2', $keys, true)) {
            $formulas[] = 'mft2';
        }
        if (in_array('cf', $keys, true)) {
            $formulas[] = 'cf';
        }
        if (in_array('ca', $keys, true)) {
            $formulas[] = 'ca';
        }

        return $formulas;
    }

    /**
     * Fórmula Excel com ROUND (arredondamento por defeito/excesso).
     */
    protected function formulaMedia(string $key, array $colMap, int $row): string
    {
        $trim = (int) ($this->dados['trimestre'] ?? 1);
        $classe = (int) ($this->dados['classe_numero'] ?? 0);
        if ($classe <= 0) {
            $raw = (string) ($this->dados['turma']->classe->codigo
                ?? $this->dados['turma']->classe->nome
                ?? '10');
            $classe = (int) (preg_replace('/\D/', '', $raw) ?: 10);
        }

        $get = fn (string $k) => isset($colMap[$k]) ? $colMap[$k].$row : '';
        $this->dados['turma']->loadMissing('curso', 'classe');
        $regime = $this->dados['turma']->curso?->regime()
            ?? app(\App\Services\RegimePauta\RegimePolitecnico::class);
        $expr = $regime->expressoesExcel($get, $classe);

        if ($key === 'mt') {
            $id = match ($trim) {
                1 => 'mt_t1',
                2 => 'mt_t2',
                default => 'mt_t3',
            };

            return $expr[$id] ?? '';
        }

        if ($key === 'mft2') {
            return $expr['mft2'] ?? '';
        }

        if ($key === 'cf') {
            return $expr['cf'] ?? '';
        }

        if ($key === 'ca') {
            // Preferir fórmula com coluna CF quando existir (CA = 0,6×CF + 0,4×PG).
            if (($expr['ca'] ?? '') !== '' && isset($colMap['cf'])) {
                return $expr['ca'];
            }

            // Fallback legado: embutir CF = ROUND((MFT2+MT)/2,0) na fórmula da CA.
            if (isset($colMap['mft2'], $colMap['mt'], $colMap['pg'])) {
                $mft2 = "IF(ISNUMBER({$colMap['mft2']}{$row}),{$colMap['mft2']}{$row},0)";
                $mt = "IF(ISNUMBER({$colMap['mt']}{$row}),{$colMap['mt']}{$row},0)";
                $pg = "IF(ISNUMBER({$colMap['pg']}{$row}),{$colMap['pg']}{$row},0)";

                return "=ROUND(0.6*ROUND(({$mft2}+{$mt})/2,0)+0.4*{$pg},0)";
            }

            if (isset($colMap['cf'], $colMap['pg'])) {
                return $expr['ca'] ?? '';
            }
        }

        return '';
    }

    /**
     * Cores condicionais para notas e situação.
     */
    protected function aplicarFormatacaoCondicional(Worksheet $sheet, string $primeiraNota, string $ultimaNota): void
    {
        $condVermelho = new Conditional();
        $condVermelho->setConditionType(Conditional::CONDITION_CELLIS)
            ->setOperatorType(Conditional::OPERATOR_LESSTHAN)
            ->addCondition('10');
        $condVermelho->getStyle()->getFont()->setBold(true)->getColor()->setRGB(self::COR_VERMELHO);

        $condAzul = new Conditional();
        $condAzul->setConditionType(Conditional::CONDITION_CELLIS)
            ->setOperatorType(Conditional::OPERATOR_GREATERTHANOREQUAL)
            ->addCondition('10');
        $condAzul->getStyle()->getFont()->setBold(true)->getColor()->setRGB(self::COR_AZUL_NOTA);

        $rangeNotas = "{$primeiraNota}{$this->linhaDados}:{$ultimaNota}{$this->ultimaLinha}";
        $existentes = $sheet->getStyle($rangeNotas)->getConditionalStyles();
        $existentes[] = clone $condVermelho;
        $existentes[] = clone $condAzul;
        $sheet->getStyle($rangeNotas)->setConditionalStyles($existentes);

        $condNeg = new Conditional();
        $condNeg->setConditionType(Conditional::CONDITION_CELLIS)
            ->setOperatorType(Conditional::OPERATOR_EQUAL)
            ->addCondition('"Negativa"');
        $condNeg->getStyle()->getFont()->setBold(true)->getColor()->setRGB(self::COR_VERMELHO);

        $condPos = new Conditional();
        $condPos->setConditionType(Conditional::CONDITION_CELLIS)
            ->setOperatorType(Conditional::OPERATOR_EQUAL)
            ->addCondition('"Positiva"');
        $condPos->getStyle()->getFont()->setBold(true)->getColor()->setRGB(self::COR_VERDE);

        $rangeSit = "{$this->ultimaCol}{$this->linhaDados}:{$this->ultimaCol}{$this->ultimaLinha}";
        $existentesSit = $sheet->getStyle($rangeSit)->getConditionalStyles();
        $existentesSit[] = $condNeg;
        $existentesSit[] = $condPos;
        $sheet->getStyle($rangeSit)->setConditionalStyles($existentesSit);
    }

    /**
     * Bloco de estatísticas no rodapé, com fórmulas que se recalculam
     * sempre que o utilizador altera notas.
     */
    protected function desenharEstatisticas(Worksheet $sheet): void
    {
        $linha = $this->ultimaLinha + 2;
        $rangeRes = "{$this->colResultado}{$this->linhaDados}:{$this->colResultado}{$this->ultimaLinha}";
        $rangeSit = "{$this->ultimaCol}{$this->linhaDados}:{$this->ultimaCol}{$this->ultimaLinha}";
        $colValorFim = $this->colLetra(max(6, $this->totalCols - 1));

        $sheet->mergeCells("A{$linha}:{$this->ultimaCol}{$linha}");
        $sheet->setCellValue("A{$linha}", 'ESTATÍSTICAS');
        $sheet->getStyle("A{$linha}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => self::COR_NAVY]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COR_CINZA_CLARO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COR_PRETO]]],
        ]);
        $sheet->getRowDimension($linha)->setRowHeight(20);

        $headerResultado = collect($this->colunasNotas)->firstWhere('key', $this->chaveResultado)['header'] ?? 'MT';
        $stats = [
            ['Total de Alunos', '=COUNTA(B' . $this->linhaDados . ':B' . $this->ultimaLinha . ')'],
            ["Com Nota ({$headerResultado})", "=COUNT({$rangeRes})"],
            ['Aprovados (Positivos)', "=COUNTIF({$rangeSit},\"Positiva\")"],
            ['Reprovados (Negativos)', "=COUNTIF({$rangeSit},\"Negativa\")"],
            ['Média da Turma', "=IF(COUNT({$rangeRes})=0,\"—\",ROUND(AVERAGE({$rangeRes}),2))"],
        ];

        foreach ($stats as $i => [$label, $formula]) {
            $r = $linha + 1 + $i;
            $sheet->mergeCells("A{$r}:{$colValorFim}{$r}");
            $sheet->setCellValue("A{$r}", $label);
            $sheet->mergeCells("{$this->colResultado}{$r}:{$this->ultimaCol}{$r}");
            $sheet->setCellValueExplicit("{$this->colResultado}{$r}", $formula, DataType::TYPE_FORMULA);

            $sheet->getStyle("A{$r}:{$this->ultimaCol}{$r}")->applyFromArray([
                'font' => ['size' => 10, 'color' => ['rgb' => self::COR_PRETO]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COR_PRETO]]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);
            $sheet->getStyle("{$this->colResultado}{$r}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COR_NAVY]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getRowDimension($r)->setRowHeight(17);
        }

        // Guarda a última linha das estatísticas para o rodapé
        $this->ultimaLinha = $linha + count($stats);
    }

    /**
     * Rodapé com data do conselho + assinaturas.
     */
    protected function desenharRodape(Worksheet $sheet): void
    {
        $linha = $this->ultimaLinha + 2;

        // ===== Data do conselho (preenchida do sistema) =====
        $sheet->mergeCells("A{$linha}:C{$linha}");
        $sheet->setCellValue("A{$linha}", 'DATA DO CONSELHO DE TURMA');
        $sheet->getStyle("A{$linha}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COR_NAVY]]],
        ]);

        $sheet->mergeCells("D{$linha}:{$this->ultimaCol}{$linha}");
        $dataConselho = !empty($this->dados['data_conselho'])
            ? $this->dados['data_conselho']
            : '_____/_____/_____';
        // Forçar como string para evitar que "19/12/2025" seja interpretado como divisão
        $sheet->setCellValueExplicit("D{$linha}", $dataConselho, DataType::TYPE_STRING);
        $sheet->getStyle("D{$linha}")->applyFromArray([
            'font' => ['bold' => !empty($this->dados['data_conselho']), 'size' => 10, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COR_NAVY]]],
        ]);
        $sheet->getRowDimension($linha)->setRowHeight(20);

        // ===== Assinaturas =====
        $linhaTit = $linha + 3;
        $linhaLin = $linhaTit + 2;
        $linhaNom = $linhaTit + 3;

        $colMeio = $this->colLetra((int) ceil($this->totalCols / 2));
        $blocos = [
            ['ini' => 'A', 'fim' => $this->colLetra(max(3, (int) floor($this->totalCols / 3))), 'titulo' => 'O PROFESSOR DA DISCIPLINA', 'nome' => $this->dados['professor_nome'] ?? ''],
            ['ini' => $this->colLetra(max(4, (int) floor($this->totalCols / 3) + 1)), 'fim' => $colMeio, 'titulo' => 'DIRECTOR DE TURMA', 'nome' => $this->dados['director_turma_nome'] ?? ''],
            ['ini' => $this->colLetra($colMeio === 'A' ? 1 : Coordinate::columnIndexFromString($colMeio) + 1), 'fim' => $this->ultimaCol, 'titulo' => 'COORD. DO CURSO', 'nome' => $this->dados['coordenador_curso_nome'] ?? ''],
        ];

        foreach ($blocos as $b) {
            $sheet->mergeCells("{$b['ini']}{$linhaTit}:{$b['fim']}{$linhaTit}");
            $sheet->setCellValue("{$b['ini']}{$linhaTit}", $b['titulo']);
            $sheet->getStyle("{$b['ini']}{$linhaTit}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => self::COR_AZUL_MEDIO]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $sheet->mergeCells("{$b['ini']}{$linhaLin}:{$b['fim']}{$linhaLin}");
            $sheet->setCellValue("{$b['ini']}{$linhaLin}", str_repeat('_', 30));
            $sheet->getStyle("{$b['ini']}{$linhaLin}")->applyFromArray([
                'font' => ['size' => 9, 'color' => ['rgb' => self::COR_AZUL_MEDIO]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            $sheet->mergeCells("{$b['ini']}{$linhaNom}:{$b['fim']}{$linhaNom}");
            $sheet->setCellValue("{$b['ini']}{$linhaNom}", $b['nome']);
            $sheet->getStyle("{$b['ini']}{$linhaNom}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => self::COR_NAVY]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }
        $sheet->getRowDimension($linhaTit)->setRowHeight(18);
        $sheet->getRowDimension($linhaLin)->setRowHeight(15);
        $sheet->getRowDimension($linhaNom)->setRowHeight(18);
    }

    /**
     * Caminho local do logo da instituição.
     */
    protected function getLogoPath(): ?string
    {
        $rel = $this->dados['instituicao']['logo'] ?? null;
        if (!$rel) {
            return null;
        }
        $path = storage_path('app/public/' . $rel);
        return file_exists($path) ? $path : null;
    }

    /**
     * Deriva ÁREA do nome do curso. Ex: "Técnico de Informática" → "INFORMÁTICA"
     */
    protected function getAreaNome(): string
    {
        $cursoNome = $this->dados['turma']->curso->nome ?? '';
        if (preg_match('/\b(?:de|do|da|dos|das)\s+(.+)$/iu', $cursoNome, $m)) {
            return mb_strtoupper(trim($m[1]));
        }
        return mb_strtoupper($cursoNome);
    }
}
