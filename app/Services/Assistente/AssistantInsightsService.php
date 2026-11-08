<?php

namespace App\Services\Assistente;

use App\Models\Aluno;
use App\Models\CoordenadorCurso;
use App\Models\DirectorTurma;
use App\Models\Nota;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\User;
use App\Services\ConfiguracaoService;
use Illuminate\Support\Collection;

class AssistantInsightsService
{
    public function __construct(
        protected ConfiguracaoService $configuracao,
    ) {}

    /**
     * Director de turma, coordenador do curso e directores das turmas do mesmo curso.
     *
     * @return array<string, mixed>
     */
    public function gestaoInstitucionalTurma(?Turma $turma, ?int $anoLectivoId): array
    {
        if (! $turma || ! $anoLectivoId) {
            return [];
        }

        $turma->loadMissing(['curso', 'classe']);

        $coordenador = CoordenadorCurso::query()
            ->with('coordenador')
            ->where('curso_id', $turma->curso_id)
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->first();

        $directorTurma = DirectorTurma::query()
            ->with('director')
            ->where('turma_id', $turma->id)
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->first();

        $directoresCurso = DirectorTurma::query()
            ->with(['director', 'turma.curso', 'turma.classe'])
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereHas('turma', fn ($q) => $q
                ->where('curso_id', $turma->curso_id)
                ->where('activo', true))
            ->get()
            ->map(fn (DirectorTurma $d) => [
                'turma' => $d->turma?->codigo ?? $d->turma?->nome,
                'classe' => $d->turma?->classe?->codigo,
                'director' => $d->director?->name,
            ])
            ->sortBy('turma')
            ->values()
            ->all();

        return [
            'curso' => $turma->curso?->nome,
            'coordenador_do_curso' => $coordenador?->coordenador?->name,
            'director_da_turma' => $directorTurma?->director?->name,
            'directores_turmas_do_curso' => $directoresCurso,
        ];
    }

    /**
     * @param  array<int>  $turmasIds
     * @return array<int, array<string, mixed>>
     */
    public function listarDirectoresTurmas(array $turmasIds, ?int $anoLectivoId, ?string $termoTurma = null): array
    {
        if ($turmasIds === [] || ! $anoLectivoId) {
            return [];
        }

        $query = DirectorTurma::query()
            ->with(['director', 'turma.curso', 'turma.classe'])
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereIn('turma_id', $turmasIds);

        if ($termoTurma && mb_strlen($termoTurma) >= 2) {
            $query->whereHas('turma', fn ($q) => $q
                ->where('codigo', 'like', "%{$termoTurma}%")
                ->orWhere('nome', 'like', "%{$termoTurma}%"));
        }

        return $query->get()
            ->map(fn (DirectorTurma $d) => [
                'turma' => $d->turma?->codigo ?? $d->turma?->nome,
                'curso' => $d->turma?->curso?->nome,
                'classe' => $d->turma?->classe?->codigo,
                'director' => $d->director?->name,
            ])
            ->sortBy('turma')
            ->values()
            ->all();
    }

    /**
     * @param  array<int>  $cursoIds
     * @return array<int, array<string, mixed>>
     */
    public function listarCoordenadoresCursos(array $cursoIds, ?int $anoLectivoId): array
    {
        if ($cursoIds === [] || ! $anoLectivoId) {
            return [];
        }

        return CoordenadorCurso::query()
            ->with(['coordenador', 'curso'])
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereIn('curso_id', $cursoIds)
            ->get()
            ->map(fn (CoordenadorCurso $c) => [
                'curso' => $c->curso?->nome,
                'coordenador' => $c->coordenador?->name,
            ])
            ->sortBy('curso')
            ->values()
            ->all();
    }

