<?php

namespace App\Http\Controllers;

use App\Http\Requests\AjustarNotaConselhoRequest;
use App\Models\AjusteConselho;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\ConselhoTrimestre;
use App\Models\Nota;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Services\ConselhoService;
use App\Support\NotaLancamentoRules;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ConselhoController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ConselhoService $conselhoService
    ) {}

    /**
     * Lista todos os conselhos (filtrados por turma/ano)
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ConselhoTrimestre::class);

        $user = auth()->user();
        $turmaId = $request->get('turma_id');
        $anoLectivoId = (int) $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());
        $anoLectivoNome = AnoLectivo::getNomeById($anoLectivoId);
        $anosLectivos = AnoLectivo::getParaSelect();
        $modoConsulta = Configuracao::isModoConsultaById($anoLectivoId, $user);
        $trimestreActual = Configuracao::getTrimestreActual();
        $isAnoLectivoActual = $anoLectivoId === Configuracao::getAnoLectivoActualId();

        // Determinar turmas disponíveis baseado na role do usuário
        if ($user->temVisaoInstitucional()) {
            // Visão institucional: todas as turmas
            $turmas = Turma::activo()
                ->where('ano_lectivo_id', $anoLectivoId)
                ->with('curso', 'classe')
                ->orderBy('nome')
                ->get();

        } elseif ($user->hasRole('coordenador_curso')) {
            // Coordenador vê apenas turmas dos seus cursos
            $cursosIds = $user->cursosComoCoordenador()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo_id', $anoLectivoId)
                ->pluck('cursos.id');

            $turmas = Turma::activo()
                ->where('ano_lectivo_id', $anoLectivoId)
                ->whereIn('curso_id', $cursosIds)
                ->with('curso', 'classe')
                ->orderBy('nome')
                ->get();

        } elseif ($user->hasRole('director_turma')) {
            // Director de Turma vê apenas suas turmas
            $turmas = $user->turmasComoDirector()
                ->where('director_turma.activo', true)
                ->where('turmas.activo', true)
                ->where('turmas.ano_lectivo_id', $anoLectivoId)
                ->with('curso', 'classe')
                ->orderBy('turmas.nome')
                ->get();

        } elseif ($user->hasRole('professor')) {
            // Professor vê as turmas onde lecciona (só pode visualizar, não gerir)
            $turmaIds = ProfessorDisciplinaTurma::where('user_id', $user->id)
                ->where('activo', true)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->pluck('turma_id')
                ->unique();

            $turmas = Turma::activo()
                ->whereIn('id', $turmaIds)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->with('curso', 'classe')
                ->orderBy('nome')
                ->get();
        } else {
            $turmas = collect();
        }

        // Se turma_id foi fornecida, buscar conselhos dessa turma
        $conselhos = collect();
        $turmaSelecionada = null;
        $conselhoActual = null;

        if ($turmaId) {
            $turmaSelecionada = $turmas->firstWhere('id', $turmaId);

            if ($turmaSelecionada) {
                // Buscar ou criar conselhos para os 3 trimestres
                $conselhos = collect();
                for ($trimestre = 1; $trimestre <= 3; $trimestre++) {
                    $conselho = $this->conselhoService->obterOuCriarConselho(
                        $turmaId,
                        $trimestre,
                        $anoLectivoNome
                    );

                    // Contar ajustes
                    $conselho->load(['ajustes', 'iniciadoPor', 'concluidoPor']);
                    $conselho->total_ajustes = $conselho->ajustes->count();

                    $conselhos->push($conselho);
                }

                if ($isAnoLectivoActual && $trimestreActual) {
                    $conselhoActual = $conselhos->firstWhere('trimestre', $trimestreActual);
                }
            }
        }

        return view('conselhos.index', [
            'turmas' => $turmas,
            'turmaSelecionada' => $turmaSelecionada,
            'conselhos' => $conselhos,
            'conselhoActual' => $conselhoActual,
            'trimestreActual' => $trimestreActual,
            'isAnoLectivoActual' => $isAnoLectivoActual,
            'anoLectivo' => $anoLectivoNome,
            'anoLectivoId' => $anoLectivoId,
            'anosLectivos' => $anosLectivos,
            'modoConsulta' => $modoConsulta,
        ]);
    }

    /**
     * Mostra detalhes de um conselho específico com todas as notas
     */
    public function show(Request $request, ConselhoTrimestre $conselho): View|RedirectResponse
    {
        $this->authorize('view', $conselho);

        $navTurmaId = (int) $request->get('turma_id', $conselho->turma_id);
        $navAnoLectivoId = (int) $request->get('ano_lectivo_id', $conselho->ano_lectivo_id);
        $navTrimestre = (int) $request->get('trimestre', $conselho->trimestre);

        if ($navTurmaId !== (int) $conselho->turma_id
            || $navAnoLectivoId !== (int) $conselho->ano_lectivo_id
            || $navTrimestre !== (int) $conselho->trimestre) {
            $anoNome = AnoLectivo::getNomeById($navAnoLectivoId);
            $destino = $this->conselhoService->obterOuCriarConselho($navTurmaId, $navTrimestre, $anoNome);
            $this->authorize('view', $destino);

            return redirect()->route('conselhos.show', array_filter([
                'conselho' => $destino,
                'aluno' => $request->get('aluno'),
                'apenas_falhas' => $request->get('apenas_falhas'),
            ]));
        }

        $conselho->load([
            'turma.curso',
            'turma.classe',
            'turma.turno',
            'anoLectivo',
            'iniciadoPor',
            'concluidoPor',
            'ajustes.notaPre.aluno',
            'ajustes.notaPre.disciplina',
            'ajustes.notaPos',
            'ajustes.ajustadoPor',
        ]);

        $colunasAvaliacao = $this->conselhoService->colunasAvaliacaoConselho(
            $conselho->turma_id,
            $conselho->trimestre,
        );
        $substituiPtPorPg = collect($colunasAvaliacao)->contains(fn ($c) => ! empty($c['substitui_pt']));

        $user = auth()->user();
        $disciplinas = NotaLancamentoRules::disciplinasLancamentoNaTurma($user, $conselho->turma);
        $disciplinaIdsVisiveis = $disciplinas->pluck('id')->all();
        $visaoApenasSuasDisciplinas = $this->visaoApenasSuasDisciplinas($user, $conselho);

        $notas = $this->conselhoService->obterNotasParaConselho(
            $conselho->turma_id,
            $conselho->trimestre,
            $conselho->ano_lectivo
        );

        if ($disciplinaIdsVisiveis !== []) {
            $notas = $notas->filter(
                fn ($nota) => in_array((int) $nota->disciplina_id, $disciplinaIdsVisiveis, true)
            );
        }

        $notasAgrupadasCompletas = $notas->groupBy(function ($nota) {
            return $nota->aluno_id . '_' . $nota->disciplina_id;
        })->map(function ($grupo) use ($colunasAvaliacao) {
            $aluno = $grupo->first()->aluno;
            $disciplina = $grupo->first()->disciplina;

            $linha = [
                'aluno' => $aluno,
                'disciplina' => $disciplina,
            ];

            foreach ($colunasAvaliacao as $coluna) {
                $linha[$coluna['key']] = $grupo->firstWhere('tipo', $coluna['tipo']);
            }

            return $linha;
        })->sortBy(fn ($g) => $g['aluno']->numero_ordem ?? 999)->values();

        $filtros = [
            'disciplina_id' => $request->get('disciplina_id'),
            'aluno' => trim((string) $request->get('aluno', '')),
            'apenas_falhas' => $request->boolean('apenas_falhas'),
        ];

        if ($filtros['disciplina_id']
            && ! in_array((int) $filtros['disciplina_id'], $disciplinaIdsVisiveis, true)) {
            $filtros['disciplina_id'] = null;
        }

        $notasAgrupadas = $this->filtrarNotasAgrupadas(
            $notasAgrupadasCompletas,
            $colunasAvaliacao,
            $filtros,
        );

        $ajustesVisiveis = $conselho->ajustes->filter(
            fn (AjusteConselho $ajuste) => in_array(
                (int) $ajuste->notaPre->disciplina_id,
                $disciplinaIdsVisiveis,
                true,
            )
        );

        $ajustesFiltrados = $this->filtrarAjustes($ajustesVisiveis, $filtros);

        $estatisticas = $this->conselhoService->obterEstatisticasConselho($conselho);
        $modoConsulta = Configuracao::isModoConsultaById($conselho->ano_lectivo_id, auth()->user());
        $anosLectivos = AnoLectivo::getParaSelect();
        $turmas = $this->turmasDisponiveis(auth()->user(), $conselho->ano_lectivo_id);

        $disciplinasPorTurma = $turmas->mapWithKeys(function (Turma $turma) use ($user) {
            $turma->loadMissing(['curso', 'classe']);

            return [
                $turma->id => NotaLancamentoRules::disciplinasLancamentoNaTurma($user, $turma)
                    ->map(fn ($d) => ['id' => $d->id, 'nome' => $d->nome])
                    ->values()
                    ->all(),
            ];
        });

        return view('conselhos.show', [
            'conselho' => $conselho,
            'notasAgrupadas' => $notasAgrupadas,
            'notasAgrupadasCompletas' => $notasAgrupadasCompletas,
            'ajustesFiltrados' => $ajustesFiltrados,
            'colunasAvaliacao' => $colunasAvaliacao,
            'substituiPtPorPg' => $substituiPtPorPg,
            'estatisticas' => $estatisticas,
            'motivosReducao' => \App\Support\ConselhoAjusteRules::motivosReducaoPermitidos(),
            'modoConsulta' => $modoConsulta,
            'filtros' => $filtros,
            'disciplinas' => $disciplinas,
            'turmas' => $turmas,
            'anosLectivos' => $anosLectivos,
            'visaoApenasSuasDisciplinas' => $visaoApenasSuasDisciplinas,
            'disciplinasPorTurma' => $disciplinasPorTurma,
        ]);
    }

    /**
     * Disciplinas visíveis ao utilizador numa turma (AJAX para filtros do conselho).
     */
    public function getDisciplinas(Request $request)
    {
        $this->authorize('viewAny', ConselhoTrimestre::class);

        $turmaId = (int) $request->get('turma_id');
        if (! $turmaId) {
            return response()->json([]);
        }

        $turma = Turma::with(['curso', 'classe'])->find($turmaId);
        if (! $turma) {
            return response()->json([]);
        }

        return response()->json(
            NotaLancamentoRules::disciplinasLancamentoNaTurma(auth()->user(), $turma)
                ->map(fn ($d) => ['id' => $d->id, 'nome' => $d->nome])
                ->values()
        );
    }

    /**
     * Professor comum vê só as disciplinas que lecciona; director/coordenação/admin vêem todas da turma.
     */
    private function visaoApenasSuasDisciplinas(\App\Models\User $user, ConselhoTrimestre $conselho): bool
    {
        if ($user->temVisaoInstitucional() || $user->hasRole('subdiretor_pedagogico')) {
            return false;
        }

        $turma = $conselho->turma;
        $anoLectivoNome = $conselho->ano_lectivo;

        if ($user->hasRole('director_turma')) {
            $eDirector = $user->turmasComoDirector()
                ->where('director_turma.activo', true)
                ->where('director_turma.ano_lectivo', $anoLectivoNome)
                ->where('turmas.id', $turma->id)
                ->exists();

            if ($eDirector) {
                return false;
            }
        }

        if ($user->hasRole('coordenador_curso')) {
            $cursosIds = $user->cursosComoCoordenador()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo_id', $conselho->ano_lectivo_id)
                ->pluck('cursos.id');

            if ($cursosIds->contains($turma->curso_id)) {
                return false;
            }
        }

        return $user->hasRole('professor');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $notasAgrupadas
     * @param  array<int, array{key: string, header: string, tipo: string}>  $colunasAvaliacao
     * @param  array{disciplina_id: mixed, aluno: string, apenas_falhas: bool}  $filtros
     */
    private function filtrarNotasAgrupadas($notasAgrupadas, array $colunasAvaliacao, array $filtros)
    {
        return $notasAgrupadas->filter(function ($grupo) use ($colunasAvaliacao, $filtros) {
            if ($filtros['disciplina_id'] && (int) $grupo['disciplina']->id !== (int) $filtros['disciplina_id']) {
                return false;
            }

            if ($filtros['aluno'] !== '') {
                $termo = mb_strtolower($filtros['aluno']);
                $nome = mb_strtolower($grupo['aluno']->nome ?? '');
                $processo = mb_strtolower((string) ($grupo['aluno']->numero_processo ?? ''));

                if (! str_contains($nome, $termo) && ! str_contains($processo, $termo)) {
                    return false;
                }
            }

            if ($filtros['apenas_falhas']) {
                $temFalha = collect($colunasAvaliacao)->contains(
                    fn ($coluna) => empty($grupo[$coluna['key']])
                );

                if (! $temFalha) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AjusteConselho>  $ajustes
     * @param  array{disciplina_id: mixed, aluno: string, apenas_falhas: bool}  $filtros
     */
    private function filtrarAjustes($ajustes, array $filtros)
    {
        return $ajustes->filter(function (AjusteConselho $ajuste) use ($filtros) {
            if ($filtros['disciplina_id']
                && (int) $ajuste->notaPre->disciplina_id !== (int) $filtros['disciplina_id']) {
                return false;
            }

            if ($filtros['aluno'] !== '') {
                $termo = mb_strtolower($filtros['aluno']);
                $nome = mb_strtolower($ajuste->notaPre->aluno->nome ?? '');
                $processo = mb_strtolower((string) ($ajuste->notaPre->aluno->numero_processo ?? ''));

                if (! str_contains($nome, $termo) && ! str_contains($processo, $termo)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * Turmas visíveis ao utilizador no ano lectivo indicado.
     */
    private function turmasDisponiveis(\App\Models\User $user, int $anoLectivoId)
    {
        if ($user->temVisaoInstitucional()) {
            return Turma::activo()
                ->where('ano_lectivo_id', $anoLectivoId)
                ->with('curso')
                ->orderBy('nome')
                ->get();
        }

        if ($user->hasRole('coordenador_curso')) {
            $cursosIds = $user->cursosComoCoordenador()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo_id', $anoLectivoId)
                ->pluck('cursos.id');

            return Turma::activo()
                ->where('ano_lectivo_id', $anoLectivoId)
                ->whereIn('curso_id', $cursosIds)
                ->with('curso')
                ->orderBy('nome')
                ->get();
        }

        if ($user->hasRole('director_turma')) {
            return $user->turmasComoDirector()
                ->where('director_turma.activo', true)
                ->where('turmas.activo', true)
                ->where('turmas.ano_lectivo_id', $anoLectivoId)
                ->with('curso')
                ->orderBy('turmas.nome')
                ->get();
        }

        if ($user->hasRole('professor')) {
            $turmaIds = ProfessorDisciplinaTurma::where('user_id', $user->id)
                ->where('activo', true)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->pluck('turma_id')
                ->unique();

            return Turma::activo()
                ->whereIn('id', $turmaIds)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->with('curso')
                ->orderBy('nome')
                ->get();
        }

        return collect();
    }

    /**
     * Inicia um conselho (pendente → em_curso)
     */
    public function iniciar(ConselhoTrimestre $conselho): RedirectResponse
    {
        $this->authorize('iniciar', $conselho);

        try {
            DB::beginTransaction();

            $this->conselhoService->iniciarConselho($conselho, auth()->user());

            DB::commit();

            return redirect()
                ->route('conselhos.show', $conselho)
                ->with('success', 'Conselho de classe iniciado com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()
                ->back()
                ->with('error', 'Erro ao iniciar conselho: ' . $e->getMessage());
        }
    }

    /**
     * Ajusta uma nota durante o conselho
     */
    public function ajustar(AjustarNotaConselhoRequest $request, ConselhoTrimestre $conselho): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $notaPre = Nota::findOrFail($validated['nota_id']);

            $resultado = $this->conselhoService->ajustarNota(
                $conselho,
                $notaPre,
                (float) $validated['novo_valor'],
                $validated['justificacao'],
                auth()->user(),
                $validated['motivo_reducao'] ?? null,
            );

            return redirect()
                ->route('conselhos.show', array_filter([
                    'conselho' => $conselho,
                    'disciplina_id' => $request->get('disciplina_id'),
                    'aluno' => $request->get('aluno'),
                    'apenas_falhas' => $request->get('apenas_falhas'),
                ]))
                ->with('success', sprintf(
                    'Nota ajustada com sucesso! %s: %.1f → %.1f',
                    $notaPre->tipo,
                    $notaPre->valor,
                    $resultado['nota_pos']->valor
                ));

        } catch (\Exception $e) {
            return redirect()
                ->route('conselhos.show', $conselho)
                ->withInput()
                ->with('error', 'Erro ao ajustar nota: ' . $e->getMessage());
        }
    }

    /**
     * Conclui o conselho (em_curso → concluido)
     */
    public function concluir(Request $request, ConselhoTrimestre $conselho): RedirectResponse
    {
        $this->authorize('concluir', $conselho);

        $validated = $request->validate([
            'observacoes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $this->conselhoService->concluirConselho(
                $conselho,
                auth()->user(),
                $validated['observacoes'] ?? null
            );

            DB::commit();

            return redirect()
                ->route('conselhos.index', ['turma_id' => $conselho->turma_id])
                ->with('success', 'Conselho de classe concluído com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()
                ->back()
                ->with('error', 'Erro ao concluir conselho: ' . $e->getMessage());
        }
    }

    /**
     * Reverte um ajuste específico (apenas se conselho ainda em_curso)
     */
    public function reverterAjuste(AjusteConselho $ajuste): RedirectResponse
    {
        $conselho = $ajuste->conselho;
        $this->authorize('ajustar', $conselho);

        try {
            DB::beginTransaction();

            $this->conselhoService->reverterAjuste($ajuste, auth()->user());

            DB::commit();

            return redirect()
                ->route('conselhos.show', $conselho)
                ->with('success', 'Ajuste revertido com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()
                ->back()
                ->with('error', 'Erro ao reverter ajuste: ' . $e->getMessage());
        }
    }

    /**
     * Retorna estatísticas do conselho em JSON (para AJAX)
     */
    public function estatisticas(ConselhoTrimestre $conselho)
    {
        $this->authorize('view', $conselho);

        $estatisticas = $this->conselhoService->obterEstatisticasConselho($conselho);

        return response()->json($estatisticas);
    }
}
