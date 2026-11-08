<?php

namespace App\Services\Pedagogico;

use App\Models\Configuracao;
use App\Models\Pedagogico\PedHorarioSemanal;
use App\Models\Pedagogico\PedPlanificacao;
use App\Models\Pedagogico\PedQuinzena;
use App\Models\Pedagogico\PedQuinzenaDia;
use App\Models\Pedagogico\PedQuinzenaTema;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\User;
use App\Services\ConfiguracaoService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PlanificacaoService
{
    public function __construct(
        protected ConfiguracaoService $configuracaoService,
        protected CalendarioAulaService $calendarioAulaService,
    ) {}

    public function listar(User $user, array $filtros = []): LengthAwarePaginator
    {
        $query = PedPlanificacao::with(['turma.classe', 'disciplina', 'professor'])
            ->when($filtros['ano_lectivo_id'] ?? null, fn (Builder $q, $v) => $q->where('ano_lectivo_id', $v))
            ->when($filtros['trimestre'] ?? null, fn (Builder $q, $v) => $q->where('trimestre', $v))
            ->when($filtros['turma_id'] ?? null, fn (Builder $q, $v) => $q->where('turma_id', $v))
            ->when($filtros['disciplina_id'] ?? null, fn (Builder $q, $v) => $q->where('disciplina_id', $v))
            ->when($filtros['estado'] ?? null, fn (Builder $q, $v) => $q->where('estado', $v))
            ->when($filtros['user_id'] ?? null, fn (Builder $q, $v) => $q->where('user_id', $v));

        $query = $this->aplicarEscopoUtilizador($query, $user);

        return $query->latest()->paginate((int) ($filtros['per_page'] ?? 15));
    }

    public function criar(User $user, array $dados): PedPlanificacao
    {
        $anoLectivoId = (int) ($dados['ano_lectivo_id'] ?? Configuracao::getAnoLectivoActualId());
        $trimestre = (int) $dados['trimestre'];
        $turmaId = (int) $dados['turma_id'];
        $disciplinaId = (int) $dados['disciplina_id'];
        $professorId = $this->resolverProfessorId($user, $dados, $turmaId, $disciplinaId, $anoLectivoId);

        $professor = User::findOrFail($professorId);
        $this->validarAssociacaoProfessor($professor, $turmaId, $disciplinaId, $anoLectivoId);

        [$dataInicial, $dataFinal] = $this->resolverDatasTrimestre($trimestre, $dados);

        return PedPlanificacao::create([
            'ano_lectivo_id' => $anoLectivoId,
            'trimestre' => $trimestre,
            'turma_id' => $turmaId,
            'disciplina_id' => $disciplinaId,
            'user_id' => $professorId,
            'data_inicial' => $dataInicial,
            'data_final' => $dataFinal,
            'estado' => PedPlanificacao::ESTADO_RASCUNHO,
            'tipo_periodo' => $dados['tipo_periodo'] ?? PedPlanificacao::TIPO_QUINZENAL,
        ]);
    }

    /**
     * Resolve o professor da planificação.
     * Admin/coordenador/director: usa user_id do pedido ou a associação activa;
     * professor: usa a própria conta.
     */
    protected function resolverProfessorId(
        User $user,
        array $dados,
        int $turmaId,
        int $disciplinaId,
        int $anoLectivoId,
    ): int {
        $pedidoId = ! empty($dados['user_id']) ? (int) $dados['user_id'] : null;

        $podeAtribuirOutro = $user->temVisaoInstitucional()
            || $user->hasRole('coordenador_curso')
            || $user->hasRole('director_turma');

        if ($podeAtribuirOutro) {
            if ($pedidoId) {
                return $pedidoId;
            }

            $professoresIds = ProfessorDisciplinaTurma::query()
                ->where('turma_id', $turmaId)
                ->where('disciplina_id', $disciplinaId)
                ->where('activo', true)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->pluck('user_id')
                ->unique()
                ->values();

            if ($professoresIds->isEmpty()) {
                throw new \Exception(
                    'Não existe professor com associação activa a esta turma e disciplina. '
                    .'Crie a associação em Associacoes antes de planificar.'
                );
            }

            if ($professoresIds->count() > 1) {
                throw new \Exception(
                    'Existem vários professores associados a esta turma e disciplina. '
                    .'Seleccione o professor da planificação.'
                );
            }

            return (int) $professoresIds->first();
        }

        return $pedidoId ?: (int) $user->id;
    }

    public function actualizar(PedPlanificacao $planificacao, array $dados): PedPlanificacao
    {
        if ($planificacao->estado === PedPlanificacao::ESTADO_CONCLUIDO) {
            throw new \Exception('Não é possível editar uma planificação concluída.');
        }

        if (isset($dados['turma_id'], $dados['disciplina_id'])) {
            $this->validarAssociacaoProfessor(
                $planificacao->professor,
                (int) ($dados['turma_id'] ?? $planificacao->turma_id),
                (int) ($dados['disciplina_id'] ?? $planificacao->disciplina_id),
                (int) ($dados['ano_lectivo_id'] ?? $planificacao->ano_lectivo_id),
            );
        }

        if (isset($dados['trimestre']) && ! isset($dados['data_inicial'])) {
            [$dataInicial, $dataFinal] = $this->resolverDatasTrimestre((int) $dados['trimestre'], $dados);
            $dados['data_inicial'] = $dataInicial;
            $dados['data_final'] = $dataFinal;
        }

        $planificacao->update(collect($dados)->only([
            'ano_lectivo_id', 'trimestre', 'turma_id', 'disciplina_id',
            'user_id', 'data_inicial', 'data_final', 'tipo_periodo',
        ])->filter()->all());

        return $planificacao->fresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $quinzenas
     */
    public function guardarQuinzenas(PedPlanificacao $planificacao, array $quinzenas): PedPlanificacao
    {
        DB::transaction(function () use ($planificacao, $quinzenas) {
            $numeros = collect($quinzenas)->pluck('numero')->filter()->all();

            $planificacao->quinzenas()
                ->whereNotIn('numero', $numeros)
                ->delete();

            foreach ($quinzenas as $quinzenaData) {
                $temasNormalizados = $this->normalizarTemasPeriodo($quinzenaData);

                $quinzena = PedQuinzena::updateOrCreate(
                    [
                        'planificacao_id' => $planificacao->id,
                        'numero' => (int) $quinzenaData['numero'],
                    ],
                    [
                        'data_inicial' => $quinzenaData['data_inicial'],
                        'data_final' => $quinzenaData['data_final'],
                        'tema' => $this->resumirTemasPeriodo($temasNormalizados),
                        'subtemas' => $temasNormalizados,
                        'competencias' => $quinzenaData['competencias'] ?? [],
                        'objetivos' => $quinzenaData['objetivos'] ?? [],
                        'objetivos_geral' => $quinzenaData['objetivos_geral'] ?? null,
                        'objetivos_especificos' => $quinzenaData['objetivos_especificos'] ?? null,
                        'observacoes' => $quinzenaData['observacoes'] ?? null,
                    ]
                );

                $this->guardarTemasQuinzena($quinzena, $temasNormalizados);

                if (isset($quinzenaData['dias']) && is_array($quinzenaData['dias'])) {
                    $this->guardarDiasQuinzena($quinzena, $quinzenaData['dias']);
                }
            }
        });

        return $planificacao->fresh(['quinzenas.temas', 'quinzenas.dias']);
    }

    /**
     * @param  array<string, mixed>  $quinzenaData
     * @return array<int, array{codigo: ?string, titulo: string}>
     */
    protected function normalizarTemasPeriodo(array $quinzenaData): array
    {
        $temas = collect($quinzenaData['temas'] ?? [])
            ->map(function ($item) {
                if (is_string($item)) {
                    return ['codigo' => null, 'titulo' => trim($item)];
                }

                return [
                    'codigo' => isset($item['codigo']) ? trim((string) $item['codigo']) : null,
                    'titulo' => trim((string) ($item['titulo'] ?? '')),
                ];
            })
            ->filter(fn (array $item) => $item['titulo'] !== '')
            ->values()
            ->all();

        if ($temas !== []) {
            return $temas;
        }

        if (! empty($quinzenaData['tema'])) {
            return [['codigo' => null, 'titulo' => trim((string) $quinzenaData['tema'])]];
        }

        return [];
    }

    /**
     * @param  array<int, array{codigo: ?string, titulo: string}>  $temas
     */
    protected function resumirTemasPeriodo(array $temas): string
    {
        if ($temas === []) {
            return 'Período sem tema';
        }

        return collect($temas)
            ->map(fn (array $t) => trim(($t['codigo'] ? $t['codigo'].' ' : '').$t['titulo']))
            ->implode('; ');
    }

    /**
     * @param  array<int, array{codigo: ?string, titulo: string}>  $temas
     */
    protected function guardarTemasQuinzena(PedQuinzena $quinzena, array $temas): void
    {
        $quinzena->temas()->delete();

        $ordem = 0;
        foreach ($temas as $tema) {
            $ordem++;
            PedQuinzenaTema::create([
                'quinzena_id' => $quinzena->id,
                'codigo' => $tema['codigo'] ?: null,
                'titulo' => $tema['titulo'],
                'ordem' => $ordem,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $dias
     */
    protected function guardarDiasQuinzena(PedQuinzena $quinzena, array $dias): void
    {
        $datas = collect($dias)->pluck('data')->filter()->all();

        $quinzena->dias()->whereNotIn('data', $datas)->delete();

        $ordem = 0;
        foreach ($dias as $dia) {
            if (empty($dia['data']) || empty($dia['tema'])) {
                continue;
            }
            $ordem++;
            PedQuinzenaDia::updateOrCreate(
                [
                    'quinzena_id' => $quinzena->id,
                    'data' => $dia['data'],
                ],
                [
                    'tema' => $dia['tema'],
                    'objetivos_geral' => $dia['objetivos_geral'] ?? null,
                    'objetivos_especificos' => $dia['objetivos_especificos'] ?? null,
                    'tempos' => max(1, min((int) config('pedagogico.tempos_max', 8), (int) ($dia['tempos'] ?? 1))),
                    'hora_inicio' => $dia['hora_inicio'] ?? null,
                    'ordem' => $ordem,
                ]
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $horarios
     */
    public function guardarHorariosSemanais(PedPlanificacao $planificacao, array $horarios): PedPlanificacao
    {
        DB::transaction(function () use ($planificacao, $horarios) {
            $planificacao->horariosSemanais()->delete();

            foreach ($horarios as $horario) {
                PedHorarioSemanal::create([
                    'planificacao_id' => $planificacao->id,
                    'dia_semana' => (int) $horario['dia_semana'],
                    'hora_inicio' => $horario['hora_inicio'],
                    'hora_fim' => $horario['hora_fim'],
                ]);
            }
        });

        return $planificacao->fresh(['horariosSemanais']);
    }

    public function activar(PedPlanificacao $planificacao, ?User $actor = null): PedPlanificacao
    {
        $planificacao->load(['quinzenas.dias', 'horariosSemanais']);

        $actor = $actor ?? auth()->user();
        $podeBypass = $actor
            && $actor->can('pedagogico.aprovar')
            && PedagogicoAccess::podeVerTudo($actor);

        if ($planificacao->estado !== PedPlanificacao::ESTADO_APROVADA && ! $podeBypass) {
            throw new \Exception('A planificação precisa de estar aprovada antes de ser activada.');
        }

        if ($planificacao->quinzenas->isEmpty()) {
            throw new \Exception('A planificação precisa de pelo menos uma quinzena antes de ser activada.');
        }

        $temDiasQuinzena = $planificacao->quinzenas->contains(fn ($q) => $q->dias->isNotEmpty());

        if (! $temDiasQuinzena && $planificacao->horariosSemanais->isEmpty()) {
            throw new \Exception('Defina os dias de cada quinzena (data, tema, tempos) ou horários semanais.');
        }

        $this->validarAssociacaoProfessor(
            $planificacao->professor,
            $planificacao->turma_id,
            $planificacao->disciplina_id,
            $planificacao->ano_lectivo_id,
        );

        DB::transaction(function () use ($planificacao) {
            $planificacao->update(['estado' => PedPlanificacao::ESTADO_ACTIVO]);
            $this->calendarioAulaService->gerarCalendario($planificacao->fresh());
        });

        return $planificacao->fresh(['quinzenas', 'horariosSemanais', 'aulas']);
    }

    public function eliminar(PedPlanificacao $planificacao): void
    {
        if ($planificacao->estado === PedPlanificacao::ESTADO_ACTIVO) {
            throw new \Exception('Desactive ou conclua a planificação antes de eliminar.');
        }

        $planificacao->delete();
    }

    protected function aplicarEscopoUtilizador(Builder $query, User $user): Builder
    {
        if (PedagogicoAccess::podeVerTudo($user)) {
            return $query;
        }

        if ($user->hasRole('coordenador_disciplina')) {
            return app(DisciplinaCoordEscopoService::class)
                ->aplicarEscopoPlanificacao($query, $user);
        }

        if ($user->hasRole('coordenador_curso')) {
            $anoLectivoId = Configuracao::getAnoLectivoActualId();
            $cursosIds = $user->cursosComoCoordenador()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo_id', $anoLectivoId)
                ->pluck('cursos.id');

            return $query->whereHas('turma', fn (Builder $q) => $q->whereIn('curso_id', $cursosIds));
        }

        if ($user->hasRole('director_turma')) {
            $turmasIds = $user->turmasComoDirector()
                ->where('director_turma.activo', true)
                ->pluck('turmas.id');

            return $query->whereIn('turma_id', $turmasIds);
        }

        if ($user->hasRole('professor')) {
            return $query->where('user_id', $user->id);
        }

        return $query->whereRaw('0 = 1');
    }

    protected function validarAssociacaoProfessor(
        User $professor,
        int $turmaId,
        int $disciplinaId,
        int $anoLectivoId,
    ): void {
        $existe = ProfessorDisciplinaTurma::where('user_id', $professor->id)
            ->where('turma_id', $turmaId)
            ->where('disciplina_id', $disciplinaId)
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->exists();

        if (! $existe) {
            throw new \Exception(
                'O professor «'.$professor->name.'» não tem associação activa com esta turma e disciplina '
                .'no ano lectivo seleccionado. Verifique as Associações professor–disciplina–turma.'
            );
        }
    }

    /**
     * Professores com associação activa à turma/disciplina.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function professoresAssociados(int $turmaId, int $disciplinaId, ?int $anoLectivoId = null)
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();

        $ids = ProfessorDisciplinaTurma::query()
            ->where('turma_id', $turmaId)
            ->where('disciplina_id', $disciplinaId)
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolverDatasTrimestre(int $trimestre, array $dados): array
    {
        if (! empty($dados['data_inicial']) && ! empty($dados['data_final'])) {
            return [$dados['data_inicial'], $dados['data_final']];
        }

        $dataInicio = $this->configuracaoService->get("lancamento_t{$trimestre}_inicio");
        $dataFim = $this->configuracaoService->get("lancamento_t{$trimestre}_fim");

        if (! $dataInicio || ! $dataFim) {
            throw new \Exception("Datas do trimestre {$trimestre} não estão configuradas.");
        }

        return [$dataInicio, $dataFim];
    }
}
