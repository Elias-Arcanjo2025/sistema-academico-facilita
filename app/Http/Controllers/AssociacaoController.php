<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssociacaoRequest;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssociacaoController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProfessorDisciplinaTurma::class);

        $anoLectivoId = $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());
        $professorId = $request->get('professor_id');
        $turmaId = $request->get('turma_id');

        $query = ProfessorDisciplinaTurma::with(['professor', 'disciplina.cursos', 'turma'])
            ->where('ano_lectivo_id', $anoLectivoId)
            ->orderBy('created_at', 'desc');

        if ($professorId) {
            $query->where('user_id', $professorId);
        }

        if ($turmaId) {
            $query->where('turma_id', $turmaId);
        }

        // Filtrar por permissoes
        $user = auth()->user();
        if ($user->hasAnyRole(['admin_full', 'subdiretor_pedagogico', 'coordenador_curso'])) {
            // Admin, Subdiretor e Coordenador veem todas - query inicial já está correta
        } elseif ($user->hasRole('director_turma')) {
            // Diretor de turma ve apenas associacoes das suas turmas
            $turmasIds = $user->turmasComoDirector()
                ->where('director_turma.activo', true)
                ->pluck('turmas.id');

            $query->whereIn('turma_id', $turmasIds);
        } else {
            // Professor ve apenas as suas associacoes
            $query->where('user_id', $user->id);
        }

        // Buscar todas as associações (sem paginação inicial) para agrupar
        $todasAssociacoes = $query->get();

        // Agrupar por professor_id + disciplina_id
        $associacoesAgrupadas = $todasAssociacoes->groupBy(function ($item) {
            return $item->user_id . '_' . $item->disciplina_id;
        })->map(function ($grupo) {
            $primeira = $grupo->first();
            return (object) [
                'id' => $primeira->id,
                'primeira_associacao' => $primeira,
                'professor' => $primeira->professor,
                'disciplina' => $primeira->disciplina,
                'ano_lectivo' => $primeira->ano_lectivo,
                'turmas' => $grupo->map(fn ($item) => [
                    'id' => $item->id,
                    'turma' => $item->turma,
                    'activo' => $item->activo,
                ])->values()->all(),
                'total_turmas' => $grupo->count(),
                'activas' => $grupo->where('activo', true)->count(),
                'inactivas' => $grupo->where('activo', false)->count(),
            ];
        })->sortBy(fn ($g) => mb_strtolower(($g->professor->name ?? '').'|'.($g->disciplina->nome ?? '')))
            ->values();

        // Paginar manualmente os grupos
        $perPage = 10;
        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $associacoes = new \Illuminate\Pagination\LengthAwarePaginator(
            $associacoesAgrupadas->forPage($currentPage, $perPage),
            $associacoesAgrupadas->count(),
            $perPage,
            $currentPage,
            ['path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath()]
        );

        // Preservar parâmetros de query na paginação
        $associacoes->appends($request->query());

        // Estatísticas totais (baseadas nos grupos)
        $totalAssociacoes = $todasAssociacoes->count(); // Total de associações individuais
        $totalGrupos = $associacoesAgrupadas->count(); // Total de grupos (professor+disciplina)
        $totalActivas = $todasAssociacoes->where('activo', true)->count();
        $totalInactivas = $todasAssociacoes->where('activo', false)->count();
        $totalProfessores = $todasAssociacoes->pluck('user_id')->unique()->count();

        $professores = User::role('professor')->orderBy('name')->get();
        $turmas = Turma::activo()->with('curso')->orderBy('nome')->get();

        // Buscar anos lectivos da tabela centralizada
        $anosLectivos = AnoLectivo::orderBy('nome', 'desc')->pluck('nome', 'id');

        if ($anosLectivos->isEmpty()) {
            $anosLectivos = collect([Configuracao::getAnoLectivoActual() => Configuracao::getAnoLectivoActual()]);
        }

        // Obter nome do ano lectivo actual (para exibição)
        $anoLectivo = AnoLectivo::getNomeById($anoLectivoId) ?? Configuracao::getAnoLectivoActual();

        return view('associacoes.index', compact(
            'associacoes',
            'professores',
            'turmas',
            'anosLectivos',
            'anoLectivoId',
            'anoLectivo',
            'professorId',
            'turmaId',
            'totalAssociacoes',
            'totalGrupos',
            'totalActivas',
            'totalInactivas',
            'totalProfessores'
        ));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ProfessorDisciplinaTurma::class);

        $professores = User::role('professor')->orderBy('name')->get();
        $turmas = Turma::activo()->with('curso')->orderBy('nome')->get();
        $anosLectivos = AnoLectivo::orderBy('nome', 'desc')->pluck('nome', 'id');
        $anoLectivoActual = Configuracao::getAnoLectivoActual();

        $turmaId = $request->get('turma_id');
        $disciplinas = collect();

        if ($turmaId) {
            $turma = Turma::find($turmaId);
            if ($turma) {
                $disciplinas = Disciplina::whereHas('cursos', function ($query) use ($turma) {
                        $query->where('cursos.id', $turma->curso_id);
                    })
                    ->activo()
                    ->orderBy('nome')
                    ->get();
            }
        }

        return view('associacoes.create', compact('professores', 'turmas', 'disciplinas', 'turmaId', 'anosLectivos', 'anoLectivoActual'));
    }

    public function store(AssociacaoRequest $request): RedirectResponse
    {
        $this->authorize('create', ProfessorDisciplinaTurma::class);

        $validated = $request->validated();
        $turmaIds = $validated['turma_ids'] ?? [];

        // Obter nome do ano para compatibilidade
        $anoLectivo = AnoLectivo::find($validated['ano_lectivo_id']);
        $validated['ano_lectivo'] = $anoLectivo->nome;

        $associacoesCriadas = 0;
        $turmasCriadas = [];
        $desactivadasAnteriores = 0;
        $activa = $validated['activo'] ?? true;

        foreach ($turmaIds as $turmaId) {
            if ($activa) {
                $desactivadasAnteriores += ProfessorDisciplinaTurma::desactivarOutrasActivas(
                    $validated['disciplina_id'],
                    $turmaId,
                    $validated['ano_lectivo_id'],
                    $validated['user_id']
                );
            }

            $associacao = ProfessorDisciplinaTurma::create([
                'user_id' => $validated['user_id'],
                'disciplina_id' => $validated['disciplina_id'],
                'turma_id' => $turmaId,
                'ano_lectivo' => $validated['ano_lectivo'],
                'ano_lectivo_id' => $validated['ano_lectivo_id'],
                'activo' => $activa,
            ]);

            $turma = Turma::find($turmaId);
            if ($turma) {
                $turmasCriadas[] = $turma->nome;
            }

            $associacoesCriadas++;
        }

        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'user_id' => $validated['user_id'],
                'disciplina_id' => $validated['disciplina_id'],
                'turma_ids' => $turmaIds,
                'ano_lectivo' => $validated['ano_lectivo'],
                'total_criadas' => $associacoesCriadas,
                'desactivadas_anteriores' => $desactivadasAnteriores,
            ])
            ->log('Novas associações professor-disciplina-turma criadas');

        $mensagem = $associacoesCriadas === 1
            ? 'Associação criada com sucesso.'
            : "{$associacoesCriadas} associações criadas com sucesso para as turmas: " . implode(', ', $turmasCriadas);

        if ($desactivadasAnteriores > 0) {
            $mensagem .= " {$desactivadasAnteriores} associação(ões) anterior(es) foram desactivadas (histórico de notas preservado).";
        }

        return redirect()->route('associacoes.index')
            ->with('success', $mensagem);
    }

    public function show(ProfessorDisciplinaTurma $associacao): View
    {
        $this->authorize('view', $associacao);

        $associacao->load(['professor', 'disciplina.cursos', 'turma.sala', 'turma.turno', 'turma.curso']);

        // Contar notas lancadas por esta associacao
        $notasCount = Nota::where('professor_id', $associacao->user_id)
            ->where('disciplina_id', $associacao->disciplina_id)
            ->where('turma_id', $associacao->turma_id)
            ->count();

        return view('associacoes.show', compact('associacao', 'notasCount'));
    }

    public function edit(ProfessorDisciplinaTurma $associacao): View
    {
        $this->authorize('update', $associacao);

        $professores = User::role('professor')->orderBy('name')->get();
        $turmas = Turma::activo()->with('curso')->orderBy('nome')->get();
        $anosLectivos = AnoLectivo::orderBy('nome', 'desc')->pluck('nome', 'id');
        $anoLectivoActual = Configuracao::getAnoLectivoActual();

        $turma = $associacao->turma;
        $disciplinas = Disciplina::whereHas('cursos', function ($query) use ($turma) {
                $query->where('cursos.id', $turma->curso_id);
            })
            ->activo()
            ->orderBy('nome')
            ->get();

        $notasCount = $associacao->contarNotas();

        return view('associacoes.edit', compact('associacao', 'professores', 'turmas', 'disciplinas', 'anosLectivos', 'anoLectivoActual', 'notasCount'));
    }

    public function update(AssociacaoRequest $request, ProfessorDisciplinaTurma $associacao): RedirectResponse
    {
        $this->authorize('update', $associacao);

        $validated = $request->validated();

        if ((int) $validated['user_id'] !== (int) $associacao->user_id && $associacao->contarNotas() > 0) {
            return back()->withInput()->with(
                'error',
                'Não pode alterar o professor desta associação porque existem notas lançadas. '
                .'Desactive esta associação e crie uma nova para o novo professor — as notas anteriores permanecem ligadas ao professor antigo.'
            );
        }

        // Obter nome do ano para compatibilidade
        $anoLectivo = AnoLectivo::find($validated['ano_lectivo_id']);
        $validated['ano_lectivo'] = $anoLectivo->nome;

        $associacao->update($validated);

        if ($associacao->activo && isset($validated['user_id'], $validated['disciplina_id'], $validated['turma_id'], $validated['ano_lectivo_id'])) {
            ProfessorDisciplinaTurma::desactivarOutrasActivas(
                (int) $validated['disciplina_id'],
                (int) $validated['turma_id'],
                (int) $validated['ano_lectivo_id'],
                (int) $validated['user_id']
            );
        }

        activity()
            ->causedBy(auth()->user())
            ->performedOn($associacao)
            ->withProperties($validated)
            ->log('Associacao professor-disciplina-turma actualizada');

        return redirect()->route('associacoes.index')
            ->with('success', 'Associacao actualizada com sucesso.');
    }

    public function destroy(ProfessorDisciplinaTurma $associacao): RedirectResponse
    {
        $this->authorize('delete', $associacao);

        // Verificar se existem notas lancadas
        if ($associacao->contarNotas() > 0) {
            return redirect()->route('associacoes.index')
                ->with('error', 'Não é possível eliminar esta associação porque existem notas lançadas. Desactive-a em vez de eliminar — o histórico será preservado.');
        }

        activity()
            ->causedBy(auth()->user())
            ->performedOn($associacao)
            ->log('Associacao professor-disciplina-turma eliminada');

        $associacao->delete();

        return redirect()->route('associacoes.index')
            ->with('success', 'Associacao eliminada com sucesso.');
    }

    public function getDisciplinas(Request $request)
    {
        $turmaId = $request->get('turma_id');

        if (!$turmaId) {
            return response()->json([]);
        }

        $turma = Turma::find($turmaId);
        if (!$turma) {
            return response()->json([]);
        }

        // Filtrar disciplinas por CURSO e CLASSE da turma
        $disciplinas = Disciplina::whereHas('cursos', function ($query) use ($turma) {
                $query->where('cursos.id', $turma->curso_id);
            })
            ->whereHas('classes', function ($query) use ($turma) {
                $query->where('classes.id', $turma->classe_id);
            })
            ->activo()
            ->orderBy('nome')
            ->get(['id', 'nome', 'codigo']);

        return response()->json($disciplinas);
    }

    /**
     * Visualizar grupo de associações (professor + disciplina)
     */
    public function showGrupo(Request $request): View
    {
        $professorId = $request->get('professor_id');
        $disciplinaId = $request->get('disciplina_id');
        $anoLectivo = $request->get('ano_lectivo');

        // Buscar todas as associações do grupo
        $associacoes = ProfessorDisciplinaTurma::with(['professor', 'disciplina.cursos', 'turma.curso', 'turma.sala', 'turma.turno'])
            ->where('user_id', $professorId)
            ->where('disciplina_id', $disciplinaId)
            ->where('ano_lectivo', $anoLectivo)
            ->orderBy('turma_id')
            ->get();

        // Verificar autorização na primeira associação
        if ($associacoes->isNotEmpty()) {
            $this->authorize('view', $associacoes->first());
        } else {
            abort(404, 'Nenhuma associação encontrada');
        }

        $professor = $associacoes->first()->professor;
        $disciplina = $associacoes->first()->disciplina;

        // Contar notas lançadas para todo o grupo
        $notasCount = Nota::where('professor_id', $professorId)
            ->where('disciplina_id', $disciplinaId)
            ->whereIn('turma_id', $associacoes->pluck('turma_id'))
            ->count();

        return view('associacoes.show-grupo', compact('associacoes', 'professor', 'disciplina', 'anoLectivo', 'notasCount'));
    }

    /**
     * Editar grupo de associações (professor + disciplina)
     */
    public function editGrupo(Request $request): View
    {
        $professorId = $request->get('professor_id');
        $disciplinaId = $request->get('disciplina_id');
        $anoLectivo = $request->get('ano_lectivo');

        // Buscar todas as associações do grupo
        $associacoes = ProfessorDisciplinaTurma::with(['professor', 'disciplina.cursos', 'turma'])
            ->where('user_id', $professorId)
            ->where('disciplina_id', $disciplinaId)
            ->where('ano_lectivo', $anoLectivo)
            ->orderBy('turma_id')
            ->get();

        // Verificar autorização na primeira associação
        if ($associacoes->isNotEmpty()) {
            $this->authorize('update', $associacoes->first());
        } else {
            abort(404, 'Nenhuma associação encontrada');
        }

        $professor = $associacoes->first()->professor;
        $disciplina = $associacoes->first()->disciplina;
        $anoLectivoId = $associacoes->first()->ano_lectivo_id;

        // Buscar IDs dos cursos da disciplina
        $cursoIds = $disciplina->cursos->pluck('id')->toArray();

        // Buscar todas as turmas disponíveis dos cursos que têm esta disciplina
        // Usando ano_lectivo_id em vez de ano_lectivo string para melhor precisão
        $turmasDisponiveis = Turma::with('curso')
            ->whereIn('curso_id', $cursoIds)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('activo', true)
            ->orderBy('nome')
            ->get();

        $notasPorTurma = $associacoes->mapWithKeys(
            fn ($a) => [$a->turma_id => $a->contarNotas()]
        );

        return view('associacoes.edit-grupo', compact(
            'associacoes', 'professor', 'disciplina', 'anoLectivo', 'anoLectivoId', 'turmasDisponiveis', 'notasPorTurma'
        ));
    }

    /**
     * Atualizar grupo de associações
     */
    public function updateGrupo(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'professor_id' => 'required|exists:users,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
            'ano_lectivo' => 'required|string',
            'ano_lectivo_id' => 'required|exists:anos_lectivos,id',
            'turmas' => 'required|array|min:1',
            'turmas.*.turma_id' => 'required|exists:turmas,id',
            'turmas.*.activo' => 'required|boolean',
            'turmas.*.associacao_id' => 'nullable|exists:professor_disciplina_turma,id',
        ]);

        $professorId = $validated['professor_id'];
        $disciplinaId = $validated['disciplina_id'];
        $anoLectivo = $validated['ano_lectivo'];
        $anoLectivoId = $validated['ano_lectivo_id'];

        // Buscar associações existentes com relação turma para mensagens de erro
        $associacoesExistentes = ProfessorDisciplinaTurma::with('turma')
            ->where('user_id', $professorId)
            ->where('disciplina_id', $disciplinaId)
            ->where('ano_lectivo', $anoLectivo)
            ->get()
            ->keyBy('id');

        // Verificar autorização
        if ($associacoesExistentes->isNotEmpty()) {
            $this->authorize('update', $associacoesExistentes->first());
        }

        $turmasIds = collect($validated['turmas'])->pluck('turma_id')->toArray();
        $actualizadas = 0;
        $criadas = 0;

        // Atualizar/criar associações
        foreach ($validated['turmas'] as $turmaData) {
            $associacaoId = $turmaData['associacao_id'] ?? null;

            if ($associacaoId && isset($associacoesExistentes[$associacaoId])) {
                $associacoesExistentes[$associacaoId]->update([
                    'activo' => $turmaData['activo'],
                ]);

                if ($turmaData['activo']) {
                    ProfessorDisciplinaTurma::desactivarOutrasActivas(
                        $disciplinaId,
                        (int) $turmaData['turma_id'],
                        $anoLectivoId,
                        $professorId
                    );
                }

                $actualizadas++;
            } else {
                if ($turmaData['activo']) {
                    ProfessorDisciplinaTurma::desactivarOutrasActivas(
                        $disciplinaId,
                        (int) $turmaData['turma_id'],
                        $anoLectivoId,
                        $professorId
                    );
                }

                ProfessorDisciplinaTurma::create([
                    'user_id' => $professorId,
                    'disciplina_id' => $disciplinaId,
                    'turma_id' => $turmaData['turma_id'],
                    'ano_lectivo' => $anoLectivo,
                    'ano_lectivo_id' => $anoLectivoId,
                    'activo' => $turmaData['activo'],
                ]);
                $criadas++;
            }
        }

        // Eliminar associações removidas
        $associacoesParaEliminar = $associacoesExistentes->filter(function($assoc) use ($turmasIds) {
            return !in_array($assoc->turma_id, $turmasIds);
        });

        $eliminadas = 0;
        $desactivadas = 0;
        foreach ($associacoesParaEliminar as $assoc) {
            if ($assoc->contarNotas() === 0) {
                $assoc->delete();
                $eliminadas++;
            } elseif ($assoc->activo) {
                $assoc->update(['activo' => false]);
                $desactivadas++;
            }
        }

        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'professor_id' => $professorId,
                'disciplina_id' => $disciplinaId,
                'ano_lectivo' => $anoLectivo,
                'actualizadas' => $actualizadas,
                'criadas' => $criadas,
                'eliminadas' => $eliminadas,
                'desactivadas' => $desactivadas,
            ])
            ->log('Grupo de associações atualizado');

        $mensagem = 'Grupo actualizado com sucesso: ';
        $detalhes = [];

        if ($actualizadas > 0) {
            $detalhes[] = "{$actualizadas} actualizada(s)";
        }
        if ($criadas > 0) {
            $detalhes[] = "{$criadas} criada(s)";
        }
        if ($eliminadas > 0) {
            $detalhes[] = "{$eliminadas} eliminada(s)";
        }
        if ($desactivadas > 0) {
            $detalhes[] = "{$desactivadas} desactivada(s) (notas preservadas)";
        }

        $mensagem .= implode(', ', $detalhes) ?: 'sem alterações.';

        return redirect()->route('associacoes.index')
            ->with('success', $mensagem);
    }

    /**
     * Deletar grupo completo de associações
     */
    public function deleteGrupo(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'professor_id' => 'required|exists:users,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
            'ano_lectivo' => 'required|string',
            'ano_lectivo_id' => 'required|exists:anos_lectivos,id',
        ]);

        $professorId = $validated['professor_id'];
        $disciplinaId = $validated['disciplina_id'];
        $anoLectivo = $validated['ano_lectivo'];

        // Buscar todas as associações do grupo com relação turma
        $associacoes = ProfessorDisciplinaTurma::with('turma')
            ->where('user_id', $professorId)
            ->where('disciplina_id', $disciplinaId)
            ->where('ano_lectivo', $anoLectivo)
            ->get();

        if ($associacoes->isEmpty()) {
            return redirect()->route('associacoes.index')
                ->with('error', 'Nenhuma associação encontrada para eliminar.');
        }

        // Verificar autorização em TODAS as associações
        foreach ($associacoes as $assoc) {
            try {
                $this->authorize('delete', $assoc);
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                return redirect()->route('associacoes.index')
                    ->with('error', 'Não tem permissão para eliminar uma ou mais associações deste grupo (turma: ' . $assoc->turma->nome . ').');
            }
        }

        $eliminadas = 0;
        $desactivadas = 0;

        foreach ($associacoes as $assoc) {
            if ($assoc->contarNotas() > 0) {
                if ($assoc->activo) {
                    $assoc->update(['activo' => false]);
                    $desactivadas++;
                }
            } else {
                $assoc->delete();
                $eliminadas++;
            }
        }

        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'professor_id' => $professorId,
                'disciplina_id' => $disciplinaId,
                'ano_lectivo' => $anoLectivo,
                'eliminadas' => $eliminadas,
                'desactivadas' => $desactivadas,
            ])
            ->log('Tentativa de eliminar grupo de associações');

        if ($eliminadas === 0 && $desactivadas === 0) {
            return redirect()->route('associacoes.index')
                ->with('info', 'Nenhuma alteração necessária — o grupo já estava inactivo ou vazio.');
        }

        $mensagem = [];
        if ($eliminadas > 0) {
            $mensagem[] = "{$eliminadas} associação(ões) eliminada(s)";
        }
        if ($desactivadas > 0) {
            $mensagem[] = "{$desactivadas} desactivada(s) (notas preservadas)";
        }

        return redirect()->route('associacoes.index')
            ->with('success', 'Grupo processado: ' . implode('; ', $mensagem) . '.');
    }
}