    /**
     * Análise pré-calculada para o assistente responder comparações e recomendações.
     *
     * @param  Collection<int, Nota>  $notas
     * @return array<string, mixed>
     */
    public function analisarDesempenhoAluno(Collection $notas, float $notaMinima, ?int $anoLectivoId): array
    {
        if (! $anoLectivoId) {
            return [
                'nota_minima_aprovacao' => $notaMinima,
                'comparacao_por_disciplina' => [],
                'disciplinas_a_melhorar' => [],
                'melhores_disciplinas' => [],
                'maior_evolucao_t1_t2' => [],
                'maior_queda_t1_t2' => [],
                'instrucao' => 'Sem ano lectivo activo — não há dados de desempenho para analisar.',
            ];
        }

        $notas = $notas
            ->whereNull('archived_at')
            ->where('ano_lectivo_id', $anoLectivoId);

        $porDisciplina = $notas->groupBy('disciplina_id');
        $comparacao = [];
        $ranking = [];

        foreach ($porDisciplina as $grupo) {
            $disciplina = $grupo->first()?->disciplina?->nome ?? 'Disciplina';

            $mtPorTrimestre = $grupo->where('tipo', Nota::TIPO_MT)
                ->keyBy('trimestre')
                ->map(fn (Nota $n) => (float) $n->valor);

            $t1 = $mtPorTrimestre->get(1);
            $t2 = $mtPorTrimestre->get(2);
            $t3 = $mtPorTrimestre->get(3);

            $ultimoTrimestre = collect([3 => $t3, 2 => $t2, 1 => $t1])
                ->filter(fn ($v) => $v !== null)
                ->keys()
                ->max();

            $mtRecente = $ultimoTrimestre ? $mtPorTrimestre->get($ultimoTrimestre) : null;

            $variacaoT1T2 = ($t1 !== null && $t2 !== null) ? round($t2 - $t1, 2) : null;

            $comparacao[] = [
                'disciplina' => $disciplina,
                'mt_1_trimestre' => $t1,
                'mt_2_trimestre' => $t2,
                'mt_3_trimestre' => $t3,
                'variacao_t1_para_t2' => $variacaoT1T2,
                'mt_mais_recente' => $mtRecente,
                'trimestre_mais_recente' => $ultimoTrimestre,
            ];

            if ($mtRecente !== null) {
                $ranking[] = [
                    'disciplina' => $disciplina,
                    'mt' => $mtRecente,
                    'trimestre' => $ultimoTrimestre,
                    'abaixo_minimo' => $mtRecente < $notaMinima,
                ];
            }
        }

        usort($ranking, fn ($a, $b) => $a['mt'] <=> $b['mt']);

        $aMelhorar = collect($ranking)
            ->filter(fn ($r) => $r['abaixo_minimo'] || $r['mt'] < ($notaMinima + 2))
            ->take(5)
            ->values()
            ->all();

        $melhores = collect($ranking)->sortByDesc('mt')->take(5)->values()->all();

        $evolucaoPositiva = collect($comparacao)
            ->filter(fn ($c) => $c['variacao_t1_para_t2'] !== null && $c['variacao_t1_para_t2'] > 0)
            ->sortByDesc('variacao_t1_para_t2')
            ->take(5)
            ->values()
            ->all();

        $evolucaoNegativa = collect($comparacao)
            ->filter(fn ($c) => $c['variacao_t1_para_t2'] !== null && $c['variacao_t1_para_t2'] < 0)
            ->sortBy('variacao_t1_para_t2')
            ->take(5)
            ->values()
            ->all();

        return [
            'nota_minima_aprovacao' => $notaMinima,
            'comparacao_por_disciplina' => $comparacao,
            'disciplinas_a_melhorar' => $aMelhorar,
            'melhores_disciplinas' => $melhores,
            'maior_evolucao_t1_t2' => $evolucaoPositiva,
            'maior_queda_t1_t2' => $evolucaoNegativa,
            'instrucao' => 'Use estes dados para comparar trimestres, indicar onde melhorar e destacar melhores notas. '
                .'Responda com valores concretos do JSON, não apenas remeta ao menu.',
        ];
    }

    /**
     * Contexto docente: turmas, disciplinas e alunos sem MAC/PP no trimestre actual.
     *
     * @return array<string, mixed>|null
     */
    public function contextoProfessor(User $user, ?int $anoLectivoId): ?array
    {
        if ($user->hasRole('aluno') || ! $anoLectivoId) {
            return null;
        }

        $trimestre = $this->configuracao->getTrimestreActual();

        $associacoes = $user->professorDisciplinaTurmas()
            ->with(['disciplina', 'turma.curso'])
            ->where('activo', true)
            ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
            ->get();

        if ($associacoes->isEmpty()) {
            return null;
        }

        $turmasQueLecciona = $associacoes
            ->groupBy('turma_id')
            ->map(function (Collection $grupo) {
                $turma = $grupo->first()?->turma;

                return [
                    'turma' => $turma?->codigo ?? $turma?->nome,
                    'curso' => $turma?->curso?->nome,
                    'disciplinas' => $grupo->map(fn (ProfessorDisciplinaTurma $p) => $p->disciplina?->nome)
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $pendencias = [];

        foreach ($associacoes->unique(fn (ProfessorDisciplinaTurma $p) => $p->turma_id.'-'.$p->disciplina_id) as $assoc) {
            $alunos = Aluno::query()
                ->where('turma_id', $assoc->turma_id)
                ->where('activo', true)
                ->orderBy('nome')
                ->get(['id', 'nome', 'numero_processo']);

            if ($alunos->isEmpty()) {
                continue;
            }

            $notasLancadas = Nota::query()
                ->where('disciplina_id', $assoc->disciplina_id)
                ->where('turma_id', $assoc->turma_id)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('trimestre', $trimestre)
                ->whereIn('tipo', [Nota::TIPO_MAC, Nota::TIPO_PP])
                ->whereNull('archived_at')
                ->get()
                ->groupBy('aluno_id');

            $semAvaliacao = [];

            foreach ($alunos as $aluno) {
                $tipos = $notasLancadas->get($aluno->id, collect())->pluck('tipo')->unique()->all();
                $faltam = array_values(array_diff([Nota::TIPO_MAC, Nota::TIPO_PP], $tipos));

                if ($faltam !== []) {
                    $semAvaliacao[] = [
                        'nome' => $aluno->nome,
                        'numero_processo' => $aluno->numero_processo,
                        'notas_em_falta' => $faltam,
                    ];
                }
            }

            if ($semAvaliacao !== []) {
                $pendencias[] = [
                    'turma' => $assoc->turma?->codigo ?? $assoc->turma?->nome,
                    'disciplina' => $assoc->disciplina?->nome,
                    'trimestre' => $trimestre,
                    'total_alunos' => $alunos->count(),
                    'alunos_sem_avaliacao_completa' => count($semAvaliacao),
                    'detalhes' => array_slice($semAvaliacao, 0, 25),
                ];
            }
        }

        return [
            'turmas_que_lecciona' => $turmasQueLecciona,
            'trimestre_referencia' => $trimestre,
            'pendencias_lancamento' => $pendencias,
            'total_pendencias' => count($pendencias),
            'instrucao' => 'Use pendencias_lancamento para indicar alunos sem MAC ou PP lançados no trimestre actual. '
                .'Liste turmas e disciplinas que o professor lecciona a partir de turmas_que_lecciona.',
        ];
    }
}
