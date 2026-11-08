<?php

namespace App\Services\Pedagogico;

use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\User;
use App\Services\ConfiguracaoService;
use App\Support\NotaLancamentoRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de rendimento — só disciplinas/classes coordenadas.
 */
class RelatorioDisciplinaService
{
    public function __construct(
        protected DisciplinaCoordEscopoService $escopo,
        protected ConfiguracaoService $configuracaoService,
    ) {}

    public function podeVer(User $user): bool
    {
        return PedagogicoAccess::podeAcederCoordenacaoDisciplina($user, 'relatorios.disciplina');
    }

    /**
     * @return array<string, mixed>
     */
    public function rendimento(User $user, ?int $trimestre = null, ?int $anoLectivoId = null): array
    {
        abort_unless($this->podeVer($user), 403);

        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();
        $trimestre = $trimestre ?? (int) $this->configuracaoService->getTrimestreActual();
        $notaMinima = (float) (Configuracao::get('nota_minima_aprovacao') ?? 10);

        $associacoes = $this->escopo->queryAssociacoes($user, $anoLectivoId)
            ->with(['disciplina:id,nome,codigo', 'turma:id,nome,classe_id', 'professor:id,name'])
            ->get();

        $paresTurmaDisc = $associacoes
            ->unique(fn ($a) => $a->turma_id.':'.$a->disciplina_id)
            ->values();

        $tipos = [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT, Nota::TIPO_MT];
        $porTurma = collect();
        $somas = array_fill_keys($tipos, ['soma' => 0.0, 'n' => 0]);
        $alertas = collect();
        $totalNegativas = 0;
        $totalNotasMt = 0;

        foreach ($paresTurmaDisc as $assoc) {
            $mediasQuery = Nota::query()
                ->where('turma_id', $assoc->turma_id)
                ->where('disciplina_id', $assoc->disciplina_id)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('trimestre', $trimestre)
                ->whereIn('tipo', $tipos)
                ->whereNotNull('valor');
            NotaLancamentoRules::aplicarVisibilidadeArquivo($mediasQuery, $anoLectivoId);

            $medias = $mediasQuery
                ->select('tipo', DB::raw('AVG(valor) as media'), DB::raw('COUNT(*) as total'))
                ->groupBy('tipo')
                ->get()
                ->keyBy('tipo');

            $negQuery = Nota::query()
                ->where('turma_id', $assoc->turma_id)
                ->where('disciplina_id', $assoc->disciplina_id)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('trimestre', $trimestre)
                ->where('tipo', Nota::TIPO_MT)
                ->whereNotNull('valor')
                ->where('valor', '<', $notaMinima);
            NotaLancamentoRules::aplicarVisibilidadeArquivo($negQuery, $anoLectivoId);
            $negativas = $negQuery->count();

            $totalMt = (int) ($medias[Nota::TIPO_MT]->total ?? 0);
            $totalNegativas += $negativas;
            $totalNotasMt += $totalMt;

            $linha = [
                'turma_id' => (int) $assoc->turma_id,
                'turma' => $assoc->turma?->nome,
                'disciplina_id' => (int) $assoc->disciplina_id,
                'disciplina' => $assoc->disciplina?->nome,
                'medias' => [],
                'negativas_mt' => $negativas,
                'total_mt' => $totalMt,
            ];

            foreach ($tipos as $tipo) {
                $media = isset($medias[$tipo]) ? round((float) $medias[$tipo]->media, 2) : null;
                $linha['medias'][$tipo] = $media;
                if ($media !== null) {
                    $somas[$tipo]['soma'] += $media;
                    $somas[$tipo]['n']++;
                }
            }

            $mt = $linha['medias'][Nota::TIPO_MT];
            if ($mt !== null && $mt < $notaMinima) {
                $alertas->push([
                    'turma' => $linha['turma'],
                    'disciplina' => $linha['disciplina'],
                    'media_mt' => $mt,
                    'mensagem' => 'Média MT abaixo do mínimo ('.$notaMinima.').',
                ]);
            }

            $porTurma->push($linha);
        }

        $mediasGlobais = [];
        foreach ($tipos as $tipo) {
            $mediasGlobais[$tipo] = $somas[$tipo]['n'] > 0
                ? round($somas[$tipo]['soma'] / $somas[$tipo]['n'], 2)
                : null;
        }

        return [
            'trimestre' => $trimestre,
            'ano_lectivo_id' => $anoLectivoId,
            'nota_minima' => $notaMinima,
            'por_turma' => $porTurma->sortBy(['disciplina', 'turma'])->values(),
            'por_professor' => $this->aproveitamentoPorProfessor($associacoes, $anoLectivoId, $trimestre, $notaMinima),
            'evolucao' => $this->evolucaoMedias($paresTurmaDisc, $anoLectivoId),
            'medias_globais' => $mediasGlobais,
            'negativas' => [
                'total' => $totalNegativas,
                'total_avaliacoes_mt' => $totalNotasMt,
                'percentagem' => $totalNotasMt > 0
                    ? round(($totalNegativas / $totalNotasMt) * 100, 1)
                    : null,
            ],
            'alertas' => $alertas,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $associacoes
     * @return Collection<int, array<string, mixed>>
     */
    private function aproveitamentoPorProfessor(
        Collection $associacoes,
        int $anoLectivoId,
        int $trimestre,
        float $notaMinima,
    ): Collection {
        $porProfessor = collect();

        $grupos = $associacoes->groupBy('user_id');

        foreach ($grupos as $userId => $assocs) {
            $professor = $assocs->first()->professor;
            $somasMt = 0.0;
            $nMt = 0;
            $negativas = 0;
            $turmas = [];

            foreach ($assocs->unique(fn ($a) => $a->turma_id.':'.$a->disciplina_id) as $assoc) {
                $statsQuery = Nota::query()
                    ->where('turma_id', $assoc->turma_id)
                    ->where('disciplina_id', $assoc->disciplina_id)
                    ->where('ano_lectivo_id', $anoLectivoId)
                    ->where('trimestre', $trimestre)
                    ->where('tipo', Nota::TIPO_MT)
                    ->whereNotNull('valor');
                NotaLancamentoRules::aplicarVisibilidadeArquivo($statsQuery, $anoLectivoId);

                $stats = $statsQuery
                    ->selectRaw('AVG(valor) as media, COUNT(*) as total, SUM(CASE WHEN valor < ? THEN 1 ELSE 0 END) as negativas', [$notaMinima])
                    ->first();

                if ($stats && (int) $stats->total > 0) {
                    $somasMt += (float) $stats->media;
                    $nMt++;
                    $negativas += (int) $stats->negativas;
                }

                $turmas[] = $assoc->turma?->nome;
            }

            $porProfessor->push([
                'professor_id' => (int) $userId,
                'professor' => $professor?->name,
                'turmas' => collect($turmas)->filter()->unique()->values()->implode(', '),
                'media_mt' => $nMt > 0 ? round($somasMt / $nMt, 2) : null,
                'negativas_mt' => $negativas,
            ]);
        }

        return $porProfessor->sortBy('professor')->values();
    }

    /**
     * @param  Collection<int, mixed>  $paresTurmaDisc
     * @return Collection<int, array{trimestre: int, media_mt: float|null}>
     */
    private function evolucaoMedias(Collection $paresTurmaDisc, int $anoLectivoId): Collection
    {
        $evolucao = collect();

        for ($t = 1; $t <= 3; $t++) {
            $somas = 0.0;
            $n = 0;

            foreach ($paresTurmaDisc as $assoc) {
                $mediaQuery = Nota::query()
                    ->where('turma_id', $assoc->turma_id)
                    ->where('disciplina_id', $assoc->disciplina_id)
                    ->where('ano_lectivo_id', $anoLectivoId)
                    ->where('trimestre', $t)
                    ->where('tipo', Nota::TIPO_MT)
                    ->whereNotNull('valor');
                NotaLancamentoRules::aplicarVisibilidadeArquivo($mediaQuery, $anoLectivoId);

                $media = $mediaQuery->avg('valor');

                if ($media !== null) {
                    $somas += (float) $media;
                    $n++;
                }
            }

            $evolucao->push([
                'trimestre' => $t,
                'media_mt' => $n > 0 ? round($somas / $n, 2) : null,
            ]);
        }

        return $evolucao;
    }
}
