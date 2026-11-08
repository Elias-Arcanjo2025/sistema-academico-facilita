<?php

namespace App\Support;

/**
 * Fórmulas Excel para RESULTADO e OBSERVAÇÃO na pauta T3 (10.ª–12.ª).
 *
 * 10.ª–11.ª:
 * - TRANSITA (0) | TRANSITA/C DEF (só contínuas) | RECUPERAÇÃO (terminais) | N/TRANSITA
 *
 * 12.ª (configurável):
 * - APTO (0) | RECURSO (1–N) | N/APTO (>N)
 *
 * Contínuas → CA | Terminais → CFD
 * A partir da 11.ª: CFD de terminais de anos anteriores conta no resultado.
 */
class PautaExcelFormulasTransicao
{
    /**
     * @param  array<int, array{pg:?string, ca:?string, cfd:?string, recuperacao:?string, recurso:?string}>  $celulasPorDisciplina
     * @param  array<int, array{codigo:string, terminal?:bool, usa_cfd?:bool, classe_fim?:int}>  $metaDisciplinas
     * @param  array<int, array{col:string, codigo:string}>  $celulasCfdTerminaisAnteriores
     */
    public function __construct(
        protected array $celulasPorDisciplina,
        protected array $metaDisciplinas,
        protected array $celulasCfdTerminaisAnteriores = [],
        protected int $classeNumero = 10,
        protected ?int $maxNegativasRecurso12 = null,
        protected ?int $maxNegativasRecuperacao = null,
    ) {}

    public function formulaResultado(int $row): string
    {
        if ($this->itensNegativosTodos($row) === [] && $this->expressaoCompleto($row) === null) {
            return '=""';
        }

        return $this->formulaResultadoComSomas(
            $this->expressaoSomaNegativas($row, false),
            $this->expressaoSomaNegativas($row, true),
        );
    }

    /**
     * RESULTADO a partir de expressões de soma já calculadas (ex.: colunas auxiliares).
     */
    public function formulaResultadoComSomas(string $somaCont, string $somaTerm): string
    {
        $somaTotal = $this->expressaoSomaTotal($somaCont, $somaTerm);

        if ($this->classeNumero === 12) {
            $maxRec = PautaResultadoTransicao::maxNegativasRecurso12($this->maxNegativasRecurso12);

            return '=IF('.$somaTotal.'=0,"'.PautaResultadoTransicao::RESULTADO_APTO.'",'
                .'IF('.$somaTotal.'<='.$maxRec.',"'.PautaResultadoTransicao::RESULTADO_RECURSO.'",'
                .'"'.PautaResultadoTransicao::RESULTADO_N_APTO.'"'
                .'))';
        }

        $maxCont = PautaResultadoTransicao::MAX_NEGATIVAS_CONTINUIDADE;
        $maxTotal = PautaResultadoTransicao::maxNegativasRecuperacao($this->maxNegativasRecuperacao);

        return '=IF('.$somaCont.'>='.$maxCont.',"'.PautaResultadoTransicao::RESULTADO_N_TRANSITA.'",'
            .'IF('.$somaTotal.'>'.$maxTotal.',"'.PautaResultadoTransicao::RESULTADO_N_TRANSITA.'",'
            .'IF('.$somaTotal.'=0,"'.PautaResultadoTransicao::RESULTADO_TRANSITA.'",'
            .'IF('.$somaTerm.'>=1,"'.PautaResultadoTransicao::RESULTADO_RECUPERACAO.'",'
            .'"'.PautaResultadoTransicao::RESULTADO_TRANSITA_COM_DEFICIENCIA.'"'
            .'))))';
    }

    /**
     * Negativas contínuas e terminais (inclui carry-over) para colunas auxiliares Excel.
     *
     * @return array{continuas: list<array{neg:string, cond:string, codigo:string}>, terminais: list<array{neg:string, cond:string, codigo:string}>}
     */
    public function negatividades(int $row): array
    {
        return [
            'continuas' => $this->itensNegativosPorTipo($row, false),
            'terminais' => $this->itensNegativosTerminais($row),
        ];
    }

