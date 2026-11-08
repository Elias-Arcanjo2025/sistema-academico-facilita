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
use PhpOffice\PhpSpreadsheet\Style\Borders;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exportação da Pauta de Aproveitamento Trimestral em Excel,
 * com layout oficial idêntico ao modelo da escola:
 *  - Logo à esquerda e à direita do nome da instituição (centralizado)
 *  - "ÁREA DE [X]" e "CURSO DE [Y]"
 *  - Assinatura "O DIRECTOR" à esquerda + "O (a) Director (a)" à direita
 *  - Título verde "PAUTA DE APROVEITAMENTO - Nº TRIMESTRE"
 *  - Tabela por disciplina (FALTAS J|I, MAC, PP, PT, MT — MT realçado)
 *  - Linha de professores, conselho de turma e assinaturas finais
 */
class PautaTrimestralCompletaExport implements WithEvents, WithDrawings, WithTitle
{
    // Paleta de cores oficial (igual ao modelo Excel da escola)
    private const COR_NAVY = PautaCores::NAVY;
    private const COR_VERDE = '00B050';
    private const COR_AZUL_MEDIO = '4F81BD';
    private const COR_PRETO = '000000';
    private const COR_VERMELHO = PautaCores::VERMELHO_NOTA;
    private const COR_AZUL_NOTA = PautaCores::AZUL_NOTA;

    /** @var int Total de colunas calculado dinamicamente */
    protected int $totalCols;

    /** @var int Linha onde começa o corpo de alunos */
    protected int $linhaDados = 15;

    /** @var int Última linha de dados (preenchida em buildLayout) */
    protected int $ultimaLinha;

    /** @var array<int, array> Esquema de colunas por disciplina_id (pode variar — ex.: RECURSO só numa disciplina) */
    protected array $colunasPorDisciplina;

    /** @var array Lista de disciplinas terminais anteriores (carry-over CFD). Vazio para T1/T2 e para 10ª no T3. */
    protected array $cfdTerminais;

    /** @var int Nº de colunas extras de CFD terminal (=count($cfdTerminais)) */
    protected int $colsCfdTerm;

    /** @var bool Se há coluna MÉDIA (regime administração no T3) */
    protected bool $temColMedia;

    /** @var \App\Services\RegimePauta\RegimePautaInterface Regime do curso */
    protected \App\Services\RegimePauta\RegimePautaInterface $regime;

    /** @var \App\Support\PautaExcelFormulasTransicao|null Fórmulas T3 10.ª/11.ª */
    protected ?\App\Support\PautaExcelFormulasTransicao $formulasTransicao = null;

    /** Primeira coluna 1-based para auxiliares de transição (ocultas). */
    protected int $helperColStart = 0;

    public function __construct(protected array $dados)
    {
        // Resolver regime do curso da turma (politécnico por defeito)
        $curso = $this->dados['turma']->curso ?? null;
        $this->regime = $curso?->regime() ?? app(\App\Services\RegimePauta\RegimePolitecnico::class);

        $this->colunasPorDisciplina = $this->construirColunasPorDisciplina();
        $this->cfdTerminais = $this->dados['disciplinas_terminais_anteriores'] ?? [];
        $this->colsCfdTerm = count($this->cfdTerminais);
        $this->temColMedia = (bool) ($this->dados['tem_coluna_media_aluno'] ?? false);

        $totalColsDisc = 0;
        foreach ($this->dados['disciplinas'] as $disc) {
            $totalColsDisc += count($this->colunasParaDisciplina((int) $disc->id));
        }

        // Total de colunas:
        //  4 fixos (N.OR, N.PR, NOME, SEXO)
        //  + N cfd terminais
        //  + soma das colunas de cada disciplina (podem diferir)
        //  + (1 MÉDIA se aplicável)
        //  + 2 (OBS, RESULT)
        $this->totalCols = 4 + $this->colsCfdTerm
            + $totalColsDisc
            + ($this->temColMedia ? 1 : 0)
            + 2;
        $this->ultimaLinha = $this->linhaDados + count($this->dados['alunos']) - 1;
        $this->helperColStart = $this->totalCols + 1;

        if ($this->usaFormulasTransicao()) {
            $classe = (int) ($this->dados['classe_numero'] ?? 10);
            $this->formulasTransicao = new \App\Support\PautaExcelFormulasTransicao(
                $this->mapaCelulasTransicao(),
                $this->dados['meta_disciplinas_transicao'] ?? [],
                $classe >= 11 ? $this->celulasCfdTerminaisExcel() : [],
                $classe,
                $classe === 12
                    ? \App\Support\PautaResultadoTransicao::maxNegativasRecurso12()
                    : null,
                in_array($classe, [10, 11], true)
                    ? \App\Support\PautaResultadoTransicao::maxNegativasRecuperacao()
                    : null,
            );
        }
    }

    protected function usaFormulasTransicao(): bool
    {
        return (int) ($this->dados['trimestre'] ?? 1) === 3
            && in_array((int) ($this->dados['classe_numero'] ?? 10), [10, 11, 12], true)
            && ! empty($this->dados['meta_disciplinas_transicao']);
    }

    /**
     * @return array<int, array{pg:?string, ca:?string}>
     */
    protected function mapaCelulasTransicao(): array
    {
        $mapa = [];
        $col = $this->colInicialDisciplinas();

        foreach ($this->dados['disciplinas'] as $disc) {
            $colsDisc = $this->colunasParaDisciplina((int) $disc->id);
            $localCol = $col;
            $keys = [];
            foreach ($colsDisc as $def) {
                $keys[$def['key']] = Coordinate::stringFromColumnIndex($localCol);
                $localCol++;
            }
            $mapa[(int) $disc->id] = [
                'pg' => $keys['pg'] ?? null,
                'ca' => $keys['ca'] ?? null,
                'cfd' => $keys['cfd'] ?? null,
                'recuperacao' => $keys['recuperacao'] ?? null,
                'recurso' => $keys['recurso'] ?? null,
            ];
            $col += count($colsDisc);
        }

        return $mapa;
    }

    /**
     * @return array<int, array{col:string, codigo:string}>
     */
    protected function celulasCfdTerminaisExcel(): array
    {
        $lista = [];
        $cfdCol = 5;
        foreach ($this->cfdTerminais as $dt) {
            $lista[] = [
                'col' => Coordinate::stringFromColumnIndex($cfdCol),
                'codigo' => $dt['codigo'] ?? mb_strtoupper(mb_substr($dt['nome'] ?? '', 0, 5)),
            ];
            $cfdCol++;
        }

        return $lista;
    }

    /** Colunas enriquecidas (width + fórmula) para uma disciplina. */
    protected function colunasParaDisciplina(int $disciplinaId): array
    {
        return $this->colunasPorDisciplina[$disciplinaId] ?? [];
    }

    /** Coluna 1-based onde começam as disciplinas (depois de N.OR|N.PR|NOME|SEXO + colsCfdTerm). */
    protected function colInicialDisciplinas(): int
    {
        return 5 + $this->colsCfdTerm;
    }

    /**
     * Constrói o mapa disciplina_id → colunas (com width e fórmulas Excel).
     */
    protected function construirColunasPorDisciplina(): array
    {
        $trim = (int) ($this->dados['trimestre'] ?? 1);
        $classe = (int) ($this->dados['classe_numero'] ?? 10);
        $porDisc = $this->dados['colunas_por_disciplina'] ?? null;
        $disciplinasComRecurso = $this->dados['disciplinas_com_recurso_ids'] ?? [];
        $disciplinasComRecuperacao = $this->dados['disciplinas_com_recuperacao_ids'] ?? [];
        $mapa = [];

        foreach ($this->dados['disciplinas'] as $disc) {
            $pivot = null;
            $colsBase = $porDisc[$disc->id] ?? null;
            if ($colsBase === null) {
                $colsBase = $this->regime->colunasDisciplina($trim, $classe, \App\Services\PautaService::flagsColunasDisciplina(
                    $disc,
                    null,
                    $classe,
                    (int) ($this->dados['turma']->curso->classe_max ?? 12),
                    in_array($disc->id, $this->dados['disciplinas_terminais_classe_atual_ids'] ?? [], true),
                    in_array($disc->id, $disciplinasComRecurso, true),
                    (bool) ($this->dados['tem_recurso'] ?? false),
                    in_array($disc->id, $disciplinasComRecuperacao, true),
                    (bool) ($this->dados['tem_recuperacao'] ?? false),
                ));
            }
            $mapa[$disc->id] = $this->enriquecerColunas($colsBase, $trim, $classe);
        }

        return $mapa;
    }

