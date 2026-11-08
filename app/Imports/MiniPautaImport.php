<?php

namespace App\Imports;

use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Nota;
use App\Models\User;
use App\Services\NotaService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Importa notas a partir do Excel oficial da mini-pauta (MiniPautaExport).
 * Cabeçalho na linha 13; dados dos alunos a partir da linha 14.
 */
class MiniPautaImport
{
    private const LINHA_CABECALHO = 13;

    private const LINHA_DADOS = 14;

    public int $criadas = 0;

    public int $atualizadas = 0;

    public int $bloqueadas = 0;

    /** @var array<int, string> */
    public array $erros = [];

    public function __construct(
        protected int $turmaId,
        protected int $disciplinaId,
        protected int $trimestre,
        protected int $anoLectivoId,
        protected User $professor,
        protected NotaService $notaService,
        /** @var array<int, array{key: string, header: string}> */
        protected array $colunasMeta,
        protected string $regimeId,
    ) {}

    public function processar(string $caminhoFicheiro): void
    {
        $spreadsheet = IOFactory::load($caminhoFicheiro);
        $sheet = $spreadsheet->getActiveSheet();

        $mapaColunas = $this->construirMapaColunas($sheet);
        if ($mapaColunas === []) {
            if ($this->erros === []) {
                $this->erros[] = 'Cabeçalho da mini-pauta não reconhecido (linha 13). Descarregue o modelo oficial do sistema.';
            }

            return;
        }

        $camposImportaveis = $this->camposImportaveis();
        $alunosPorOrdem = Aluno::where('turma_id', $this->turmaId)
            ->activo()
            ->get()
            ->keyBy('numero_ordem');

        $linha = self::LINHA_DADOS;
        $ultimaLinha = $sheet->getHighestRow();

        while ($linha <= $ultimaLinha) {
            $cellA = trim((string) $sheet->getCell("A{$linha}")->getCalculatedValue());
            $cellB = trim((string) $sheet->getCell("B{$linha}")->getCalculatedValue());

            if ($cellA === '' && $cellB === '') {
                break;
            }

            if ($this->eLinhaRodape($cellA, $cellB)) {
                break;
            }

            if (! is_numeric($cellA)) {
                $linha++;
                continue;
            }

            $numeroOrdem = (int) $cellA;
            $aluno = $alunosPorOrdem->get($numeroOrdem);

            if (! $aluno) {
                $this->erros[] = "Linha {$linha}: aluno N.º {$numeroOrdem} não encontrado na turma.";
                $linha++;
                continue;
            }

            foreach ($mapaColunas as $colLetter => $key) {
                if (! isset($camposImportaveis[$key])) {
                    continue;
                }

                [$tipo, $trimestreNota] = $camposImportaveis[$key];
                $raw = $sheet->getCell("{$colLetter}{$linha}")->getCalculatedValue();

                if ($raw === null || $raw === '') {
                    continue;
                }

                if (is_string($raw) && $this->eTextoSituacao($raw)) {
                    continue;
                }

                try {
                    $valor = $this->formatarValor($raw);
                } catch (\InvalidArgumentException $e) {
                    $header = collect($this->colunasMeta)->firstWhere('key', $key)['header'] ?? strtoupper($key);
                    $this->erros[] = "Linha {$linha} ({$header}): {$e->getMessage()}";
                    continue;
                }

                if ($valor < 0 || $valor > 20) {
                    $header = collect($this->colunasMeta)->firstWhere('key', $key)['header'] ?? strtoupper($key);
                    $this->erros[] = "Linha {$linha} ({$header}): nota deve estar entre 0 e 20 (recebido: {$valor}).";
                    continue;
                }

                $accao = $this->notaService->gravarOuRemoverLancamento(
                    $valor,
                    $aluno->id,
                    $this->disciplinaId,
                    $this->turmaId,
                    $tipo,
                    $trimestreNota,
                    $this->professor,
                    $this->anoLectivoId,
                );

                match ($accao) {
                    'criada' => $this->criadas++,
                    'actualizada' => $this->atualizadas++,
                    'bloqueada' => $this->bloqueadas++,
                    default => null,
                };
            }

            $linha++;
        }
    }

    /**
     * @return array<string, string> coluna Excel => key da nota
     */
    protected function construirMapaColunas(Worksheet $sheet): array
    {
        $mapa = [];
        $colIndex = 3;

        foreach ($this->colunasMeta as $coluna) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $headerCell = trim((string) $sheet->getCell("{$colLetter}" . self::LINHA_CABECALHO)->getCalculatedValue());
            $expected = $coluna['header'];

            if ($headerCell === '') {
                $this->erros[] = "Coluna {$colLetter}: cabeçalho em falta (esperado «{$expected}»).";
                $colIndex++;
                continue;
            }

            if (! $this->headerCorresponde($headerCell, $expected, $coluna['key'])) {
                $this->erros[] = "Coluna {$colLetter}: esperado «{$expected}», encontrado «{$headerCell}». Não altere a estrutura do modelo.";
            }

            $mapa[$colLetter] = $coluna['key'];
            $colIndex++;
        }

        return $mapa;
    }

    /**
     * @return array<string, array{0: string, 1: ?int}>
     */
    protected function camposImportaveis(): array
    {
        if ($this->trimestre === 3) {
            if ($this->regimeId === Curso::REGIME_ADMINISTRACAO) {
                return [
                    'pg' => [Nota::TIPO_PG, null],
                ];
            }

            return [
                'mac' => [Nota::TIPO_MAC, 3],
                'pp' => [Nota::TIPO_PP, 3],
                'pg' => [Nota::TIPO_PG, null],
            ];
        }

        return [
            'mac' => [Nota::TIPO_MAC, $this->trimestre],
            'pp' => [Nota::TIPO_PP, $this->trimestre],
            'pt' => [Nota::TIPO_PT, $this->trimestre],
        ];
    }

    protected function headerCorresponde(string $actual, string $expected, string $key): bool
    {
        if ($this->normalizarHeader($actual) === $this->normalizarHeader($expected)) {
            return true;
        }

        $norm = $this->normalizarHeader($actual);

        return str_contains($norm, strtoupper($key))
            || str_contains($norm, $this->normalizarHeader($expected));
    }

    protected function normalizarHeader(string $header): string
    {
        $header = preg_replace('/\s+/', '', mb_strtoupper($header)) ?? '';

        return str_replace(['º', '°', '-', '_'], '', $header);
    }

    protected function eLinhaRodape(string $cellA, string $cellB): bool
    {
        foreach ([$cellA, $cellB] as $valor) {
            if (stripos($valor, 'ESTATÍSTICAS') !== false || stripos($valor, 'ESTATISTICAS') !== false) {
                return true;
            }
        }

        return false;
    }

    protected function eTextoSituacao(string $valor): bool
    {
        return in_array(mb_strtolower(trim($valor)), [
            'pendente', 'negativa', 'positiva', 'desistente', 'positivo', 'negativo',
        ], true);
    }

    protected function formatarValor(mixed $valor): float
    {
        if (is_numeric($valor)) {
            return (float) $valor;
        }

        $valor = str_replace(',', '.', trim((string) $valor));

        if ($valor === '' || ! is_numeric($valor)) {
            throw new \InvalidArgumentException('valor inválido');
        }

        return (float) $valor;
    }
}
