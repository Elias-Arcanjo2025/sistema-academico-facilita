<?php

namespace App\Http\Controllers\Pedagogico;

use App\Http\Controllers\Controller;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Disciplina;
use App\Models\Pedagogico\PedPlanificacao;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Services\Pedagogico\CalendarioAulaService;
use App\Services\Pedagogico\PedagogicoAccess;
use App\Services\Pedagogico\PedagogicoAprovacaoService;
use App\Services\Pedagogico\PlanificacaoService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanificacaoController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private PlanificacaoService $planificacaoService,
        private CalendarioAulaService $calendarioAulaService,
        private PedagogicoAprovacaoService $aprovacaoService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PedPlanificacao::class);

        $anoLectivoId = (int) $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());
        $filtros = array_merge(
            $request->only(['trimestre', 'turma_id', 'disciplina_id', 'estado', 'user_id']),
            ['ano_lectivo_id' => $anoLectivoId],
        );

        $planificacoes = $this->planificacaoService->listar(auth()->user(), $filtros);

        return view('pedagogico.planificacoes.index', [
            'planificacoes' => $planificacoes,
            'filtros' => $filtros,
            'anoLectivoId' => $anoLectivoId,
            'anosLectivos' => AnoLectivo::getParaSelect(),
            'turmas' => $this->obterTurmasDisponiveis($anoLectivoId),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', PedPlanificacao::class);

        $user = auth()->user();

        $anoLectivoId = Configuracao::getAnoLectivoActualId();

        return view('pedagogico.planificacoes.create', [
            'turmas' => $this->obterTurmasDisponiveis($anoLectivoId),
            'anoLectivoId' => $anoLectivoId,
            'anosLectivos' => AnoLectivo::getParaSelect(),
            'podeEscolherProfessor' => $user->temVisaoInstitucional()
                || $user->hasRole('coordenador_curso')
                || $user->hasRole('director_turma'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PedPlanificacao::class);

        $validated = $request->validate([
            'trimestre' => 'required|integer|in:1,2,3',
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
            'tipo_periodo' => 'nullable|in:quinzenal,mensal',
            'user_id' => 'nullable|exists:users,id',
            'data_inicial' => 'nullable|date',
            'data_final' => 'nullable|date|after_or_equal:data_inicial',
        ]);

        try {
            $planificacao = $this->planificacaoService->criar(auth()->user(), $validated);

            return redirect()
                ->route('pedagogico.planificacoes.show', $planificacao)
                ->with('success', 'Planificação criada com sucesso.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(PedPlanificacao $planificacao): View
    {
        $this->authorize('view', $planificacao);

        $planificacao->load(['turma.curso', 'turma.classe', 'disciplina', 'professor', 'quinzenas.temas', 'quinzenas.dias', 'horariosSemanais', 'aulas']);

        $conteudoMinimo = null;
        if ($planificacao->disciplina_id && $planificacao->turma?->classe_id) {
            $conteudoMinimo = app(\App\Services\Pedagogico\ConteudoMinimoService::class)->publicadoPara(
                (int) $planificacao->disciplina_id,
                (int) $planificacao->turma->classe_id,
                $planificacao->turma->curso_id ? (int) $planificacao->turma->curso_id : null,
                $planificacao->ano_lectivo_id ? (int) $planificacao->ano_lectivo_id : null,
                $planificacao->trimestre ? (int) $planificacao->trimestre : null,
            );
        }

        return view('pedagogico.planificacoes.show', compact('planificacao', 'conteudoMinimo'));
    }

    public function edit(PedPlanificacao $planificacao): View
    {
        $this->authorize('update', $planificacao);

        $planificacao->load(['quinzenas.temas', 'quinzenas.dias', 'horariosSemanais']);

        return view('pedagogico.planificacoes.edit', [
            'planificacao' => $planificacao,
            'turmas' => $this->obterTurmasDisponiveis(),
        ]);
    }

    public function update(Request $request, PedPlanificacao $planificacao): RedirectResponse
    {
        $this->authorize('update', $planificacao);

        $validated = $request->validate([
            'trimestre' => 'sometimes|integer|in:1,2,3',
            'turma_id' => 'sometimes|exists:turmas,id',
            'disciplina_id' => 'sometimes|exists:disciplinas,id',
            'data_inicial' => 'nullable|date',
            'data_final' => 'nullable|date|after_or_equal:data_inicial',
            'tipo_periodo' => 'sometimes|in:quinzenal,mensal',
        ]);

        try {
            $this->planificacaoService->actualizar($planificacao, $validated);

            return redirect()
                ->route('pedagogico.planificacoes.show', $planificacao)
                ->with('success', 'Planificação actualizada.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(PedPlanificacao $planificacao): RedirectResponse
    {
        $this->authorize('delete', $planificacao);

        try {
            $this->planificacaoService->eliminar($planificacao);

            return redirect()
                ->route('pedagogico.planificacoes.index')
                ->with('success', 'Planificação eliminada.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function storeQuinzenas(Request $request, PedPlanificacao $planificacao): RedirectResponse
    {
        $this->authorize('update', $planificacao);

        $validated = $request->validate([
            'tipo_periodo' => 'nullable|in:quinzenal,mensal',
            'quinzenas' => 'required|array|min:1',
            'quinzenas.*.numero' => 'required|integer|min:1',
            'quinzenas.*.data_inicial' => 'required|date',
            'quinzenas.*.data_final' => 'required|date',
            'quinzenas.*.tema' => 'nullable|string|max:500',
            'quinzenas.*.temas' => 'nullable|array',
            'quinzenas.*.temas.*.codigo' => 'nullable|string|max:30',
            'quinzenas.*.temas.*.titulo' => 'nullable|string|max:500',
            'quinzenas.*.objetivos_geral' => 'nullable|string',
            'quinzenas.*.objetivos_especificos' => 'nullable|string',
            'quinzenas.*.subtemas' => 'nullable|array',
            'quinzenas.*.competencias' => 'nullable|array',
            'quinzenas.*.objetivos' => 'nullable|array',
            'quinzenas.*.observacoes' => 'nullable|string',
            'quinzenas.*.dias' => 'nullable|array',
            'quinzenas.*.dias.*.data' => 'nullable|date',
            'quinzenas.*.dias.*.tema' => 'nullable|string|max:255',
            'quinzenas.*.dias.*.objetivos_geral' => 'nullable|string',
            'quinzenas.*.dias.*.objetivos_especificos' => 'nullable|string',
            'quinzenas.*.dias.*.tempos' => 'nullable|integer|min:1|max:'.config('pedagogico.tempos_max', 8),
            'quinzenas.*.dias.*.hora_inicio' => 'nullable|date_format:H:i',
        ]);

        try {
            foreach ($validated['quinzenas'] as $index => $quinzena) {
                $temas = collect($quinzena['temas'] ?? [])
                    ->filter(fn ($t) => ! empty($t['titulo'] ?? null))
                    ->values();
                if ($temas->isEmpty() && empty($quinzena['tema'])) {
                    throw new \Exception('O período '.($quinzena['numero'] ?? ($index + 1)).' precisa de pelo menos um tema.');
                }
            }

            if (! empty($validated['tipo_periodo'])) {
                $planificacao->update(['tipo_periodo' => $validated['tipo_periodo']]);
            }

            $this->planificacaoService->guardarQuinzenas($planificacao, $validated['quinzenas']);

            return back()->with('success', 'Quinzenas guardadas com sucesso.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function storeHorarios(Request $request, PedPlanificacao $planificacao): RedirectResponse
    {
        $this->authorize('update', $planificacao);

        $validated = $request->validate([
            'horarios' => 'required|array|min:1',
            'horarios.*.dia_semana' => 'required|integer|between:1,7',
            'horarios.*.hora_inicio' => 'required|date_format:H:i',
            'horarios.*.hora_fim' => 'required|date_format:H:i|after:horarios.*.hora_inicio',
        ]);

        try {
            $this->planificacaoService->guardarHorariosSemanais($planificacao, $validated['horarios']);

            return back()->with('success', 'Horários semanais guardados.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function activar(PedPlanificacao $planificacao): RedirectResponse
    {
        $this->authorize('update', $planificacao);

        try {
            $this->planificacaoService->activar($planificacao, auth()->user());

            return redirect()
                ->route('pedagogico.planificacoes.show', $planificacao)
                ->with('success', 'Planificação activada e calendário gerado.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function submeter(PedPlanificacao $planificacao): RedirectResponse
    {
        $this->authorize('update', $planificacao);

        try {
            $this->aprovacaoService->submeterPlanificacao(auth()->user(), $planificacao);

            return back()->with('success', 'Planificação submetida para aprovação.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function aprovar(PedPlanificacao $planificacao): RedirectResponse
    {
        abort_unless(auth()->user()->can('pedagogico.aprovar'), 403);

        try {
            $this->aprovacaoService->aprovarPlanificacao(auth()->user(), $planificacao);

            return back()->with('success', 'Planificação aprovada.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function rejeitar(Request $request, PedPlanificacao $planificacao): RedirectResponse
    {
        abort_unless(auth()->user()->can('pedagogico.aprovar'), 403);

        $data = $request->validate([
            'motivo_rejeicao' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->aprovacaoService->rejeitarPlanificacao(
                auth()->user(),
                $planificacao,
                $data['motivo_rejeicao']
            );

            return back()->with('success', 'Planificação rejeitada.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function regenerarCalendario(PedPlanificacao $planificacao): RedirectResponse
    {
        $this->authorize('update', $planificacao);

        try {
            $this->calendarioAulaService->gerarCalendario($planificacao);

            return back()->with('success', 'Calendário de aulas regenerado.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function getDisciplinas(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PedPlanificacao::class);

        $validated = $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $turma = Turma::findOrFail($validated['turma_id']);
        $user = auth()->user();

        if (! PedagogicoAccess::turmaAcessivel($user, (int) $turma->id, $turma->ano_lectivo_id)) {
            return response()->json(['message' => 'Turma não disponível.'], 403);
        }

        if (! empty($validated['user_id']) && $user->temVisaoInstitucional()) {
            $anoLectivoId = $turma->ano_lectivo_id ?? Configuracao::getAnoLectivoActualId();
            $disciplinasIds = ProfessorDisciplinaTurma::query()
                ->where('user_id', (int) $validated['user_id'])
                ->where('turma_id', $turma->id)
                ->where('activo', true)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->pluck('disciplina_id');

            $disciplinas = Disciplina::query()
                ->whereIn('id', $disciplinasIds)
                ->where('activo', true)
                ->orderBy('nome')
                ->get();
        } else {
            $disciplinas = \App\Support\NotaLancamentoRules::disciplinasLancamentoNaTurma($user, $turma);
        }

        return response()->json(
            $disciplinas->map(fn (Disciplina $d) => [
                'id' => $d->id,
                'nome' => $d->codigo ? "{$d->codigo} — {$d->nome}" : $d->nome,
            ])->values()
        );
    }

    public function getProfessores(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PedPlanificacao::class);

        $validated = $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
        ]);

        $turma = Turma::findOrFail($validated['turma_id']);
        $user = auth()->user();

        if (! PedagogicoAccess::turmaAcessivel($user, (int) $turma->id, $turma->ano_lectivo_id)) {
            return response()->json(['message' => 'Turma não disponível.'], 403);
        }

        $podeEscolher = $user->temVisaoInstitucional()
            || $user->hasRole('coordenador_curso')
            || $user->hasRole('director_turma');

        if (! $podeEscolher) {
            return response()->json([]);
        }

        $professores = $this->planificacaoService->professoresAssociados(
            (int) $validated['turma_id'],
            (int) $validated['disciplina_id'],
            $turma->ano_lectivo_id
        );

        return response()->json(
            $professores->map(fn ($p) => [
                'id' => $p->id,
                'nome' => $p->name,
            ])->values()
        );
    }

    protected function obterTurmasDisponiveis(?int $anoLectivoId = null)
    {
        return PedagogicoAccess::turmasDisponiveis(auth()->user(), $anoLectivoId);
    }
}