    /**
     * Adiciona width e ID de fórmula Excel a cada coluna do esquema.
     */
    protected function enriquecerColunas(array $cols, int $trim, int $classe): array
    {
        $widths = match ($trim) {
            1 => ['faltas' => 4.5, 'faltas_j' => 3.2, 'faltas_i' => 3.2, 'mac' => 4, 'pp' => 4, 'pt' => 4, 'mt' => 4.2],
            2 => ['mt1' => 3, 'mac' => 3, 'pp' => 3, 'pt' => 3, 'mt' => 3, 'mft2' => 3.5],
            3 => ['ca10' => 3.5, 'ca11' => 3.5, 'mft2' => 3, 'mac' => 3, 'pp' => 3, 'mt' => 3, 'cf' => 4, 'pg' => 4, 'ca' => 4, 'cfd' => 3.5, 'recuperacao' => 4, 'recurso' => 4.5],
            default => [],
        };
        $formulasKey = match (true) {
            $trim === 1 => ['mt' => 'mt_t1'],
            $trim === 2 => ['mt' => 'mt_t2', 'mft2' => 'mft2'],
            $trim === 3 && $classe <= 10 => ['mt' => 'mt_t3', 'cf' => 'cf', 'ca' => 'ca', 'cfd' => 'cfd_10'],
            $trim === 3 && $classe === 11 => ['mt' => 'mt_t3', 'cf' => 'cf', 'ca' => 'ca', 'cfd' => 'cfd_11'],
            $trim === 3 && $classe === 12 => ['mt' => 'mt_t3', 'cf' => 'cf', 'ca' => 'ca', 'cfd' => 'cfd_12'],
            default => [],
        };

        $colKeys = collect($cols)->pluck('key')->all();
        $temColCa = in_array('ca', $colKeys, true);
        $temColCa10 = in_array('ca10', $colKeys, true);
        $temColCa11 = in_array('ca11', $colKeys, true);

        foreach ($cols as &$def) {
            $def['width'] = $widths[$def['key']] ?? 4;
            if ($def['tipo'] !== 'formula') {
                continue;
            }

            if ($trim === 3 && ($def['key'] ?? '') === 'cfd') {
                // Escolha pelo intervalo real da disciplina (colunas CA históricas presentes):
                // 10→12: ca10+ca11 | 11→12: só ca11 | 10→11: só ca10 | solo: só ca
                $def['formula'] = match (true) {
                    ! $temColCa => 'cfd_solo',
                    $classe === 12 && $temColCa10 && $temColCa11 => 'cfd_12',       // (CA10+CA11+CA12)/3
                    $classe === 12 && $temColCa11 => 'cfd_12_de_11',                 // (CA11+CA12)/2
                    $classe >= 11 && $temColCa10 => 'cfd_11',                        // (CA10+CA11)/2
                    default => 'cfd_10',                                             // CFD = CA
                };
                continue;
            }

            if (isset($formulasKey[$def['key']])) {
                $def['formula'] = $formulasKey[$def['key']];
            }
        }

        return $cols;
    }

    /**
     * Escreve RESULTADO + OBSERVAÇÃO com colunas auxiliares ocultas (uma por disciplina).
     * Garante fórmulas curtas (<8192) e consistentes: nunca TRANSITA com Def./Rec. na OBS.
     *
     * @param  array<string, mixed>  $aluno
     */
    protected function escreverResultadoEObservacaoComHelpers(
        Worksheet $sheet,
        int $row,
        string $colObs,
        string $colRes,
        array $aluno,
    ): void {
        $ft = $this->formulasTransicao;
        if ($ft === null) {
            return;
        }

        $negs = $ft->negatividades($row);
        $colIdx = $this->helperColStart;
        $celNegCont = [];
        $celNegTerm = [];
        $celCodCont = [];
        $celCodTerm = [];

        foreach ($negs['continuas'] as $item) {
            $colNeg = Coordinate::stringFromColumnIndex($colIdx++);
            $colCod = Coordinate::stringFromColumnIndex($colIdx++);
            $sheet->setCellValueExplicit(
                "{$colNeg}{$row}",
                '='.$item['neg'],
                DataType::TYPE_FORMULA
            );
            $sheet->setCellValueExplicit(
                "{$colCod}{$row}",
                '=IF('.$item['cond'].',"'.$item['codigo'].',","")',
                DataType::TYPE_FORMULA
            );
            $celNegCont[] = $colNeg.$row;
            $celCodCont[] = $colCod.$row;
        }

        foreach ($negs['terminais'] as $item) {
            $colNeg = Coordinate::stringFromColumnIndex($colIdx++);
            $colCod = Coordinate::stringFromColumnIndex($colIdx++);
            $sheet->setCellValueExplicit(
                "{$colNeg}{$row}",
                '='.$item['neg'],
                DataType::TYPE_FORMULA
            );
            $sheet->setCellValueExplicit(
                "{$colCod}{$row}",
                '=IF('.$item['cond'].',"'.$item['codigo'].',","")',
                DataType::TYPE_FORMULA
            );
            $celNegTerm[] = $colNeg.$row;
            $celCodTerm[] = $colCod.$row;
        }

        $somaCont = $ft->expressaoSomaDeCelulas($celNegCont);
        $somaTerm = $ft->expressaoSomaDeCelulas($celNegTerm);
        $listaDef = $ft->expressaoListaDeCelulas($celCodCont);
        $listaRec = $ft->expressaoListaDeCelulas($celCodTerm);
        $listaTodas = $ft->expressaoListaDeCelulas(array_merge($celCodCont, $celCodTerm));

        $formulaRes = $ft->formulaResultadoComSomas($somaCont, $somaTerm);
        $formulaObs = $ft->formulaObservacaoComListas($row, $colRes, $listaDef, $listaRec, $listaTodas);

        // Segurança: se ainda assim exceder o limite, gravar AMBOS estáticos (nunca misturar).
        if (strlen($formulaRes) > 8192 || strlen($formulaObs) > 8192) {
            $sheet->setCellValueExplicit(
                "{$colRes}{$row}",
                (string) ($aluno['resultado'] ?? ''),
                DataType::TYPE_STRING
            );
            $sheet->setCellValue("{$colObs}{$row}", $aluno['observacao'] ?? '');

            return;
        }

        $sheet->setCellValueExplicit("{$colRes}{$row}", $formulaRes, DataType::TYPE_FORMULA);
        $sheet->setCellValueExplicit("{$colObs}{$row}", $formulaObs, DataType::TYPE_FORMULA);
    }

    /**
     * Oculta colunas auxiliares de transição à direita de OBS/RESULTADO.
     */
    protected function ocultarColunasAuxiliaresTransicao(Worksheet $sheet, int $rowExemplo): void
    {
        if ($this->formulasTransicao === null || $this->helperColStart < 1) {
            return;
        }

        $negs = $this->formulasTransicao->negatividades($rowExemplo);
        $nHelpers = (count($negs['continuas']) + count($negs['terminais'])) * 2;
        if ($nHelpers <= 0) {
            return;
        }

        $ate = $this->helperColStart + $nHelpers - 1;
        for ($c = $this->helperColStart; $c <= $ate; $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setVisible(false);
        }
    }