    /**
     * OBS compacta a partir de listas já montadas (colunas auxiliares) + coluna RESULTADO.
     */
    public function formulaObservacaoComListas(
        int $row,
        string $colResultado,
        string $listaDef,
        string $listaRec,
        string $listaTodas,
    ): string {
        $res = $colResultado.$row;

        if ($this->classeNumero === 12) {
            $apto = PautaResultadoTransicao::RESULTADO_APTO;
            $recurso = PautaResultadoTransicao::RESULTADO_RECURSO;

            return '=IF(OR('.$res.'="",'.$res.'="'.$apto.'"),"",'
                .'IF('.$res.'="'.$recurso.'","Rec.: "&'.$listaTodas.','
                .$listaTodas
                .'))';
        }

        $comDef = PautaResultadoTransicao::RESULTADO_TRANSITA_COM_DEFICIENCIA;
        $recuperacao = PautaResultadoTransicao::RESULTADO_RECUPERACAO;
        $corpo = 'IF(('.$listaDef.')="","","Def.: "&('.$listaDef.'))'
            .'&IF(AND(('.$listaDef.')<>"",('.$listaRec.')<>"")," | ","")'
            .'&IF(('.$listaRec.')="","","Rec.: "&('.$listaRec.'))';

        // OBS com TRANSITA/C DEF ou RECUPERAÇÃO (terminais)
        return '=IF(OR('.$res.'="'.$comDef.'",'.$res.'="'.$recuperacao.'"),'.$corpo.',"")';
    }

    /**
     * 10.ª/11.ª: Def.: / Rec.: por tipo.
     * 12.ª: Rec.: (RECURSO) ou lista completa (N/APTO).
     *
     * @param  string|null  $colResultado  Coluna Excel do RESULTADO (ex.: "CM") —
     *                                     quando indicada, a OBS fica compacta (referencia o resultado)
     *                                     e cabe no limite de 8192 caracteres do Excel.
     */
    public function formulaObservacao(int $row, ?string $colResultado = null): string
    {
        if ($this->expressaoCompleto($row) === null) {
            return '=""';
        }

        if ($this->classeNumero === 12) {
            return $this->formulaObservacao12($row, $colResultado);
        }

        return $this->formulaObservacaoTransicao($row, $colResultado);
    }

    private function formulaObservacao12(int $row, ?string $colResultado): string
    {
        $listaTodas = $this->expressaoListaCodigos($this->itensNegativosTodos($row));

        if ($colResultado !== null) {
            $res = $colResultado.$row;
            $apto = PautaResultadoTransicao::RESULTADO_APTO;
            $recurso = PautaResultadoTransicao::RESULTADO_RECURSO;

            return '=IF(OR('.$res.'="",'.$res.'="'.$apto.'"),"",'
                .'IF('.$res.'="'.$recurso.'","Rec.: "&'.$listaTodas.','
                .$listaTodas
                .'))';
        }

        $somaCont = $this->expressaoSomaNegativas($row, false);
        $somaTerm = $this->expressaoSomaNegativas($row, true);
        $somaTotal = $this->expressaoSomaTotal($somaCont, $somaTerm);
        $maxRec = PautaResultadoTransicao::maxNegativasRecurso12($this->maxNegativasRecurso12);

        return '=IF('.$somaTotal.'=0,"",'
            .'IF('.$somaTotal.'<='.$maxRec.','
            .'"Rec.: "&'.$listaTodas.','
            .$listaTodas
            .'))';
    }

