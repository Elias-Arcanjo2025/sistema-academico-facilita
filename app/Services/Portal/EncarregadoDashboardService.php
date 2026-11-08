<?php

namespace App\Services\Portal;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\Encarregado;
use App\Models\Nota;
use App\Models\Ocorrencia;
use App\Models\PresencaRegisto;
use App\Services\ConfiguracaoService;
use App\Services\PresencaService;
use App\Support\NotaLancamentoRules;
use Illuminate\Support\Collection;

class EncarregadoDashboardService
{
    /** @var list<string> */
    private array $cores = ['#0d9488', '#ea580c', '#2563eb', '#c026d3', '#ca8a04', '#dc2626'];

    public function __construct(
        private readonly AlunoPortalNotasService $notasService,
        private readonly AlunoPortalAcessoService $alunoAcesso,
        private readonly ConfiguracaoService $configuracao,
        private readonly PresencaService $presencaService,
    ) {}

    /**
     * @param  Collection<int, Aluno>  $filhos
     * @return array<string, mixed>
     */
    public function resumoFamilia(Encarregado $encarregado, Collection $filhos): array
    {
        $trimestre = $this->configuracao->getTrimestreActual();
        $notaMinima = $this->configuracao->getNotaMinimaAprovacao();
        $anoId = Configuracao::getAnoLectivoActualId();

        $cards = $filhos->values()->map(function (Aluno $filho, int $i) use ($encarregado, $trimestre, $notaMinima, $anoId) {
            return $this->cartaoFilho($encarregado, $filho, $i, $trimestre, $notaMinima, $anoId);
        });

        $comMedia = $cards->filter(fn (array $c) => $c['pode_notas'] && $c['media_mt'] !== null);

        $alertas = [];
        foreach ($cards as $card) {
            if ($card['negativas'] > 0) {
                $alertas[] = [
                    'tipo' => 'notas',
                    'texto' => $card['nome_curto'].' tem '.$card['negativas'].' negativa(s) (CFD).',
                    'url' => $card['url'],
                ];
            }
            if (($card['faltas_injustificadas'] ?? 0) >= 3) {
                $alertas[] = [
                    'tipo' => 'faltas',
                    'texto' => $card['nome_curto'].' acumula '.$card['faltas_injustificadas'].' faltas injustificadas.',
                    'url' => route('portal.encarregado.assiduidade', $card['id']),
                ];
            }
            if (($card['ocorrencias'] ?? 0) > 0) {
                $alertas[] = [
                    'tipo' => 'ocorrencia',
                    'texto' => $card['nome_curto'].' tem '.$card['ocorrencias'].' ocorrência(ões) este ano.',
                    'url' => route('portal.encarregado.ocorrencias', $card['id']),
                ];
            }
        }

        return [
            'trimestre' => $trimestre,
            'nota_minima' => $notaMinima,
            'ano_lectivo' => Configuracao::getAnoLectivoActual(),
            'filhos' => $cards,
            'totais' => [
                'educandos' => $cards->count(),
                'media_geral' => $comMedia->isEmpty()
                    ? null
                    : round($comMedia->avg('media_mt'), 1),
                'negativas' => $cards->sum('negativas'),
                'faltas' => $cards->sum(fn (array $c) => ($c['faltas_injustificadas'] ?? 0) + ($c['faltas_justificadas'] ?? 0)),
            ],
            'grafico_medias' => [
                'labels' => $comMedia->pluck('nome_curto')->values()->all(),
                'valores' => $comMedia->pluck('media_mt')->values()->all(),
                'cores' => $comMedia->pluck('cor')->values()->all(),
            ],
            'grafico_faltas' => [
                'labels' => $cards->pluck('nome_curto')->values()->all(),
                'injustificadas' => $cards->pluck('faltas_injustificadas')->values()->all(),
                'justificadas' => $cards->pluck('faltas_justificadas')->values()->all(),
                'atrasos' => $cards->pluck('atrasos')->values()->all(),
            ],
            'alertas' => array_slice($alertas, 0, 6),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resumoFilho(Encarregado $encarregado, Aluno $aluno): array
    {
        $trimestre = $this->configuracao->getTrimestreActual();
        $notaMinima = $this->configuracao->getNotaMinimaAprovacao();
        $anoId = Configuracao::getAnoLectivoActualId();
        $cor = $this->cores[0];

        $card = $this->cartaoFilho($encarregado, $aluno, 0, $trimestre, $notaMinima, $anoId);

        $notasLista = collect();
        $graficoMt = collect();
        $evolucao = ['labels' => ['1º', '2º', '3º'], 'valores' => [null, null, null]];
        $notasResumo = collect();

        if ($card['pode_notas']) {
            $filtros = $this->notasService->normalizarFiltros([
                'ano_lectivo_id' => $anoId,
                'periodo' => (string) $trimestre,
            ]);
            $notasLista = $this->notasService->consultar($aluno, $filtros);
            $notasResumo = $notasLista->groupBy(fn (Nota $n) => $n->disciplina?->nome ?? 'Disciplina');

            $graficoMt = $notasLista
                ->where('tipo', Nota::TIPO_MT)
                ->where('trimestre', $trimestre)
                ->map(fn (Nota $n) => [
                    'disciplina' => $n->disciplina?->nome ?? 'Disciplina',
                    'valor' => (float) $n->valor,
                ])
                ->values();

            $mtsAnoQuery = Nota::query()
                ->whereIn('aluno_id', $this->notasService->alunoIdsDoProcesso($aluno))
                ->where('ano_lectivo_id', $anoId)
                ->where('tipo', Nota::TIPO_MT)
                ->whereIn('trimestre', [1, 2, 3]);
            NotaLancamentoRules::aplicarVisibilidadeArquivo($mtsAnoQuery, $anoId);
            $mtsAno = $mtsAnoQuery->get()->groupBy('trimestre');

            foreach ([1, 2, 3] as $t) {
                $grupo = $mtsAno->get($t, collect());
                $evolucao['valores'][$t - 1] = $grupo->isEmpty()
                    ? null
                    : round((float) $grupo->avg('valor'), 1);
            }
        }

        $assiduidade = $this->presencaService->resumoAluno((int) $aluno->id, $anoId);
        $totais = $assiduidade['totais'];

        return [
            'card' => $card,
            'trimestre' => $trimestre,
            'nota_minima' => $notaMinima,
            'cor' => $cor,
            'notas' => $notasLista,
            'notas_resumo' => $notasResumo,
            'grafico_mt' => $graficoMt,
            'evolucao' => $evolucao,
            'assiduidade_chart' => [
                'labels' => ['Presente', 'Falta just.', 'Falta injust.', 'Atraso'],
                'valores' => [
                    (int) ($totais[PresencaRegisto::PRESENTE] ?? 0),
                    (int) ($totais[PresencaRegisto::FALTA_JUSTIFICADA] ?? 0),
                    (int) ($totais[PresencaRegisto::FALTA_INJUSTIFICADA] ?? 0),
                    (int) ($totais[PresencaRegisto::ATRASO] ?? 0),
                ],
                'cores' => ['#0d9488', '#ca8a04', '#dc2626', '#ea580c'],
            ],
            'resumo_assiduidade' => $assiduidade,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cartaoFilho(
        Encarregado $encarregado,
        Aluno $aluno,
        int $indice = 0,
        ?int $trimestre = null,
        ?float $notaMinima = null,
        ?int $anoId = null,
    ): array {
        $trimestre ??= $this->configuracao->getTrimestreActual();
        $notaMinima ??= $this->configuracao->getNotaMinimaAprovacao();
        $anoId ??= Configuracao::getAnoLectivoActualId();
        $pivot = $encarregado->alunos()->where('alunos.id', $aluno->id)->first();
        $podeVerNotasPivot = (bool) ($pivot?->pivot?->pode_ver_notas);
        $podeNotas = $podeVerNotasPivot && $this->alunoAcesso->podeConsultarNotas($aluno);

        $mediaMt = null;
        $disciplinas = 0;
        $negativas = 0;
        $mts = collect();

        if ($podeNotas && $anoId) {
            $alunoIds = $this->notasService->alunoIdsDoProcesso($aluno);

            $mtsQuery = Nota::query()
                ->whereIn('aluno_id', $alunoIds)
                ->where('ano_lectivo_id', $anoId)
                ->where('tipo', Nota::TIPO_MT)
                ->where('trimestre', $trimestre);
            NotaLancamentoRules::aplicarVisibilidadeArquivo($mtsQuery, $anoId);
            $mts = $mtsQuery->get();

            $disciplinas = $mts->pluck('disciplina_id')->unique()->count();
            $mediaMt = $mts->isEmpty() ? null : round((float) $mts->avg('valor'), 1);

            $negQuery = Nota::query()
                ->whereIn('aluno_id', $alunoIds)
                ->where('ano_lectivo_id', $anoId)
                ->where('tipo', Nota::TIPO_CFD)
                ->where('valor', '<', $notaMinima);
            NotaLancamentoRules::aplicarVisibilidadeArquivo($negQuery, $anoId);
            $negativas = $negQuery->count();
        }

        $presenca = $this->presencaService->resumoAluno((int) $aluno->id, $anoId);
        $totais = $presenca['totais'];

        $ocorrencias = Ocorrencia::query()
            ->where('aluno_id', $aluno->id)
            ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
            ->count();

        $partes = preg_split('/\s+/', trim($aluno->nome)) ?: [];
        $nomeCurto = count($partes) >= 2
            ? $partes[0].' '.$partes[count($partes) - 1]
            : ($partes[0] ?? $aluno->nome);

        $score = $this->scoreSaude($mediaMt, $negativas, (int) ($totais[PresencaRegisto::FALTA_INJUSTIFICADA] ?? 0), $notaMinima);

        return [
            'id' => $aluno->id,
            'nome' => $aluno->nome,
            'nome_curto' => $nomeCurto,
            'processo' => $aluno->numero_processo,
            'turma' => $aluno->turma?->codigo ?? $aluno->turma?->nome ?? '—',
            'curso' => $aluno->turma?->curso?->nome,
            'classe' => $aluno->turma?->classe?->codigo,
            'cor' => $this->cores[$indice % count($this->cores)],
            'url' => route('portal.encarregado.filho', $aluno),
            'pode_notas' => $podeNotas,
            'media_mt' => $mediaMt,
            'disciplinas' => $disciplinas,
            'negativas' => $negativas,
            'faltas_injustificadas' => (int) ($totais[PresencaRegisto::FALTA_INJUSTIFICADA] ?? 0),
            'faltas_justificadas' => (int) ($totais[PresencaRegisto::FALTA_JUSTIFICADA] ?? 0),
            'atrasos' => (int) ($totais[PresencaRegisto::ATRASO] ?? 0),
            'presentes' => (int) ($totais[PresencaRegisto::PRESENTE] ?? 0),
            'ocorrencias' => $ocorrencias,
            'score' => $score,
            'score_label' => $this->labelScore($score),
        ];
    }

    private function scoreSaude(?float $media, int $negativas, int $faltasInjust, float $notaMinima): int
    {
        $base = $media !== null
            ? (int) min(100, max(0, round(($media / 20) * 100)))
            : 55;

        $penalidade = min(40, ($negativas * 12) + ($faltasInjust * 4));
        if ($media !== null && $media < $notaMinima) {
            $penalidade += 10;
        }

        return (int) max(0, min(100, $base - $penalidade));
    }

    private function labelScore(int $score): string
    {
        return match (true) {
            $score >= 80 => 'Em bom ritmo',
            $score >= 60 => 'Atenção moderada',
            $score >= 40 => 'Precisa de acompanhamento',
            default => 'Situação crítica',
        };
    }
}