    /**
     * Coordenadas Excel das colunas CA/CFD/MT usadas na fórmula RESULTADO.
     *
     * @return array<int, string>
     */
    protected function colunasResultadoExcel(): array
    {
        $trim = (int) ($this->dados['trimestre'] ?? 1);
        $classe = (int) ($this->dados['classe_numero'] ?? 10);
        $colsResultado = [];
        $col = $this->colInicialDisciplinas();

        foreach ($this->dados['disciplinas'] as $disc) {
            $colsDisc = $this->colunasParaDisciplina((int) $disc->id);
            $chave = $this->regime->chaveColunaResultado($trim, $classe, $colsDisc);
            $localCol = $col;

            foreach ($colsDisc as $def) {
                if (($def['key'] ?? '') === $chave) {
                    $colsResultado[] = Coordinate::stringFromColumnIndex($localCol);
                    break;
                }
                $localCol++;
            }

            $col += count($colsDisc);
        }

        return $colsResultado;
    }

    /**
     * Devolve a fórmula Excel para uma célula, pedindo ao regime as expressões
     * para o trimestre/classe actual. Mantém ID compatível ('mt_t1', 'cf',
     * 'ca', 'cfd_12', etc.).
     */
    protected function gerarFormula(string $id, array $colMap, int $row): string
    {
        $get = fn (string $k) => ($colMap[$k] ?? null) ? $colMap[$k] . $row : '';

        $classe = (int) ($this->dados['classe_numero'] ?? 10);
        $expr = $this->regime->expressoesExcel($get, $classe);

        return $expr[$id] ?? '';
    }

    public function title(): string
    {
        $turma = $this->dados['turma']->nome ?? 'TURMA';
        return mb_substr("{$turma} T{$this->dados['trimestre']}", 0, 31);
    }

    /**
     * Um único logotipo, centralizado no topo da pauta.
     */
    public function drawings(): array
    {
        $logoPath = $this->getLogoPath();
        if (!$logoPath) {
            return [];
        }

        // Cálculo da coluna central
        $colCentro = max(1, (int) ceil($this->totalCols / 2));
        $coord = Coordinate::stringFromColumnIndex($colCentro) . '1';

        $logo = new Drawing();
        $logo->setName('Logotipo');
        $logo->setDescription('Logo da instituição');
        $logo->setPath($logoPath);
        $logo->setHeight(58);
        $logo->setCoordinates($coord);
        // Desloca o logo para a esquerda metade da largura aproximada (~30px)
        // para ficar visualmente centralizado relativamente à coluna escolhida.
        $logo->setOffsetX(-30);
        $logo->setOffsetY(2);

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

    /**
     * Constrói o layout completo da pauta na folha.
     */
    protected function buildLayout(Worksheet $sheet): void
    {
        $this->configurarLarguraColunas($sheet);
        $this->configurarPagina($sheet);
        $this->desenharCabecalho($sheet);
        $this->desenharTituloPauta($sheet);
        $this->desenharLinhaAnoClasse($sheet);
        $this->desenharCabecalhoTabela($sheet);
        $this->desenharLinhasAlunos($sheet);
        $this->desenharRodape($sheet);
    }

    /**
     * Define larguras de colunas conforme o esquema dinâmico do trimestre/classe.
     */
    protected function configurarLarguraColunas(Worksheet $sheet): void
    {
        $sheet->getColumnDimension('A')->setWidth(6.8);   // N.º ORDEM
        $sheet->getColumnDimension('B')->setWidth(6.8);   // N.º PROC
        $sheet->getColumnDimension('C')->setWidth(34);    // NOME COMPLETO
        $sheet->getColumnDimension('D')->setWidth(5);     // SEXO

        $col = 5;

        // Colunas de CFD de disciplinas terminais anteriores (estreitas + rotação)
        for ($i = 0; $i < $this->colsCfdTerm; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col++))->setWidth(3.5);
        }

