<?php

namespace App\Http\Controllers;

use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\DirectorTurma;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DirectorTurmaController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', DirectorTurma::class);

        $anoLectivoId = $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());
        $user = auth()->user();

        $query = DirectorTurma::with(['director', 'turma.curso'])
            ->where('ano_lectivo_id', $anoLectivoId);

        // Filtrar por permissão de coordenador de curso
        if ($user->hasAnyRole(['admin_full', 'subdiretor_pedagogico'])) {
            // Admin e Subdiretor veem todos
        } elseif ($user->hasRole('coordenador_curso')) {
            // Pegar cursos do coordenador (apenas associações ativas do ano lectivo selecionado)
            $cursosIds = $user->cursosComoCoordenador()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo_id', $anoLectivoId)
                ->pluck('cursos.id');

            $query->whereHas('turma', function ($q) use ($cursosIds) {
                $q->whereIn('curso_id', $cursosIds);
            });
        }

        $todas = (clone $query)->orderBy('created_at', 'desc')->get();

        $gruposDirectores = $todas->groupBy('user_id')->map(function ($items) {
            $primeiro = $items->first();

            return (object) [
                'director' => $primeiro->director,
                'ano_lectivo' => $primeiro->ano_lectivo,
                'registros' => $items->sortBy(fn ($d) => $d->turma->nome ?? '')->values()->map(fn ($d) => [
                    'id' => $d->id,
                    'registro' => $d,
                    'turma' => $d->turma,
                    'activo' => $d->activo,
                ])->all(),
                'total_turmas' => $items->count(),
                'activas' => $items->where('activo', true)->count(),
                'inactivas' => $items->where('activo', false)->count(),
            ];
        })->sortBy(fn ($g) => mb_strtolower($g->director->name ?? ''))->values();

        $perPage = 10;
        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $directores = new \Illuminate\Pagination\LengthAwarePaginator(
            $gruposDirectores->forPage($currentPage, $perPage),
            $gruposDirectores->count(),
            $perPage,
            $currentPage,
            ['path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath()]
        );
        $directores->appends($request->query());

        $totalRegistos = $todas->count();
        $totalActivos = $todas->where('activo', true)->count();
        $totalInactivos = $todas->where('activo', false)->count();
        $totalProfessores = $gruposDirectores->count();

        // Buscar anos lectivos da tabela centralizada
        $anosLectivos = AnoLectivo::orderBy('nome', 'desc')->pluck('nome', 'id');

        if ($anosLectivos->isEmpty()) {
            $anosLectivos = collect([Configuracao::getAnoLectivoActual() => Configuracao::getAnoLectivoActual()]);
        }

        // Verificar se pode editar este ano lectivo
        $anoLectivo = AnoLectivo::getNomeById($anoLectivoId) ?? Configuracao::getAnoLectivoActual();
        $podeEditar = Configuracao::podeEditarAnoLectivo($anoLectivo);

        // Lista de professores para o modal de substituição rápida
        $professoresDisponiveis = User::role(['professor', 'director_turma'])
            ->where('estado', 'activo')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('directores-turma.index', compact(
            'directores', 'anosLectivos', 'anoLectivoId', 'anoLectivo', 'podeEditar',
            'professoresDisponiveis', 'totalRegistos', 'totalActivos', 'totalInactivos', 'totalProfessores'
        ));
    }

    public function create(): View
    {
        $this->authorize('create', DirectorTurma::class);

        $anoLectivoActual = Configuracao::getAnoLectivoActual();

        // Verificar se pode editar o ano actual
        if (!Configuracao::podeEditarAnoLectivo($anoLectivoActual)) {
            abort(403, 'Não tem permissão para criar directores de turma. Apenas o admin_full pode fazer alterações em anos lectivos diferentes do actual.');
        }

        $professores = User::role(['professor', 'director_turma'])->orderBy('name')->get();
        $turmas = Turma::activo()->with('curso')->orderBy('nome')->get();
        $anosLectivos = AnoLectivo::orderBy('nome', 'desc')->pluck('nome', 'id');

        return view('directores-turma.create', compact('professores', 'turmas', 'anoLectivoActual', 'anosLectivos'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DirectorTurma::class);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'turma_ids' => ['required', 'array', 'min:1'],
            'turma_ids.*' => ['required', 'exists:turmas,id'],
            'ano_lectivo_id' => ['required', 'exists:anos_lectivos,id'],
            'activo' => ['boolean'],
        ], [
            'user_id.required' => 'O professor e obrigatorio.',
            'turma_ids.required' => 'Deve seleccionar pelo menos uma turma.',
            'turma_ids.array' => 'O formato das turmas e invalido.',
            'turma_ids.min' => 'Deve seleccionar pelo menos uma turma.',
            'turma_ids.*.exists' => 'Uma ou mais turmas seleccionadas sao invalidas.',
            'ano_lectivo_id.required' => 'O ano lectivo e obrigatorio.',
        ]);

        // Obter nome do ano para validação e compatibilidade
        $anoLectivo = AnoLectivo::find($validated['ano_lectivo_id']);
        $anoLectivoNome = $anoLectivo->nome;

        // Verificar se pode editar este ano lectivo
        if (!Configuracao::podeEditarAnoLectivo($anoLectivoNome)) {
            return back()->withInput()
                ->with('error', 'Não tem permissão para criar directores de turma neste ano lectivo. Apenas o admin_full pode fazer alterações em anos lectivos diferentes do actual.');
        }

        // Verificar quais turmas já têm director activo neste ano
        $turmasComDirector = DirectorTurma::whereIn('turma_id', $validated['turma_ids'])
            ->where('ano_lectivo_id', $validated['ano_lectivo_id'])
            ->where('activo', true)
            ->pluck('turma_id')
            ->toArray();

        if (!empty($turmasComDirector)) {
            $turmasNomes = Turma::whereIn('id', $turmasComDirector)->pluck('nome')->toArray();
            return back()->withInput()
                ->with('error', 'As seguintes turmas já têm um director activo para este ano lectivo: ' . implode(', ', $turmasNomes));
        }

        // Criar atribuição para cada turma seleccionada
        $turmasCriadas = 0;
        foreach ($validated['turma_ids'] as $turmaId) {
            DirectorTurma::create([
                'user_id' => $validated['user_id'],
                'turma_id' => $turmaId,
                'ano_lectivo_id' => $validated['ano_lectivo_id'],
                'ano_lectivo' => $anoLectivoNome,
                'activo' => $validated['activo'] ?? true,
            ]);
            $turmasCriadas++;
        }

        // Atribuir role se ainda nao tiver
        $user = User::find($validated['user_id']);
        if (!$user->hasRole('director_turma')) {
            $user->assignRole('director_turma');
        }

        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'user_id' => $validated['user_id'],
                'turmas_count' => $turmasCriadas,
                'turma_ids' => $validated['turma_ids'],
                'ano_lectivo_id' => $validated['ano_lectivo_id'],
            ])
            ->log('Novo director de turma atribuido a ' . $turmasCriadas . ' turma(s)');

        $mensagem = $turmasCriadas === 1
            ? 'Director de turma atribuido com sucesso a 1 turma.'
            : "Director de turma atribuido com sucesso a {$turmasCriadas} turmas.";

        return redirect()->route('directores-turma.index')
            ->with('success', $mensagem);
    }

    public function show(DirectorTurma $directoresTurma): View
    {
        $this->authorize('view', $directoresTurma);

        $directoresTurma->load(['director', 'turma.curso', 'turma.sala', 'turma.turno']);

        return view('directores-turma.show', ['director' => $directoresTurma]);
    }

    public function edit(DirectorTurma $directoresTurma): View
    {
        $this->authorize('update', $directoresTurma);

        // Verificar se pode editar este ano lectivo
        if (!Configuracao::podeEditarAnoLectivo($directoresTurma->ano_lectivo)) {
            abort(403, 'Não tem permissão para editar directores de turma deste ano lectivo. Apenas o admin_full pode fazer alterações em anos lectivos diferentes do actual.');
        }

        $professores = User::role(['professor', 'director_turma'])->orderBy('name')->get();
        $turmas = Turma::activo()->with('curso')->orderBy('nome')->get();
        $anoLectivoActual = Configuracao::getAnoLectivoActual();

        return view('directores-turma.edit', [
            'director' => $directoresTurma,
            'professores' => $professores,
            'turmas' => $turmas,
            'anoLectivoActual' => $anoLectivoActual,
        ]);
    }

    public function update(Request $request, DirectorTurma $directoresTurma): RedirectResponse
    {
        $this->authorize('update', $directoresTurma);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'turma_id' => ['required', 'exists:turmas,id'],
            'ano_lectivo_id' => ['required', 'exists:anos_lectivos,id'],
            'activo' => ['boolean'],
        ]);

        // Obter nome do ano para validação
        $anoLectivo = AnoLectivo::find($validated['ano_lectivo_id']);
        $validated['ano_lectivo'] = $anoLectivo->nome;

        // Verificar se pode editar este ano lectivo
        if (!Configuracao::podeEditarAnoLectivo($validated['ano_lectivo'])) {
            return back()->withInput()
                ->with('error', 'Não tem permissão para editar directores de turma neste ano lectivo. Apenas o admin_full pode fazer alterações em anos lectivos diferentes do actual.');
        }

        // Verificar duplicacao (excluindo o actual)
        $existe = DirectorTurma::where('turma_id', $validated['turma_id'])
            ->where('ano_lectivo_id', $validated['ano_lectivo_id'])
            ->where('activo', true)
            ->where('id', '!=', $directoresTurma->id)
            ->exists();

        if ($existe) {
            return back()->withInput()
                ->with('error', 'Esta turma ja tem outro director de turma activo para este ano lectivo.');
        }

        // Guardar utilizador anterior para ajustar roles se trocar
        $userAnteriorId = $directoresTurma->user_id;
        $userMudou = $userAnteriorId !== (int) $validated['user_id'];

        $directoresTurma->update($validated);

        // Atribuir role ao utilizador actual se ainda nao tiver
        $user = User::find($validated['user_id']);
        if (!$user->hasRole('director_turma')) {
            $user->assignRole('director_turma');
        }

        // Se trocou de utilizador, retirar role do antigo se já não dirigir nada
        if ($userMudou) {
            $this->revogarRoleSeSemAtribuicoes($userAnteriorId);
        }

        activity()
            ->causedBy(auth()->user())
            ->performedOn($directoresTurma)
            ->withProperties($validated)
            ->log('Director de turma actualizado');

        return redirect()->route('directores-turma.index')
            ->with('success', 'Director de turma actualizado com sucesso.');
    }

    public function destroy(DirectorTurma $directoresTurma): RedirectResponse
    {
        $this->authorize('delete', $directoresTurma);

        // Verificar se pode editar este ano lectivo
        if (!Configuracao::podeEditarAnoLectivo($directoresTurma->ano_lectivo)) {
            return back()
                ->with('error', 'Não tem permissão para remover directores de turma deste ano lectivo. Apenas o admin_full pode fazer alterações em anos lectivos diferentes do actual.');
        }

        $userIdRemovido = $directoresTurma->user_id;

        activity()
            ->causedBy(auth()->user())
            ->performedOn($directoresTurma)
            ->log('Director de turma removido');

        $directoresTurma->delete();

        // Retirar role se este utilizador já não dirigir nenhuma turma activa
        $this->revogarRoleSeSemAtribuicoes($userIdRemovido);

        return redirect()->route('directores-turma.index')
            ->with('success', 'Director de turma removido com sucesso.');
    }

    /**
     * Substitui rapidamente o utilizador de uma atribuição existente,
     * mantendo turma e ano lectivo intactos. Ajusta roles automaticamente.
     */
    public function substituir(Request $request, DirectorTurma $directoresTurma): RedirectResponse
    {
        $this->authorize('update', $directoresTurma);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ], [
            'user_id.required' => 'Deve seleccionar o novo director de turma.',
            'user_id.exists'   => 'O utilizador seleccionado não existe.',
        ]);

        if (!Configuracao::podeEditarAnoLectivo($directoresTurma->ano_lectivo)) {
            return back()->with('error', 'Não tem permissão para editar directores deste ano lectivo.');
        }

        $userAnteriorId = (int) $directoresTurma->user_id;
        $novoUserId     = (int) $validated['user_id'];

        if ($userAnteriorId === $novoUserId) {
            return back()->with('error', 'O novo director é o mesmo que o actual.');
        }

        $directoresTurma->update(['user_id' => $novoUserId]);

        $novoUser = User::find($novoUserId);
        if (!$novoUser->hasRole('director_turma')) {
            $novoUser->assignRole('director_turma');
        }

        $this->revogarRoleSeSemAtribuicoes($userAnteriorId);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($directoresTurma)
            ->withProperties([
                'user_id_anterior' => $userAnteriorId,
                'user_id_novo'     => $novoUserId,
                'turma_id'         => $directoresTurma->turma_id,
                'ano_lectivo'      => $directoresTurma->ano_lectivo,
            ])
            ->log('Director de turma substituído');

        return redirect()
            ->route('directores-turma.index', ['ano_lectivo_id' => $directoresTurma->ano_lectivo_id])
            ->with('success', 'Director de turma substituído com sucesso.');
    }

    /**
     * Retira o role 'director_turma' do utilizador se ele já não tiver
     * nenhuma atribuição activa em qualquer turma/ano lectivo.
     *
     * Não toca em utilizadores admin_full (que têm acesso total via Gate::before).
     */
    private function revogarRoleSeSemAtribuicoes(int $userId): void
    {
        $user = User::find($userId);
        if (!$user || !$user->hasRole('director_turma')) {
            return;
        }

        // admin_full tem acesso total via Gate — não mexer
        if ($user->hasRole('admin_full')) {
            return;
        }

        $aindaDirige = DirectorTurma::where('user_id', $userId)
            ->where('activo', true)
            ->exists();

        if (!$aindaDirige) {
            $user->removeRole('director_turma');

            activity()
                ->causedBy(auth()->user())
                ->performedOn($user)
                ->log('Role director_turma removido (sem atribuições activas)');
        }
    }
}
