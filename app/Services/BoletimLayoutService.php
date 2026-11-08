<?php

namespace App\Services;

/**
 * Modelos e cores do boletim PDF — personalização pela secretaria.
 */
class BoletimLayoutService
{
    public const MODELO_CLASSICO = 'classico';

    public const MODELO_MODERNO = 'moderno';

    public const MODELO_FORMAL = 'formal';

    /**
     * @var array<string, array{nome: string, descricao: string, preview: string}>
     */
    public const MODELOS = [
        self::MODELO_CLASSICO => [
            'nome' => 'Clássico',
            'descricao' => 'Layout actual: cabeçalho limpo, tabela clara — ideal para uso geral.',
            'preview' => 'linear-gradient(180deg, #e8f1fb 0%, #ffffff 40%)',
        ],
        self::MODELO_MODERNO => [
            'nome' => 'Moderno',
            'descricao' => 'Faixa colorida no topo, tipografia mais marcada e cabeçalho da tabela na cor da escola.',
            'preview' => 'linear-gradient(135deg, #0d9488 0%, #134e4a 55%, #f8fafc 55%)',
        ],
        self::MODELO_FORMAL => [
            'nome' => 'Institucional',
            'descricao' => 'Moldura dupla e aspecto oficial — adequado a impressões solenes e arquivo.',
            'preview' => 'linear-gradient(180deg, #1e293b 0 18%, #f8fafc 18%)',
        ],
    ];

    public function __construct(
        private readonly ConfiguracaoService $configuracao,
    ) {}

    /**
     * @return array{modelo: string, cor_primaria: string, cor_secundaria: string, cor_tabela: string}
     */
    public function getConfig(): array
    {
        $pdf = $this->configuracao->getPdfConfig();
        $modelo = (string) $this->configuracao->get('boletim_modelo', self::MODELO_CLASSICO);
        if (! array_key_exists($modelo, self::MODELOS)) {
            $modelo = self::MODELO_CLASSICO;
        }

        return [
            'modelo' => $modelo,
            'cor_primaria' => (string) $this->configuracao->get('boletim_cor_primaria', $pdf['colors']['primary']),
            'cor_secundaria' => (string) $this->configuracao->get('boletim_cor_secundaria', '#134e4a'),
            'cor_tabela' => (string) $this->configuracao->get('boletim_cor_tabela', $pdf['colors']['table_header']),
        ];
    }

    /**
     * @param  array{modelo?: string, cor_primaria?: string, cor_secundaria?: string, cor_tabela?: string}  $dados
     */
    public function guardar(array $dados): void
    {
        $modelo = $dados['modelo'] ?? self::MODELO_CLASSICO;
        if (! array_key_exists($modelo, self::MODELOS)) {
            $modelo = self::MODELO_CLASSICO;
        }

        $this->configuracao->set('boletim_modelo', $modelo, 'Modelo de boletim PDF da escola');
        $this->configuracao->set('boletim_cor_primaria', $this->normalizarCor($dados['cor_primaria'] ?? '#0066cc'), 'Cor primária do boletim');
        $this->configuracao->set('boletim_cor_secundaria', $this->normalizarCor($dados['cor_secundaria'] ?? '#134e4a'), 'Cor secundária do boletim');
        $this->configuracao->set('boletim_cor_tabela', $this->normalizarCor($dados['cor_tabela'] ?? '#d0d0d0'), 'Cor do cabeçalho da tabela do boletim');
    }

    /**
     * PDF config com cores do boletim (não altera PDFs globais).
     *
     * @return array<string, mixed>
     */
    public function pdfConfigParaBoletim(): array
    {
        $pdf = $this->configuracao->getPdfConfig();
        $cfg = $this->getConfig();
        $pdf['colors']['primary'] = $cfg['cor_primaria'];
        $pdf['colors']['table_header'] = $cfg['cor_tabela'];
        $pdf['colors']['secondary'] = $cfg['cor_secundaria'];

        return $pdf;
    }

    /**
     * Dados a injectar nas views do boletim.
     *
     * @return array{pdfConfig: array<string, mixed>, boletim_modelo: string, boletim_cores: array<string, string>}
     */
    public function dadosParaView(): array
    {
        $cfg = $this->getConfig();

        return [
            'pdfConfig' => $this->pdfConfigParaBoletim(),
            'boletim_modelo' => $cfg['modelo'],
            'boletim_cores' => [
                'primaria' => $cfg['cor_primaria'],
                'secundaria' => $cfg['cor_secundaria'],
                'tabela' => $cfg['cor_tabela'],
            ],
        ];
    }

    public function viewTrimestre(): string
    {
        return 'exports.pdf.boletim-trimestre';
    }

    public function viewAnual(): string
    {
        return 'exports.pdf.boletim';
    }

    private function normalizarCor(string $cor): string
    {
        $cor = trim($cor);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $cor)) {
            return strtolower($cor);
        }

        return '#0066cc';
    }
}
