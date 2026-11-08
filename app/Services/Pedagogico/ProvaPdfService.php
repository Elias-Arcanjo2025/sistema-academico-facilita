<?php

namespace App\Services\Pedagogico;

use App\Models\Nota;
use App\Models\Pedagogico\PedProva;
use App\Services\ConfiguracaoService;
use Illuminate\Support\Str;

class ProvaPdfService
{
    public function __construct(
        protected ConfiguracaoService $configuracaoService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dadosView(PedProva $prova): array
    {
        $prova->loadMissing([
            'planificacao.professor',
            'planificacao.anoLectivo',
            'turma.classe',
            'turma.curso',
            'turma.turno',
            'disciplina',
        ]);

        $instituicao = $this->configuracaoService->getInstituicao();
        $pdfConfig = $this->configuracaoService->getPdfConfig();
        $compact = (bool) config('pedagogico.prova_pdf.uma_pagina', true);
        $margensProva = config('pedagogico.prova_pdf.margins', []);

        $pdfConfig['margins'] = $compact
            ? [
                'top' => (int) ($margensProva['top'] ?? 10),
                'right' => (int) ($margensProva['right'] ?? 12),
                'bottom' => (int) ($margensProva['bottom'] ?? 10),
                'left' => (int) ($margensProva['left'] ?? 14),
            ]
            : [
                'top' => max(18, (int) ($pdfConfig['margins']['top'] ?? 15)),
                'right' => max(16, (int) ($pdfConfig['margins']['right'] ?? 15)),
                'bottom' => max(18, (int) ($pdfConfig['margins']['bottom'] ?? 15)),
                'left' => max(20, (int) ($pdfConfig['margins']['left'] ?? 15)),
            ];

        if ($compact) {
            $pdfConfig['fonts']['sizes']['body'] = (int) config('pedagogico.prova_pdf.font_body', 8);
            $pdfConfig['fonts']['sizes']['header'] = (int) config('pedagogico.prova_pdf.font_header', 9);
            $pdfConfig['logo']['width'] = (int) config('pedagogico.prova_pdf.logo_width', 40);
        }

        return [
            'prova' => $prova,
            'instituicao' => $instituicao,
            'pdfConfig' => $pdfConfig,
            'compacto' => $compact,
            'logoBase64' => $this->resolveLogoBase64($instituicao['logo'] ?? null),
            'cabecalho' => $this->montarCabecalho($prova),
            'notasPadrao' => config('pedagogico.prova_notas_padrao', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function montarCabecalho(PedProva $prova): array
    {
        $turma = $prova->turma;
        $planificacao = $prova->planificacao;
        $disciplina = $prova->disciplina;
        $conteudo = $prova->conteudo_json ?? [];

        $sigla = Str::upper(trim((string) ($disciplina?->codigo ?: $this->siglaDisciplina($disciplina?->nome))));
        $trimestre = $planificacao?->trimestre;

        $classeTurno = collect([
            $turma?->classe?->nome,
            $turma?->turno?->nome,
        ])->filter()->implode('/');

        return [
            'titulo' => $conteudo['titulo'] ?? $this->tituloPorTipo($prova->tipo, $sigla, $trimestre),
            'classe_turno' => $classeTurno,
            'duracao' => (int) $prova->tempo_minutos,
            'curso' => Str::upper((string) ($turma?->curso?->nome ?? '')),
            'professor' => $planificacao?->professor?->name ?? '',
            'turma' => $turma?->nome ?? $turma?->codigo ?? '',
            'disciplina' => $disciplina?->nome ?? '',
            'disciplina_sigla' => $sigla,
            'trimestre' => $trimestre,
            'ano_lectivo' => $planificacao?->anoLectivo?->nome ?? $turma?->ano_lectivo ?? '',
            'data_linha' => '____/____/________',
            'instrucao_resolucao' => $conteudo['instrucoes_resolucao']
                ?? 'Resolve acertadamente as questões, depois de lida e compreendida:',
        ];
    }

    protected function tituloPorTipo(string $tipo, string $sigla, ?int $trimestre): string
    {
        return match ($tipo) {
            Nota::TIPO_PT => $trimestre
                ? sprintf('PROVA FINAL DO %dº TRIMESTRE DE %s', $trimestre, $sigla)
                : sprintf('PROVA TRIMESTRAL DE %s', $sigla),
            Nota::TIPO_PP => sprintf('PROVA PARCIAL DE %s', $sigla),
            Nota::TIPO_MAC => sprintf('PROVA DE MAC DE %s', $sigla),
            default => sprintf('PROVA DE %s DE %s', $tipo, $sigla),
        };
    }

    protected function siglaDisciplina(?string $nome): string
    {
        if ($nome === null || trim($nome) === '') {
            return 'DISC';
        }

        $palavras = preg_split('/\s+/u', trim($nome)) ?: [];
        if (count($palavras) === 1) {
            return Str::upper(Str::limit($palavras[0], 6, ''));
        }

        return Str::upper(collect($palavras)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
    }

    protected function resolveLogoBase64(?string $logoRelPath): string
    {
        if (! $logoRelPath) {
            return '';
        }

        $fullPath = storage_path('app/public/'.$logoRelPath);
        if (! is_file($fullPath)) {
            return '';
        }

        $mime = mime_content_type($fullPath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($fullPath));
    }
}