        foreach ($this->dados['disciplinas'] as $disc) {
            foreach ($this->colunasParaDisciplina((int) $disc->id) as $def) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col++))->setWidth($def['width']);
            }
        }

        // Coluna MÉDIA do aluno (regime administração no T3)
        if ($this->temColMedia) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col++))->setWidth(6);
        }

        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col++))->setWidth(18);  // OBS
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth(16);    // RESULT
    }

    /**
     * Configurações de impressão (paisagem, ajustar à largura).
     */
    protected function configurarPagina(Worksheet $sheet): void
    {
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        // Congela só N.º / Processo / Nome (A–C).
        // Sem congelar linhas: o cabeçalho institucional (1–11) permanece acessível
        // no topo; ao fazer scroll vertical vê-se a lista completa de alunos.
        $sheet->freezePane('D1');
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(12, 14);
        $sheet->getPageMargins()->setTop(0.35);
        $sheet->getPageMargins()->setRight(0.3);
        $sheet->getPageMargins()->setBottom(0.35);
        $sheet->getPageMargins()->setLeft(0.3);
    }

    /**
     * Cabeçalho institucional (linhas 1 a 8):
     *  - Esquerda: O DIRECTOR + linha + nome + DATA
     *  - Centro: nome instituição + ÁREA + CURSO (com logos nos lados, inseridos via drawings)
     *  - Direita: O (a) Director (a) + linha + nome
     */
    protected function desenharCabecalho(Worksheet $sheet): void
    {
        $colCentroIni = max(5, (int) floor($this->totalCols * 0.30));
        $colCentroFim = min($this->totalCols, (int) floor($this->totalCols * 0.72));
        $coordCentro = function (int $row) use ($colCentroIni, $colCentroFim) {
            return Coordinate::stringFromColumnIndex($colCentroIni) . $row
                . ':' . Coordinate::stringFromColumnIndex($colCentroFim) . $row;
        };

        // ===== ESQUERDA: O DIRECTOR =====
        // Linha 5: "O DIRECTOR"
        $sheet->mergeCells('A5:E5');
        $sheet->setCellValue('A5', 'O DIRECTOR');
        $sheet->getStyle('A5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Linha 6: linha de assinatura
        $sheet->mergeCells('A6:E6');
        $sheet->setCellValue('A6', str_repeat('_', 40));
        $sheet->getStyle('A6')->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Linha 7: nome do director (configurável)
        $sheet->mergeCells('A7:E7');
        $sheet->setCellValue('A7', $this->dados['director_geral_nome'] ?? '');
        $sheet->getStyle('A7')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Linha 8: DATA
        $sheet->mergeCells('A8:E8');
        $sheet->setCellValue('A8', 'DATA: _____/_____/_____');
        $sheet->getStyle('A8')->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // ===== CENTRO: Nome instituição / Área / Curso =====
        // Linha 5: nome instituição
        $sheet->mergeCells($coordCentro(5));
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colCentroIni) . '5', $this->dados['instituicao']['nome'] ?? '');
        $sheet->getStyle(Coordinate::stringFromColumnIndex($colCentroIni) . '5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Linha 6: ÁREA DE [...]
        $sheet->mergeCells($coordCentro(6));
        $area = $this->getAreaNome();
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colCentroIni) . '6', "ÁREA DE {$area}");
        $sheet->getStyle(Coordinate::stringFromColumnIndex($colCentroIni) . '6')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Linha 7: CURSO DE [...]
        $sheet->mergeCells($coordCentro(7));
        $cursoNome = $this->dados['turma']->curso->nome ?? '';
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colCentroIni) . '7', 'CURSO DE ' . mb_strtoupper($cursoNome));
        $sheet->getStyle(Coordinate::stringFromColumnIndex($colCentroIni) . '7')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // ===== DIREITA: O (a) Director (a) =====
        $colDirIni = $colCentroFim + 1;
        $colDirFim = $this->totalCols;
        $coordDir = function (int $row) use ($colDirIni, $colDirFim) {
            return Coordinate::stringFromColumnIndex($colDirIni) . $row
                . ':' . Coordinate::stringFromColumnIndex($colDirFim) . $row;
        };

        $sheet->mergeCells($coordDir(5));
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colDirIni) . '5', 'O (a) Director (a)');
        $sheet->getStyle(Coordinate::stringFromColumnIndex($colDirIni) . '5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->mergeCells($coordDir(6));
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colDirIni) . '6', str_repeat('_', 40));
        $sheet->getStyle(Coordinate::stringFromColumnIndex($colDirIni) . '6')->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->mergeCells($coordDir(7));
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colDirIni) . '7', $this->dados['director_geral_nome'] ?? '');
        $sheet->getStyle(Coordinate::stringFromColumnIndex($colDirIni) . '7')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Alturas das linhas do cabeçalho
        // Linhas 1-4 reservadas para o logo centralizado (~85px = 21.25 × 4)
        $sheet->getRowDimension(1)->setRowHeight(14);
        $sheet->getRowDimension(2)->setRowHeight(14);
        $sheet->getRowDimension(3)->setRowHeight(14);
        $sheet->getRowDimension(4)->setRowHeight(14);
        $sheet->getRowDimension(5)->setRowHeight(15);
        $sheet->getRowDimension(6)->setRowHeight(14);
        $sheet->getRowDimension(7)->setRowHeight(14);
        $sheet->getRowDimension(8)->setRowHeight(14);
    }

    /**
     * Linha 9: Título verde da pauta.
     */
    protected function desenharTituloPauta(Worksheet $sheet): void
    {
        $trimestresOrd = [1 => '1º', 2 => '2º', 3 => '3º'];
        $trimestreLabel = $trimestresOrd[$this->dados['trimestre']] ?? $this->dados['trimestre'] . 'º';

        $periodoKey = 'trimestre_' . $this->dados['trimestre'] . '_periodo_' . str_replace('/', '_', $this->dados['ano_lectivo']);
        $periodo = \App\Models\Configuracao::get($periodoKey);
        $titulo = "PAUTA DE APROVEITAMENTO - {$trimestreLabel} TRIMESTRE";
        if ($periodo) {
            $titulo .= " ({$periodo})";
        }
        $momento = $this->dados['momento_conselho'] ?? \App\Support\PautaMomentoConselho::POS;
        $titulo .= ' — '.\App\Support\PautaMomentoConselho::rotuloCurto($momento);

        $sheet->mergeCells('A9:' . Coordinate::stringFromColumnIndex($this->totalCols) . '9');
        $sheet->setCellValue('A9', $titulo);
        $sheet->getStyle('A9')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::COR_VERDE]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(9)->setRowHeight(18);
    }

    /**
     * Linha 10: Ano lectivo (esquerda) + Classe (direita)
     */
    protected function desenharLinhaAnoClasse(Worksheet $sheet): void
    {
        $meta = (int) floor($this->totalCols / 2);
        $coordEsq = 'A10:' . Coordinate::stringFromColumnIndex($meta) . '10';
        $coordDir = Coordinate::stringFromColumnIndex($meta + 1) . '10:'
            . Coordinate::stringFromColumnIndex($this->totalCols) . '10';

        $sheet->mergeCells($coordEsq);
        $sheet->setCellValue('A10', 'ANO LECTIVO: ' . $this->dados['ano_lectivo']);
        $sheet->getStyle('A10')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => self::COR_PRETO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->mergeCells($coordDir);
        $classe = $this->dados['turma']->classe->nome ?? '';
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($meta + 1) . '10', mb_strtoupper($classe));
        $sheet->getStyle(Coordinate::stringFromColumnIndex($meta + 1) . '10')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => self::COR_PRETO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(10)->setRowHeight(15);
        // Linha 11: legenda discreta (cores + exame)
        $sheet->mergeCells('A11:'.Coordinate::stringFromColumnIndex($this->totalCols).'11');
        $legenda = 'Legenda: vermelho = nota < 10  |  azul = nota ≥ 10  |  RECUP./RECURSO (quando lançado) substitui CA/CFD no RESULTADO e OBSERVAÇÃO';
        $sheet->setCellValue('A11', $legenda);
        $sheet->getStyle('A11')->applyFromArray([
            'font' => ['italic' => true, 'size' => 8, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(11)->setRowHeight(12);
    }

    /**
     * Cabeçalho da tabela (linhas 12-14) — dinâmico conforme o esquema do trimestre/classe.
     *  - Linha 12: nomes das disciplinas (verde, merged em todas as cols da disc.)
     *  - Linhas 13-14: sub-cabeçalhos (group "FALTAS" → J|I sob ele) e demais cols
     */
    protected function desenharCabecalhoTabela(Worksheet $sheet): void
    {
        // Colunas fixas (N.OR, N.PR, NOME, SEXO)
        $sheet->setCellValue('A12', "N.º\nORDEM");
        $sheet->setCellValue('B12', "N.º\nPROC.");
        $sheet->setCellValue('C12', 'NOME COMPLETO');
        $sheet->setCellValue('D12', 'SEXO');
        $sheet->mergeCells('A12:A14');
        $sheet->mergeCells('B12:B14');
        $sheet->mergeCells('C12:C14');
        $sheet->mergeCells('D12:D14');

        $estiloColFixa = [
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COR_PRETO]]],
        ];
        $sheet->getStyle('A12:D14')->applyFromArray($estiloColFixa);

        $col = 5;

        // ===== Colunas extras: CFD das disciplinas terminais anteriores
        // (ex.: "T.I.C. - C.F.D" para a 11ª; podem ser várias para a 12ª) =====
        foreach ($this->cfdTerminais as $dt) {
            $colLetra = Coordinate::stringFromColumnIndex($col);
            $codigo = $dt['codigo'] ?: $this->abreviarDisciplina($dt['nome']);
            $sheet->mergeCells("{$colLetra}12:{$colLetra}14");
            $sheet->setCellValue("{$colLetra}12", "{$codigo} - C.F.D");
            $sheet->getStyle("{$colLetra}12")->applyFromArray([
                'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => self::COR_NAVY]],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'textRotation' => 90,
                    'wrapText' => true,
                ],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COR_PRETO]]],
            ]);
            $col++;
        }

        // Por disciplina — colunas variáveis (podem diferir entre disciplinas)
        foreach ($this->dados['disciplinas'] as $disc) {
            $colsDisc = $this->colunasParaDisciplina((int) $disc->id);
            $nColsDisc = count($colsDisc);
            $colIni = Coordinate::stringFromColumnIndex($col);
            $colFim = Coordinate::stringFromColumnIndex($col + $nColsDisc - 1);

            // Linha 12 — nome da disciplina (verde)
            $sheet->mergeCells("{$colIni}12:{$colFim}12");
            $sheet->setCellValue("{$colIni}12", $this->abreviarDisciplina($disc->nome));
            $sheet->getStyle("{$colIni}12")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COR_VERDE]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COR_PRETO]]],
            ]);

            // Linhas 13-14 — agrupamentos (ex.: FALTAS merge sobre 2 cols com J|I em baixo)
            // ou colunas únicas com rowspan
            $localCol = $col;
            $i = 0;
            while ($i < $nColsDisc) {
                $def = $colsDisc[$i];
                $colLetra = Coordinate::stringFromColumnIndex($localCol);

                if (!empty($def['group']) && $def['group'] === 'FALTAS') {
                    $span = (int) ($def['merged'] ?? 2);
                    if ($span === 1) {
                        $sheet->mergeCells("{$colLetra}13:{$colLetra}14");
                        $sheet->setCellValue("{$colLetra}13", $def['header']);
                    } else {
                        $colLetra2 = Coordinate::stringFromColumnIndex($localCol + 1);
                        $sheet->mergeCells("{$colLetra}13:{$colLetra2}13");
                        $sheet->setCellValue("{$colLetra}13", 'FALTAS');
                        $sheet->setCellValue("{$colLetra}14", $def['header']);
                        $sheet->setCellValue("{$colLetra2}14", $colsDisc[$i + 1]['header']);
                    }
                    $localCol += $span;
                    $i += $span;
                    continue;
                }

                // Coluna individual com rowspan (linha 13-14)
                $sheet->mergeCells("{$colLetra}13:{$colLetra}14");
                $sheet->setCellValue("{$colLetra}13", $def['header']);
                $localCol++;
                $i++;
            }

            // Estilo de TODOS os sub-cabeçalhos da disciplina.
            // T2 e T3 têm muitas colunas estreitas → texto rotacionado 90° (igual ao modelo oficial)
            $trim = (int) ($this->dados['trimestre'] ?? 1);
            $rotacao = $trim >= 2 ? 90 : 0;
            $sheet->getStyle("{$colIni}13:{$colFim}14")->applyFromArray([
                'font' => ['size' => 8, 'color' => ['rgb' => self::COR_NAVY]],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                    'textRotation' => $rotacao,
                ],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COR_PRETO]]],
            ]);

            // Realçar MT/CF/CA (verde pálido) e CFD (verde médio) nos sub-cabeçalhos desta disciplina
            $localCol = $col;
            foreach ($colsDisc as $def) {
                $fundo = PautaCores::fundoRgb($def);
                if ($fundo !== null) {
                    $cl = Coordinate::stringFromColumnIndex($localCol);
                    $sheet->getStyle("{$cl}13:{$cl}14")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => self::COR_NAVY]],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fundo]],
                    ]);
                }
                $localCol++;
            }

            $col += $nColsDisc;
        }

        // Coluna MÉDIA do aluno (regime administração no T3)
        if ($this->temColMedia) {
            $colMed = Coordinate::stringFromColumnIndex($this->totalCols - 2);
            $sheet->mergeCells("{$colMed}12:{$colMed}14");
            $sheet->setCellValue("{$colMed}12", 'MÉDIA');
            $sheet->getStyle("{$colMed}12:{$colMed}14")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COR_NAVY]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => PautaCores::VERDE_PALIDO]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COR_PRETO]]],
            ]);
        }

        // Colunas OBS e RESULT
        $colObs = Coordinate::stringFromColumnIndex($this->totalCols - 1);
        $colRes = Coordinate::stringFromColumnIndex($this->totalCols);
        $sheet->mergeCells("{$colObs}12:{$colObs}14");
        $sheet->mergeCells("{$colRes}12:{$colRes}14");
        $sheet->setCellValue("{$colObs}12", 'OBSERVAÇÃO');
        $sheet->setCellValue("{$colRes}12", 'RESULTADO');
        $sheet->getStyle("{$colObs}12:{$colRes}14")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COR_PRETO]]],
        ]);

        $sheet->getRowDimension(12)->setRowHeight(28);
        // Quando sub-headers são rotacionados (T2/T3), as linhas precisam de mais altura
        $trim = (int) ($this->dados['trimestre'] ?? 1);
        if ($trim >= 2) {
            $sheet->getRowDimension(13)->setRowHeight(28);
            $sheet->getRowDimension(14)->setRowHeight(28);
        } else {
            $sheet->getRowDimension(13)->setRowHeight(16);
            $sheet->getRowDimension(14)->setRowHeight(16);
        }
    }

    /**
     * Preenche as linhas de alunos com valores diretos + FÓRMULAS Excel.
     * O esquema das colunas vem de getColunasDisciplina() (variável por trimestre/classe).
     * Cores via formatação condicional (atualizam quando notas são editadas).
     */
    protected function desenharLinhasAlunos(Worksheet $sheet): void
    {
        $row = $this->linhaDados;
        $colObs = Coordinate::stringFromColumnIndex($this->totalCols - 1);
        $colRes = Coordinate::stringFromColumnIndex($this->totalCols);

        // Colunas CA (T3 10.ª/11.ª) ou CFD (T3 12.ª) — usadas pela fórmula RESULTADO
        $colsResultado = $this->colunasResultadoExcel();

        foreach ($this->dados['alunos'] as $aluno) {
            $sheet->setCellValue("A{$row}", $aluno['numero_ordem']);
            $sheet->setCellValue("B{$row}", $aluno['numero_processo'] ?? '');
            // Nome do aluno: indicar NEE com (*) — Art.º 29 do DE 424/25
            $nomeCel = $aluno['nome'] . (! empty($aluno['educacao_especial']) ? ' (*)' : '');
            $sheet->setCellValue("C{$row}", $nomeCel);
            if (! empty($aluno['educacao_especial']) && ! empty($aluno['necessidades_especificas'])) {
                $sheet->getComment("C{$row}")
                    ->getText()
                    ->createTextRun('NEE: ' . $aluno['necessidades_especificas']);
            }
            $sheet->setCellValue("D{$row}", strtoupper(substr($aluno['genero'] ?? '', 0, 1)));

            // Preencher colunas CFD das disciplinas terminais anteriores
            $cfdCol = 5;
            foreach ($this->cfdTerminais as $dt) {
                $coord = Coordinate::stringFromColumnIndex($cfdCol) . $row;
                $val = $aluno['cfd_terminais'][$dt['disciplina_id']] ?? null;
                if ($val !== null && $val !== '') {
                    $sheet->setCellValue($coord, (int) round($val));
                } else {
                    $sheet->setCellValue($coord, '');
                }
                $cfdCol++;
            }

            $col = $this->colInicialDisciplinas();
            foreach ($this->dados['disciplinas'] as $disc) {
                $d = $aluno['disciplinas'][$disc->id] ?? null;
                $colsDisc = $this->colunasParaDisciplina((int) $disc->id);

                // Mapa key → letra da coluna (para construir fórmulas)
                $colMap = [];
                $localCol = $col;
                foreach ($colsDisc as $def) {
                    $colMap[$def['key']] = Coordinate::stringFromColumnIndex($localCol);
                    $localCol++;
                }

                // Preencher cada coluna conforme o tipo
                $localCol = $col;
                foreach ($colsDisc as $def) {
                    $coord = Coordinate::stringFromColumnIndex($localCol) . $row;
                    $isDesistente = ! empty($aluno['desistente']);
                    $val = $d[$def['key']] ?? null;

                    if ($isDesistente && ($def['tipo'] ?? '') === 'nota' && ($val === null || $val === '')) {
                        $this->marcarCelulaDesistente($sheet, $coord);
                    } elseif ($def['tipo'] === 'formula') {
                        $formula = $this->gerarFormula($def['formula'] ?? '', $colMap, $row);
                        if ($formula !== '' && ! $isDesistente) {
                            $sheet->setCellValueExplicit($coord, $formula, DataType::TYPE_FORMULA);
                        }
                    } elseif ($def['tipo'] === 'int') {
                        $val = $val ?? 0;
                        $sheet->setCellValue($coord, $val > 0 ? $val : '');
                    } else { // 'nota'
                        if ($val !== null && $val !== '') {
                            $sheet->setCellValue($coord, (int) round($val));
                        } else {
                            $sheet->setCellValue($coord, '');
                        }
                    }
                    $localCol++;
                }

                $col += count($colsDisc);
            }

            // Coluna MÉDIA do aluno (regime administração no T3)
            //  - Valor pré-calculado server-side (média aritmética das CAs do ano)
            if ($this->temColMedia) {
                $colMed = Coordinate::stringFromColumnIndex($this->totalCols - 2);
                $mediaVal = $aluno['media_aluno'] ?? null;
                if ($mediaVal !== null && $mediaVal !== '') {
                    $sheet->setCellValue("{$colMed}{$row}", $mediaVal);
                } else {
                    $sheet->setCellValue("{$colMed}{$row}", '');
                }
                $sheet->getStyle("{$colMed}{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => PautaCores::VERDE_PALIDO]],
                    'font' => ['bold' => true, 'size' => 10],
                ]);
            }

            // Observação + RESULTADO (colunas auxiliares ocultas → fórmulas curtas e consistentes)
            if (! empty($aluno['desistente'])) {
                $sheet->setCellValueExplicit(
                    "{$colRes}{$row}",
                    'DESISTENTE',
                    DataType::TYPE_STRING
                );
                $sheet->setCellValue("{$colObs}{$row}", 'DESISTENTE');
            } elseif ($this->formulasTransicao !== null) {
                $this->escreverResultadoEObservacaoComHelpers(
                    $sheet,
                    $row,
                    $colObs,
                    $colRes,
                    $aluno,
                );
            } elseif ($colsResultado === []) {
                $sheet->setCellValue("{$colObs}{$row}", $aluno['observacao'] ?? '');
                $sheet->setCellValue("{$colRes}{$row}", '');
            } else {
                $sheet->setCellValue("{$colObs}{$row}", $aluno['observacao'] ?? '');
                $somasPreenchidas = [];
                $somasNegativas = [];
                foreach ($colsResultado as $colD) {
                    $cell = "{$colD}{$row}";
                    $somasPreenchidas[] = "IF(ISNUMBER({$cell}),1,0)";
                    $somasNegativas[] = "IF(AND(ISNUMBER({$cell}),{$cell}<10),1,0)";
                }
                $trim = (int) ($this->dados['trimestre'] ?? 1);
                [$labelOk, $labelKo] = $trim === 3 ? ['APROVADO', 'REPROVADO'] : ['APTO', 'NÃO APTO'];
                $somaP = implode('+', $somasPreenchidas);
                $somaN = implode('+', $somasNegativas);
                $formulaRes = "=IF(({$somaP})=0,\"\",IF(({$somaN})>2,\"{$labelKo}\",\"{$labelOk}\"))";
                $sheet->setCellValueExplicit("{$colRes}{$row}", $formulaRes, DataType::TYPE_FORMULA);
            }

            $row++;
        }

        $ultLinha = $row - 1;

        // Estilo base de TODO o corpo de dados
        $rangeDados = "A{$this->linhaDados}:" . Coordinate::stringFromColumnIndex($this->totalCols) . $ultLinha;
        $sheet->getStyle($rangeDados)->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => self::COR_PRETO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COR_PRETO]]],
        ]);

        // Zebra suave (fundos oficiais MT/CF/CA/CFD/exame aplicam-se a seguir e prevalecem)
        $this->aplicarZebraLinhasAlunos($sheet, $this->linhaDados, $ultLinha);

        $sheet->getStyle("C{$this->linhaDados}:C{$ultLinha}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Realçar MT/CF/CA (verde pálido) e CFD (verde médio) em todas as linhas de alunos
        $this->aplicarFundosColunasPauta($sheet, $this->linhaDados, $ultLinha);

        // Formatação condicional (cores dinâmicas)
        $this->aplicarFormatacaoCondicionalNotas($sheet, $ultLinha);
        $this->aplicarFormatacaoCondicionalResultado($sheet, $ultLinha);
        $this->aplicarFormatacaoCondicionalFaltas($sheet, $ultLinha);

        for ($r = $this->linhaDados; $r < $row; $r++) {
            $sheet->getRowDimension($r)->setRowHeight(16);
        }

        if ($this->formulasTransicao !== null) {
            $this->ocultarColunasAuxiliaresTransicao($sheet, $this->linhaDados);
            $colObs = Coordinate::stringFromColumnIndex($this->totalCols - 1);
            $sheet->getStyle("{$colObs}{$this->linhaDados}:{$colObs}{$ultLinha}")->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ]);
            $sheet->getColumnDimension($colObs)->setWidth(28);
        }
    }

    /**
     * Linhas alternadas nos alunos (cinza muito claro), sem interferir com fundos oficiais.
     */
    protected function aplicarZebraLinhasAlunos(Worksheet $sheet, int $linhaIni, int $linhaFim): void
    {
        $ultimaCol = Coordinate::stringFromColumnIndex($this->totalCols);
        for ($r = $linhaIni; $r <= $linhaFim; $r++) {
            if ((($r - $linhaIni) % 2) === 1) {
                $sheet->getStyle("A{$r}:{$ultimaCol}{$r}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => PautaCores::ZEBRA],
                    ],
                ]);
            }
        }
    }

    /**
     * Marca célula de lançamento vazia de desistente com X diagonal (modelo oficial).
     */
    protected function marcarCelulaDesistente(Worksheet $sheet, string $coord): void
    {
        $sheet->setCellValue($coord, '');
        $borders = $sheet->getStyle($coord)->getBorders();
        $borders->getDiagonal()->setBorderStyle(Border::BORDER_THIN);
        $borders->setDiagonalDirection(Borders::DIAGONAL_BOTH);
    }

    /**
     * Aplica fundos oficiais (verde pálido / verde CFD) às colunas de notas da pauta.
     */
    protected function aplicarFundosColunasPauta(Worksheet $sheet, int $linhaIni, int $linhaFim): void
    {
        $col = $this->colInicialDisciplinas();
        foreach ($this->dados['disciplinas'] as $disc) {
            $colsDisc = $this->colunasParaDisciplina((int) $disc->id);
            $localCol = $col;
            foreach ($colsDisc as $def) {
                $fundo = PautaCores::fundoRgb($def);
                if ($fundo !== null) {
                    $cl = Coordinate::stringFromColumnIndex($localCol);
                    $sheet->getStyle("{$cl}{$linhaIni}:{$cl}{$linhaFim}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fundo]],
                        'font' => ['bold' => true, 'size' => 10],
                    ]);
                }
                $localCol++;
            }
            $col += count($colsDisc);
        }
    }

    /**
     * Aplica formatação condicional a todas as células de NOTA (qualquer key
     * que não seja 'int' / faltas) e a todas as colunas marcadas como destaque:
     *  - Vermelho se valor < 10
     *  - Azul se valor >= 10
     * Recalcula automaticamente quando o utilizador edita notas no Excel.
     */
    protected function aplicarFormatacaoCondicionalNotas(Worksheet $sheet, int $ultLinha): void
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

        // Aplicar também a formatação às colunas extras de CFD terminais (se houver)
        if ($this->colsCfdTerm > 0) {
            $colIni = Coordinate::stringFromColumnIndex(5);
            $colFim = Coordinate::stringFromColumnIndex(5 + $this->colsCfdTerm - 1);
            $range = "{$colIni}{$this->linhaDados}:{$colFim}{$ultLinha}";
            $existentes = $sheet->getStyle($range)->getConditionalStyles();
            $existentes[] = clone $condVermelho;
            $existentes[] = clone $condAzul;
            $sheet->getStyle($range)->setConditionalStyles($existentes);
        }

        // Formatação condicional na coluna MÉDIA (regime administração no T3)
        if ($this->temColMedia) {
            $colMed = Coordinate::stringFromColumnIndex($this->totalCols - 2);
            $range = "{$colMed}{$this->linhaDados}:{$colMed}{$ultLinha}";
            $existentes = $sheet->getStyle($range)->getConditionalStyles();
            $existentes[] = clone $condVermelho;
            $existentes[] = clone $condAzul;
            $sheet->getStyle($range)->setConditionalStyles($existentes);
        }

        // Localiza as ranges contínuas de colunas de NOTA (saltando colunas 'int' como faltas)
        $col = $this->colInicialDisciplinas();
        foreach ($this->dados['disciplinas'] as $disc) {
            $colsDisc = $this->colunasParaDisciplina((int) $disc->id);
            $localCol = $col;
            $rangeIni = null;
            $applyRange = function ($rangeIni, $rangeFim) use ($sheet, $condVermelho, $condAzul, $ultLinha) {
                if ($rangeIni === null) {
                    return;
                }
                $range = "{$rangeIni}{$this->linhaDados}:{$rangeFim}{$ultLinha}";
                $existentes = $sheet->getStyle($range)->getConditionalStyles();
                $existentes[] = clone $condVermelho;
                $existentes[] = clone $condAzul;
                $sheet->getStyle($range)->setConditionalStyles($existentes);
            };

            $rangeFim = null;
            foreach ($colsDisc as $def) {
                $colLetra = Coordinate::stringFromColumnIndex($localCol);
                if ($def['tipo'] === 'int') {
                    $applyRange($rangeIni, $rangeFim);
                    $rangeIni = null;
                    $rangeFim = null;
                } else {
                    if ($rangeIni === null) {
                        $rangeIni = $colLetra;
                    }
                    $rangeFim = $colLetra;
                }
                $localCol++;
            }
            $applyRange($rangeIni, $rangeFim);

            $col += count($colsDisc);
        }
    }

    /**
     * Aplica formatação condicional às colunas de FALTAS conforme Decreto Executivo 424/25, Art.º 35:
     *  - Faltas injustificadas (key='faltas_i'): vermelho + negrito quando > 0
     *  - Faltas justificadas (key='faltas_j'): azul quando > 0
     */
    protected function aplicarFormatacaoCondicionalFaltas(Worksheet $sheet, int $ultLinha): void
    {
        $condInj = new Conditional();
        $condInj->setConditionType(Conditional::CONDITION_CELLIS)
            ->setOperatorType(Conditional::OPERATOR_GREATERTHAN)
            ->addCondition('0');
        $condInj->getStyle()->getFont()->setBold(true)->getColor()->setRGB(self::COR_VERMELHO);

        $condJust = new Conditional();
        $condJust->setConditionType(Conditional::CONDITION_CELLIS)
            ->setOperatorType(Conditional::OPERATOR_GREATERTHAN)
            ->addCondition('0');
        $condJust->getStyle()->getFont()->getColor()->setRGB(self::COR_AZUL_NOTA);

        $col = $this->colInicialDisciplinas();
        foreach ($this->dados['disciplinas'] as $disc) {
            $colsDisc = $this->colunasParaDisciplina((int) $disc->id);
            $localCol = $col;
            foreach ($colsDisc as $def) {
                $colLetra = Coordinate::stringFromColumnIndex($localCol);
                $range = "{$colLetra}{$this->linhaDados}:{$colLetra}{$ultLinha}";

                if (($def['key'] ?? null) === 'faltas_i') {
                    $existentes = $sheet->getStyle($range)->getConditionalStyles();
                    $existentes[] = clone $condInj;
                    $sheet->getStyle($range)->setConditionalStyles($existentes);
                } elseif (($def['key'] ?? null) === 'faltas_j') {
                    $existentes = $sheet->getStyle($range)->getConditionalStyles();
                    $existentes[] = clone $condJust;
                    $sheet->getStyle($range)->setConditionalStyles($existentes);
                }
                $localCol++;
            }
            $col += count($colsDisc);
        }
    }

    /**
     * Aplica formatação condicional à coluna RESULTADO:
     *  - Verde se "APTO" / "APROVADO"
     *  - Vermelho se "NÃO APTO" / "REPROVADO"
     */
    protected function aplicarFormatacaoCondicionalResultado(Worksheet $sheet, int $ultLinha): void
    {
        $colRes = Coordinate::stringFromColumnIndex($this->totalCols);
        $range = "{$colRes}{$this->linhaDados}:{$colRes}{$ultLinha}";
        $primeiraCelula = "{$colRes}{$this->linhaDados}";

        // Vermelho + negrito (Art.º 35, alínea b): NÃO APTO, REPROVADO, RETIDO, EXCLUÍDO,
        // PERDEU O ANO, NÃO TRANSITA — qualquer um destes destacado a vermelho.
        $condReprovado = new Conditional();
        $condReprovado->setConditionType(Conditional::CONDITION_EXPRESSION)
            ->addCondition(
                'OR(' .
                'NOT(ISERROR(SEARCH("NÃO",' . $primeiraCelula . '))),' .
                'NOT(ISERROR(SEARCH("REPROV",' . $primeiraCelula . '))),' .
                'NOT(ISERROR(SEARCH("N/TRANSITA",' . $primeiraCelula . '))),' .
                'NOT(ISERROR(SEARCH("N/APTO",' . $primeiraCelula . '))),' .
                'NOT(ISERROR(SEARCH("RETIDO",' . $primeiraCelula . '))),' .
                'NOT(ISERROR(SEARCH("EXCLU",' . $primeiraCelula . '))),' .
                'NOT(ISERROR(SEARCH("PERDEU",' . $primeiraCelula . ')))' .
                ')'
            );
        $condReprovado->getStyle()->getFont()->setBold(true)->getColor()->setRGB(self::COR_VERMELHO);

        // Azul + negrito: TRANSITA, APTO, APROVADO, Admitido
        $condAprovado = new Conditional();
        $condAprovado->setConditionType(Conditional::CONDITION_EXPRESSION)
            ->addCondition(
                'OR(' .
                $primeiraCelula . '="TRANSITA",' .
                $primeiraCelula . '="APTO",' .
                $primeiraCelula . '="APROVADO",' .
                $primeiraCelula . '="ADMITIDO"' .
                ')'
            );
        $condAprovado->getStyle()->getFont()->setBold(true)->getColor()->setRGB(self::COR_AZUL_NOTA);

        // Âmbar + negrito: Transita com Deficiência / Recurso (12.ª)
        $condDeficiencia = new Conditional();
        $condDeficiencia->setConditionType(Conditional::CONDITION_EXPRESSION)
            ->addCondition(
                'OR(' .
                'NOT(ISERROR(SEARCH("TRANSITA/C DEF",' . $primeiraCelula . '))),' .
                $primeiraCelula . '="RECURSO",' .
                $primeiraCelula . '="RECUPERAÇÃO"' .
                ')'
            );
        $condDeficiencia->getStyle()->getFont()->setBold(true)->getColor()->setRGB('D97706');

        $existentes = $sheet->getStyle($range)->getConditionalStyles();
        $existentes[] = $condReprovado;
        $existentes[] = $condAprovado;
        $existentes[] = $condDeficiencia;
        $sheet->getStyle($range)->setConditionalStyles($existentes);
    }

    /**
     * Rodapé conforme modelo oficial:
     *  - Linha conselho: DATA DO CONSELHO + (DISCIPLINAS TERMINAIS) + nomes dos professores por disciplina
     *  - Linha seguinte: data (A:D) + linhas de assinatura dos professores
     *  - OBSERVAÇÃO + área livre
     *  - Assinaturas: DIRECTOR DE TURMA | COORDENADOR DE CURSO | SUBDIRECTOR PEDAGÓGICO
     */
    protected function desenharRodape(Worksheet $sheet): void
    {
        $estiloRotulo = [
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
        $estiloProf = [
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => self::COR_AZUL_MEDIO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ];
        $estiloLinhaAssin = [
            'font' => ['size' => 9, 'color' => ['rgb' => self::COR_AZUL_MEDIO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_BOTTOM],
        ];

        // ===== Linha 1: DATA DO CONSELHO + professores (logo após último aluno) =====
        $linhaProf = $this->ultimaLinha + 1;

        $sheet->mergeCells("A{$linhaProf}:D{$linhaProf}");
        $sheet->setCellValue("A{$linhaProf}", 'DATA DO CONSELHO DE TURMA');
        $sheet->getStyle("A{$linhaProf}")->applyFromArray($estiloRotulo);

        if ($this->colsCfdTerm > 0) {
            $cfdIniLetra = Coordinate::stringFromColumnIndex(5);
            $cfdFimLetra = Coordinate::stringFromColumnIndex(4 + $this->colsCfdTerm);
            $sheet->mergeCells("{$cfdIniLetra}{$linhaProf}:{$cfdFimLetra}{$linhaProf}");
            $sheet->setCellValue("{$cfdIniLetra}{$linhaProf}", 'DISCIPLINAS TERMINAIS');
            $sheet->getStyle("{$cfdIniLetra}{$linhaProf}")->applyFromArray($estiloRotulo);
        }

        $col = $this->colInicialDisciplinas();
        foreach ($this->dados['disciplinas'] as $disc) {
            $nColsDisc = count($this->colunasParaDisciplina((int) $disc->id));
            $colIni = Coordinate::stringFromColumnIndex($col);
            $colFim = Coordinate::stringFromColumnIndex($col + $nColsDisc - 1);
            $nomeProf = $this->formatarNomeProfessorPauta(
                $this->dados['professores'][$disc->id] ?? null
            );

            $sheet->mergeCells("{$colIni}{$linhaProf}:{$colFim}{$linhaProf}");
            $sheet->setCellValue("{$colIni}{$linhaProf}", $nomeProf);
            $sheet->getStyle("{$colIni}{$linhaProf}")->applyFromArray($estiloProf);

            $col += $nColsDisc;
        }
        $sheet->getRowDimension($linhaProf)->setRowHeight(20);

        // ===== Linha 2: data do conselho + espaço de assinatura por disciplina =====
        $linhaData = $linhaProf + 1;

        $dataConselho = !empty($this->dados['data_conselho'])
            ? $this->dados['data_conselho']
            : '_______/_______/_______';
        $sheet->mergeCells("A{$linhaData}:D{$linhaData}");
        $sheet->setCellValueExplicit("A{$linhaData}", $dataConselho, DataType::TYPE_STRING);
        $sheet->getStyle("A{$linhaData}")->applyFromArray([
            'font' => ['bold' => !empty($this->dados['data_conselho']), 'size' => 10, 'color' => ['rgb' => self::COR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $col = $this->colInicialDisciplinas();
        foreach ($this->dados['disciplinas'] as $disc) {
            $nColsDisc = count($this->colunasParaDisciplina((int) $disc->id));
            $colIni = Coordinate::stringFromColumnIndex($col);
            $colFim = Coordinate::stringFromColumnIndex($col + $nColsDisc - 1);

            $sheet->mergeCells("{$colIni}{$linhaData}:{$colFim}{$linhaData}");
            $sheet->setCellValue("{$colIni}{$linhaData}", '');
            $sheet->getStyle("{$colIni}{$linhaData}")->applyFromArray(array_merge($estiloLinhaAssin, [
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COR_AZUL_MEDIO]]],
            ]));

            $col += $nColsDisc;
        }
        $sheet->getRowDimension($linhaData)->setRowHeight(22);

        // ===== Linha vazia + OBSERVAÇÃO =====
        $linhaObs = $linhaData + 2;
        $sheet->setCellValue("A{$linhaObs}", 'OBSERVAÇÃO:');
        $sheet->getStyle("A{$linhaObs}")->applyFromArray($estiloRotulo);

        $linhaObsFim = $linhaObs + 3;
        $colFimObs = Coordinate::stringFromColumnIndex($this->totalCols);
        $sheet->mergeCells("A" . ($linhaObs + 1) . ":{$colFimObs}{$linhaObsFim}");
        $sheet->getStyle("A" . ($linhaObs + 1) . ":{$colFimObs}{$linhaObsFim}")->applyFromArray([
            'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COR_NAVY]]],
        ]);
        for ($r = $linhaObs + 1; $r <= $linhaObsFim; $r++) {
            $sheet->getRowDimension($r)->setRowHeight(16);
        }

        // ===== Assinaturas finais (posição proporcional ao modelo oficial) =====
        $linhaTitulos = $linhaObsFim + 2;
        $linhaLinha = $linhaTitulos + 1;
        $linhaNomes = $linhaTitulos + 2;

        foreach ($this->blocosAssinaturaFinal() as $b) {
            $ini = Coordinate::stringFromColumnIndex($b['col']);
            $fim = Coordinate::stringFromColumnIndex(min($this->totalCols, $b['col'] + $b['span'] - 1));
            $nome = $this->formatarNomeAssinaturaFinal(
                $this->dados[$b['nome_key']] ?? null,
                $b['cargo']
            );

            $sheet->mergeCells("{$ini}{$linhaTitulos}:{$fim}{$linhaTitulos}");
            $sheet->setCellValue("{$ini}{$linhaTitulos}", $b['titulo']);
            $sheet->getStyle("{$ini}{$linhaTitulos}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COR_AZUL_MEDIO]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $sheet->mergeCells("{$ini}{$linhaLinha}:{$fim}{$linhaLinha}");
            $sheet->setCellValue("{$ini}{$linhaLinha}", str_repeat('_', max(20, $b['span'] * 3)));
            $sheet->getStyle("{$ini}{$linhaLinha}")->applyFromArray($estiloLinhaAssin);

            $sheet->mergeCells("{$ini}{$linhaNomes}:{$fim}{$linhaNomes}");
            $sheet->setCellValue("{$ini}{$linhaNomes}", $nome);
            $sheet->getStyle("{$ini}{$linhaNomes}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COR_NAVY]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
        }

        $sheet->getRowDimension($linhaTitulos)->setRowHeight(18);
        $sheet->getRowDimension($linhaLinha)->setRowHeight(20);
        $sheet->getRowDimension($linhaNomes)->setRowHeight(20);
    }

    /**
     * Posição dos 3 blocos de assinatura final (proporções do modelo Excel oficial).
     *
     * @return array<int, array{col:int, span:int, titulo:string, cargo:string, nome_key:string}>
     */
    protected function blocosAssinaturaFinal(): array
    {
        $t = $this->totalCols;

        return [
            [
                'col' => 3,
                'span' => max(6, (int) round($t * 0.10)),
                'titulo' => 'O DIRECTOR DE TURMA',
                'cargo' => 'director_turma',
                'nome_key' => 'director_turma_nome',
            ],
            [
                'col' => max(10, (int) round($t * 0.36)),
                'span' => max(14, (int) round($t * 0.22)),
                'titulo' => 'O COORDENADOR DE CURSO',
                'cargo' => 'coordenador',
                'nome_key' => 'coordenador_curso_nome',
            ],
            [
                'col' => max(20, (int) round($t * 0.80)),
                'span' => max(8, $t - max(20, (int) round($t * 0.80)) + 1),
                'titulo' => 'O SUBDIRECTOR PEDAGÓGICO',
                'cargo' => 'subdirector',
                'nome_key' => 'subdirector_nome',
            ],
        ];
    }

    /** Nome do professor sob a disciplina (maiúsculas, como no modelo). */
    protected function formatarNomeProfessorPauta(?string $nome): string
    {
        if ($nome === null || trim($nome) === '') {
            return '';
        }

        return mb_strtoupper(trim($nome));
    }

    /** Nome com título para assinatura final (PROF. / DR.º). */
    protected function formatarNomeAssinaturaFinal(?string $nome, string $cargo): string
    {
        if ($nome === null || trim($nome) === '') {
            return '';
        }

        $n = mb_strtoupper(trim($nome));
        if (preg_match('/^(PROF\.?|ENG\.?|DR\.?)/u', $n)) {
            return $n;
        }

        return match ($cargo) {
            'director_turma', 'coordenador' => 'PROF. ' . $n,
            'subdirector' => 'DR.º ' . $n,
            default => $n,
        };
    }

    /**
     * Obtém o caminho local do logótipo da instituição.
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
     * Deriva "ÁREA DE [X]" do nome do curso (em maiúsculas).
     * Ex.: "Técnico de Informática" → "INFORMÁTICA"
     */
    protected function getAreaNome(): string
    {
        $cursoNome = $this->dados['turma']->curso->nome ?? '';
        if (preg_match('/\b(?:de|do|da|dos|das)\s+(.+)$/iu', $cursoNome, $m)) {
            return mb_strtoupper(trim($m[1]));
        }
        return mb_strtoupper($cursoNome);
    }

    /**
     * Converte o nome da disciplina em abreviatura adequada para o cabeçalho
     * estreito da tabela (6 colunas). Replica abreviações comuns do modelo Excel.
     */
    protected function abreviarDisciplina(string $nome): string
    {
        $nome = trim($nome);
        $up = mb_strtoupper($nome);

        // Abreviações específicas (mapeadas a partir do modelo Excel original)
        $mapa = [
            'LÍNGUA PORTUGUESA' => 'PORTUGUESA',
            'LINGUA PORTUGUESA' => 'PORTUGUESA',
            'PORTUGUÊS' => 'PORTUGUESA',
            'INGLÊS' => 'INGLÊS',
            'EDUCAÇÃO FÍSICA' => 'ED. FÍSICA',
            'MATEMÁTICA' => 'MATEMÁT.',
            'FÍSICA' => 'FÍSICA',
            'QUÍMICA' => 'QUÍMICA',
            'PAI' => 'PAI',
            'EMPREENDEDORISMO' => 'EMPREEND.',
            'PROJECTO TECNOLÓGICO' => 'P. T.',
            'PROJETO TECNOLÓGICO' => 'P. T.',
            'TECNOLOGIAS DE INFORMAÇÃO E COMUNICAÇÃO' => 'TIC',
            'TECNOLOGIA DE INFORMAÇÃO E COMUNICAÇÃO' => 'TIC',
        ];

        if (isset($mapa[$up])) {
            return $mapa[$up];
        }

        // Se cabe no espaço disponível (~12 chars), mantém o nome completo
        if (mb_strlen($up) <= 12) {
            return $up;
        }

        // Fallback: usa o código da disciplina se existir, senão trunca
        return mb_strtoupper(mb_substr($up, 0, 11)) . '.';
    }
}