    private function formulaObservacaoTransicao(int $row, ?string $colResultado): string
    {
        $listaDef = $this->expressaoListaCodigos($this->itensNegativosPorTipo($row, false));
        $listaRec = $this->expressaoListaCodigos($this->itensNegativosTerminais($row));

        if ($colResultado !== null) {
            return $this->formulaObservacaoComListas($row, $colResultado, $listaDef, $listaRec, '""');
        }

        $somaCont = $this->expressaoSomaNegativas($row, false);
        $somaTerm = $this->expressaoSomaNegativas($row, true);
        $somaTotal = $this->expressaoSomaTotal($somaCont, $somaTerm);

        return '=IF('.$somaTotal.'=0,"",'
            .'IF('.$somaCont.'>=1,"Def.: "&'.$listaDef.',"")'
            .'&IF(AND('.$somaCont.'>=1,'.$somaTerm.'>=1)," | ","")'
            .'&IF('.$somaTerm.'>=1,"Rec.: "&'.$listaRec.',"")'
            .')';
    }

    /**
     * Contínuas + terminais (ano actual e carry-over).
     *
     * @return array<int, array{neg:string, cond:string, codigo:string}>
     */
    private function itensNegativosTodos(int $row): array
    {
        return array_merge(
            $this->itensNegativosPorTipo($row, false),
            $this->itensNegativosTerminais($row),
        );
    }

    private function expressaoSomaNegativas(int $row, bool $terminal): string
    {
        $exprs = array_map(
            fn ($item) => $item['neg'],
            $terminal
                ? $this->itensNegativosTerminais($row)
                : $this->itensNegativosPorTipo($row, false),
        );

        if ($exprs === []) {
            return '0';
        }

        return '('.implode('+', $exprs).')';
    }

    private function expressaoSomaTotal(string $somaCont, string $somaTerm): string
    {
        return '(('.$somaCont.')+('.$somaTerm.'))';
    }

    /**
     * Terminais do ano actual + carry-over (11.ª/12.ª).
     *
     * @return array<int, array{neg:string, cond:string, codigo:string}>
     */
    private function itensNegativosTerminais(int $row): array
    {
        $itens = $this->itensNegativosPorTipo($row, true);

        if (! $this->incluirTerminaisAnteriores()) {
            return $itens;
        }

        foreach ($this->celulasCfdTerminaisAnteriores as $cfd) {
            $col = $cfd['col'];
            $cond = "AND(ISNUMBER({$col}{$row}),{$col}{$row}<10)";
            $itens[] = [
                'neg' => $this->expressaoNegativa($col, $row),
                'cond' => $cond,
                'codigo' => $this->escapeExcel($cfd['codigo']),
            ];
        }

        return $itens;
    }

    /**
     * @return array<int, array{neg:string, cond:string, codigo:string}>
     */
    private function itensNegativosPorTipo(int $row, bool $terminal): array
    {
        $itens = [];

        foreach ($this->celulasPorDisciplina as $discId => $cells) {
            $meta = $this->metaDisciplinas[$discId] ?? null;
            if ($meta === null || $this->metaTerminal($meta) !== $terminal) {
                continue;
            }

            $colNota = $terminal
                ? $this->colunaNotaTerminal($cells, $meta)
                : ($cells['ca'] ?? null);

            if ($colNota === null) {
                continue;
            }

            $codigo = $this->escapeExcel($meta['codigo'] ?? '');
            if ($codigo === '') {
                continue;
            }

            // RECUP./RECURSO (quando lançado) substitui CA/CFD — contínuas e terminais
            $colExame = $this->colunaExameEspecial($cells);

            if ($terminal && ! empty($meta['usa_cfd']) && ! empty($cells['cfd']) && ! empty($cells['ca'])) {
                $cond = $this->condicaoNegativaComExame($cells, $meta, $row);
                $neg = $this->expressaoNegativaComExame($cells, $meta, $row);
            } else {
                $cond = $this->condicaoNegativaComExameSimples($colExame, $colNota, $row);
                $neg = $this->expressaoNegativaComExameSimples($colExame, $colNota, $row);
            }

            $itens[] = [
                'neg' => $neg,
                'cond' => $cond,
                'codigo' => $codigo,
            ];
        }

        return $itens;
    }

    private function incluirTerminaisAnteriores(): bool
    {
        return $this->classeNumero >= 11;
    }

    /**
     * @param  array{terminal?:bool, usa_cfd?:bool, classe_fim?:int}  $meta
     */
    private function metaTerminal(array $meta): bool
    {
        if (! empty($meta['terminal'])) {
            return true;
        }

        $fim = (int) ($meta['classe_fim'] ?? 0);

        return $fim > 0 && $fim === $this->classeNumero;
    }

    /**
     * @param  array{pg:?string, ca:?string, cfd:?string, recuperacao:?string, recurso:?string}  $cells
     */
    private function colunaExameEspecial(array $cells): ?string
    {
        foreach (['recurso', 'recuperacao'] as $key) {
            $col = $cells[$key] ?? null;
            if (is_string($col) && $col !== '') {
                return $col;
            }
        }

        return null;
    }

    /**
     * @param  array{pg:?string, ca:?string, cfd:?string, recuperacao:?string, recurso:?string}  $cells
     * @param  array{usa_cfd?:bool}  $meta
     */
    private function expressaoNegativaComExame(array $cells, array $meta, int $row): string
    {
        $colExame = $this->colunaExameEspecial($cells);
        $colNota = $this->colunaNotaTerminal($cells, $meta);
        if ($colNota === null) {
            return '0';
        }

        if ($colExame !== null) {
            $ex = $colExame.$row;
            $baseNeg = $this->expressaoNegativaTerminal($cells['cfd'], $cells['ca'], $row);

            return 'IF(ISNUMBER('.$ex.'),IF('.$ex.'<10,1,0),'.$baseNeg.')';
        }

        if (! empty($meta['usa_cfd']) && isset($cells['cfd'], $cells['ca'])) {
            return $this->expressaoNegativaTerminal($cells['cfd'], $cells['ca'], $row);
        }

        return $this->expressaoNegativa($colNota, $row);
    }

    /**
     * @param  array{pg:?string, ca:?string, cfd:?string, recuperacao:?string, recurso:?string}  $cells
     * @param  array{usa_cfd?:bool}  $meta
     */
    private function condicaoNegativaComExame(array $cells, array $meta, int $row): string
    {
        $colExame = $this->colunaExameEspecial($cells);
        if ($colExame !== null) {
            $ex = $colExame.$row;
            $baseCond = $this->condicaoNegativaTerminal($cells['cfd'], $cells['ca'], $row);

            return 'IF(ISNUMBER('.$ex.'),'.$ex.'<10,'.$baseCond.')';
        }

        return $this->condicaoNegativaTerminal($cells['cfd'], $cells['ca'], $row);
    }

    /**
     * Junta células auxiliares com "COD," / "" numa lista "COD1, COD2".
     *
     * @param  list<string>  $celulas  Ex.: ["DL15","DN15"]
     */
    public function expressaoListaDeCelulas(array $celulas): string
    {
        if ($celulas === []) {
            return '""';
        }

        if (count($celulas) === 1) {
            $c = $celulas[0];

            return 'IF('.$c.'="","",IF(RIGHT('.$c.',1)=",",LEFT('.$c.',LEN('.$c.')-1),'.$c.'))';
        }

        $concat = '('.implode('&', $celulas).')';

        return 'IF('.$concat.'="","",LEFT('.$concat.',LEN('.$concat.')-1))';
    }

    /**
     * @param  list<string>  $celulas
     */
    public function expressaoSomaDeCelulas(array $celulas): string
    {
        if ($celulas === []) {
            return '0';
        }

        return '('.implode('+', $celulas).')';
    }

    private function expressaoNegativaComExameSimples(?string $colExame, string $colNota, int $row): string
    {
        if ($colExame !== null) {
            $ex = $colExame.$row;
            $nota = $colNota.$row;

            return 'IF(ISNUMBER('.$ex.'),IF('.$ex.'<10,1,0),IF(AND(ISNUMBER('.$nota.'),'.$nota.'<10),1,0))';
        }

        return $this->expressaoNegativa($colNota, $row);
    }

    private function condicaoNegativaComExameSimples(?string $colExame, string $colNota, int $row): string
    {
        if ($colExame !== null) {
            $ex = $colExame.$row;
            $nota = $colNota.$row;

            return 'IF(ISNUMBER('.$ex.'),'.$ex.'<10,AND(ISNUMBER('.$nota.'),'.$nota.'<10))';
        }

        return "AND(ISNUMBER({$colNota}{$row}),{$colNota}{$row}<10)";
    }

    /**
     * @param  array{pg:?string, ca:?string, cfd:?string, recuperacao:?string, recurso:?string}  $cells
     * @param  array{usa_cfd?:bool}  $meta
     */
    private function colunaNotaTerminal(array $cells, array $meta): ?string
    {
        if (! empty($meta['usa_cfd']) && isset($cells['cfd'])) {
            return $cells['cfd'];
        }

        return $cells['ca'] ?? null;
    }

    /**
     * Lista compacta de códigos separados por vírgula (sem TEXTJOIN — compatível Excel 2016).
     *
     * @param  array<int, array{cond:string, codigo:string}>  $itens
     */
    private function expressaoListaCodigos(array $itens): string
    {
        if ($itens === []) {
            return '""';
        }

        if (count($itens) === 1) {
            return 'IF('.$itens[0]['cond'].',"'.$itens[0]['codigo'].'","")';
        }

        $partes = array_map(
            fn (array $t) => 'IF('.$t['cond'].',"'.$t['codigo'].',","")',
            $itens,
        );
        $concat = '('.implode('&', $partes).')';

        return 'IF('.$concat.'="","",LEFT('.$concat.',LEN('.$concat.')-1))';
    }

    private function expressaoCompleto(int $row): ?string
    {
        $partes = [];

        foreach ($this->celulasPorDisciplina as $discId => $cells) {
            $meta = $this->metaDisciplinas[$discId] ?? null;
            if ($meta === null) {
                continue;
            }

            $colNota = $this->metaTerminal($meta)
                ? $this->colunaNotaTerminal($cells, $meta)
                : ($cells['ca'] ?? null);

            if ($colNota === null) {
                continue;
            }

            if ($this->metaTerminal($meta) && ! empty($meta['usa_cfd']) && isset($cells['cfd'], $cells['ca'])) {
                $partes[] = $this->expressaoCompletoTerminal($cells['cfd'], $cells['ca'], $row);
            } else {
                $partes[] = 'ISNUMBER('.$colNota.$row.')';
            }
        }

        if (\App\Support\PautaRegrasTransicaoMinisterio::incluirCfdTerminaisAnteriores($this->classeNumero)) {
            foreach ($this->celulasCfdTerminaisAnteriores as $cfd) {
                $partes[] = 'ISNUMBER('.$cfd['col'].$row.')';
            }
        }

        if ($partes === []) {
            return null;
        }

        return 'AND('.implode(',', $partes).')';
    }

    private function expressaoCompletoTerminal(string $colCfd, string $colCa, int $row): string
    {
        return 'OR(ISNUMBER('.$colCfd.$row.'),ISNUMBER('.$colCa.$row.'))';
    }

    private function condicaoNegativaTerminal(string $colCfd, string $colCa, int $row): string
    {
        $cfd = $colCfd.$row;
        $ca = $colCa.$row;

        return 'IF(ISNUMBER('.$cfd.'),'.$cfd.'<10,IF(ISNUMBER('.$ca.'),'.$ca.'<10,FALSE))';
    }

    private function expressaoNegativaTerminal(string $colCfd, string $colCa, int $row): string
    {
        $cfd = $colCfd.$row;
        $ca = $colCa.$row;

        return 'IF(ISNUMBER('.$cfd.'),IF('.$cfd.'<10,1,0),IF(ISNUMBER('.$ca.'),IF('.$ca.'<10,1,0),0))';
    }

    private function expressaoNegativa(string $colNota, int $row): string
    {
        $cel = $colNota.$row;

        return 'IF(AND(ISNUMBER('.$cel.'),'.$cel.'<10),1,0)';
    }

    private function escapeExcel(string $value): string
    {
        return str_replace('"', '""', trim($value));
    }
}
