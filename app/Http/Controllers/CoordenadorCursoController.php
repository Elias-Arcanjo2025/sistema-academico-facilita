<?php

namespace App\Http\Controllers;

use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\CoordenadorCurso;
use App\Models\Curso;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoordenadorCursoController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CoordenadorCurso::class);

        $anoLectivoId = $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());

        $coordenadores = CoordenadorCurso::with(['coordenador', 'curso'])
            ->where('ano_lectivo_id', $anoLectivoId)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $statsQuery = CoordenadorCurso::where('ano_lectivo_id', $anoLectivoId);
        $totalRegistos = (clone $statsQuery)->count();
        $totalActivos = (clone $statsQuery)->where('activo', true)->count();
        $totalInactivos = (clone $statsQuery)->where('activo', false)->count();
        $totalCursos = (clone $statsQuery)->distinct('curso_id')->count('curso_id');

        // Buscar anos lectivos da tabela centralizada
        $anosLectivos = AnoLectivo::orderBy('nome', 'desc')->pluck('nome', 'id');

        if ($anosLectivos->isEmpty()) {
            $anosLectivos = collect([Configuracao::getAnoLectivoActual() => Configuracao::getAnoLectivoActual()]);
        }

        // Verificar se pode editar este ano lectivo
        $anoLectivo = AnoLectivo::getNomeById($anoLectivoId) ?? Configuracao::getAnoLectivoActual();
        $podeEditar = Configuracao::podeEditarAnoLectivo($anoLectivo);

        // Lista de professores para o modal de substituição rápida
        $professoresDisponiveis = User::role(['professor', 'coordenador_curso'])
            ->where('estado', 'activo')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('coordenadores-curso.index', compact(
            'coordenadores', 'anosLectivos', 'anoLectivoId', 'anoLectivo', 'podeEditar',
            'professoresDisponiveis', 'totalRegistos', 'totalActivos', 'totalInactivos', 'totalCursos'
        ));
    }

    public function create(): View
    {
        $this->authorize('create', CoordenadorCurso::class);

        $anoLectivoActual = Configuracao::getAnoLectivoActual();

        // Verificar se pode editar o ano actual
        if (!Configuracao::podeEditarAnoLectivo($anoLectivoActual)) {
            abort(403, 'Não tem permissão para criar coordenadores de curso. Apenas o admin_full pode fazer alterações em anos lectivos diferentes do actual.');
        }

        $professores = User::role(['professor', 'coordenador_curso'])->orderBy('name')->get();
        $cursos = Curso::activo()->orderBy('nome')->get();
        $anosLectivos = AnoLectivo::orderBy('nome', 'desc')->pluck('nome', 'id');

        return view('coordenadores-curso.create', compact('professores', 'cursos', 'anoLectivoActual', 'anosLectivos'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CoordenadorCurso::class);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'curso_id' => ['required', 'exists:cursos,id'],
            'ano_lectivo_id' => ['required', 'exists:anos_lectivos,id'],
            'activo' => ['boolean'],
        ], [
            'user_id.required' => 'O professor e obrigatorio.',
            'curso_id.required' => 'O curso e obrigatorio.',
            'ano_lectivo_id.required' => 'O ano lectivo e obrigatorio.',
        ]);

        // Obter nome do ano para validação e compatibilidade
        $anoLectivo = AnoLectivo::find($validated['ano_lectivo_id']);
        $validated['ano_lectivo'] = $anoLectivo->nome;

        // Verificar se pode editar este ano lectivo
        if (!Configuracao::podeEditarAnoLectivo($validated['ano_lectivo'])) {
            return back()->withInput()
                ->with('error', 'Não tem permissão para criar coordenadores de curso neste ano lectivo. Apenas o admin_full pode fazer alterações em anos lectivos diferentes do actual.');
        }

        // Verificar se ja existe coordenador para este curso neste ano
        $existe = CoordenadorCurso::where('curso_id', $validated['curso_id'])
            ->where('ano_lectivo_id', $validated['ano_lectivo_id'])
            ->where('activo', true)
            ->exists();

        if ($existe) {
            return back()->withInput()
                ->with('error', 'Este curso ja tem um coordenador activo para este ano lectivo.');
        }

        CoordenadorCurso::create($validated);

        // Atribuir role se ainda nao tiver
        $user = User::find($validated['user_id']);
        if (!$user->hasRole('coordenador_curso')) {
            $user->assignRole('coordenador_curso');
        }

        activity()
            ->causedBy(auth()->user())
            ->withProperties($validated)
            ->log('Novo coordenador de curso atribuido');

        return redirect()->route('coordenadores-curso.index')
            ->with('success', 'Coordenador de curso atribuido com sucesso.');
    }

    public function show(CoordenadorCurso $coordenadoresCurso): View
    {
        $this->authorize('view', $coordenadoresCurso);

        $coordenadoresCurso->load(['coordenador', 'curso']);

        return view('coordenadores-curso.show', ['coordenador' => $coordenadoresCurso]);
    }

    public function edit(CoordenadorCurso $coordenadoresCurso): View
    {
        $this->authorize('update', $coordenadoresCurso);

        // Verificar se pode editar este ano lectivo
        if (!Configuracao::podeEditarAnoLectivo($coordenadoresCurso->ano_lectivo)) {
            abort(403, 'Não tem permissão para editar coordenadores de curso deste ano lectivo. Apenas o admin_full pode fazer alterações em anos lectivos diferentes do actual.');
        }

        $professores = User::role(['professor', 'coordenador_curso'])->orderBy('name')->get();
        $cursos = Curso::activo()->orderBy('nome')->get();
        $anoLectivoActual = Configuracao::getAnoLectivoActual();

        return view('coordenadores-curso.edit', [
            'coordenador' => $coordenadoresCurso,
            'professores' => $professores,
            'cursos' => $cursos,
            'anoLectivoActual' => $anoLectivoActual,
        ]);
    }

    public function update(Request $request, CoordenadorCurso $coordenadoresCurso): RedirectResponse
    {
        $this->authorize('update', $coordenadoresCurso);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'curso_id' => ['required', 'exists:cursos,id'],
            'ano_lectivo_id' => ['required', 'exists:anos_lectivos,id'],
            'activo' => ['boolean'],
        ]);

        // Obter nome do ano para validação
        $anoLectivo = AnoLectivo::find($validated['ano_lectivo_id']);
        $validated['ano_lectivo'] = $anoLectivo->nome;

        // Verificar se pode editar este ano lectivo
        if (!Configuracao::podeEditarAnoLectivo($validated['ano_lectivo'])) {
            return back()->withInput()
                ->with('error', 'Não tem permissão para editar coordenadores de curso neste ano lectivo. Apenas o admin_full pode fazer alterações em anos lectivos diferentes do actual.');
        }

        // Verificar duplicacao (excluindo o actual)
        $existe = CoordenadorCurso::where('curso_id', $validated['curso_id'])
            ->where('ano_lectivo_id', $validated['ano_lectivo_id'])
            ->where('activo', true)
            ->where('id', '!=', $coordenadoresCurso->id)
            ->exists();

        if ($existe) {
            return back()->withInput()
                ->with('error', 'Este curso ja tem outro coordenador activo para este ano lectivo.');
        }

        // Guardar utilizador anterior para comparar e ajustar roles depois do update
        $userAnteriorId = $coordenadoresCurso->user_id;
        $userMudou = $userAnteriorId !== (int) $validated['user_id'];

        $coordenadoresCurso->update($validated);

        // Atribuir role ao utilizador actual se ainda nao tiver
        $user = User::find($validated['user_id']);
        if (!$user->hasRole('coordenador_curso')) {
            $user->assignRole('coordenador_curso');
        }

        // Se trocou de utilizador, retirar role do antigo se já não coordenar nada
        if ($userMudou) {
            $this->revogarRoleSeSemAtribuicoes($userAnteriorId);
        }

        activity()
            ->causedBy(auth()->user())
            ->performedOn($coordenadoresCurso)
            ->withProperties($validated)
            ->log('Coordenador de curso actualizado');

        return redirect()->route('coordenadores-curso.index')
            ->with('success', 'Coordenador de curso actualizado com sucesso.');
    }

    public function destroy(CoordenadorCurso $coordenadoresCurso): RedirectResponse
    {
        $this->authorize('delete', $coordenadoresCurso);

        // Verificar se pode editar este ano lectivo
        if (!Configuracao::podeEditarAnoLectivo($coordenadoresCurso->ano_lectivo)) {
            return back()
                ->with('error', 'Não tem permissão para remover coordenadores de curso deste ano lectivo. Apenas o admin_full pode fazer alterações em anos lectivos diferentes do actual.');
        }

        $userIdRemovido = $coordenadoresCurso->user_id;

        activity()
            ->causedBy(auth()->user())
            ->performedOn($coordenadoresCurso)
            ->log('Coordenador de curso removido');

        $coordenadoresCurso->delete();

        // Retirar role se este utilizador já não coordenar nenhum curso activo
        $this->revogarRoleSeSemAtribuicoes($userIdRemovido);

        return redirect()->route('coordenadores-curso.index')
            ->with('success', 'Coordenador de curso removido com sucesso.');
    }

    /**
     * Substitui rapidamente o utilizador de uma atribuição existente,
     * mantendo curso e ano lectivo intactos. Ajusta roles automaticamente.
     */
    public function substituir(Request $request, CoordenadorCurso $coordenadoresCurso): RedirectResponse
    {
        $this->authorize('update', $coordenadoresCurso);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ], [
            'user_id.required' => 'Deve seleccionar o novo coordenador.',
            'user_id.exists'   => 'O utilizador seleccionado não existe.',
        ]);

        if (!Configuracao::podeEditarAnoLectivo($coordenadoresCurso->ano_lectivo)) {
            return back()->with('error', 'Não tem permissão para editar coordenadores deste ano lectivo.');
        }

        $userAnteriorId = (int) $coordenadoresCurso->user_id;
        $novoUserId     = (int) $validated['user_id'];

        if ($userAnteriorId === $novoUserId) {
            return back()->with('error', 'O novo coordenador é o mesmo que o actual.');
        }

        $coordenadoresCurso->update(['user_id' => $novoUserId]);

        $novoUser = User::find($novoUserId);
        if (!$novoUser->hasRole('coordenador_curso')) {
            $novoUser->assignRole('coordenador_curso');
        }

        $this->revogarRoleSeSemAtribuicoes($userAnteriorId);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($coordenadoresCurso)
            ->withProperties([
                'user_id_anterior' => $userAnteriorId,
                'user_id_novo'     => $novoUserId,
                'curso_id'         => $coordenadoresCurso->curso_id,
                'ano_lectivo'      => $coordenadoresCurso->ano_lectivo,
            ])
            ->log('Coordenador de curso substituído');

        return redirect()
            ->route('coordenadores-curso.index', ['ano_lectivo_id' => $coordenadoresCurso->ano_lectivo_id])
            ->with('success', 'Coordenador substituído com sucesso.');
    }

    /**
     * Retira o role 'coordenador_curso' do utilizador se ele já não tiver
     * nenhuma atribuição activa em qualquer curso/ano lectivo.
     *
     * Não toca em utilizadores admin_full (que têm acesso total via Gate::before).
     */
    private function revogarRoleSeSemAtribuicoes(int $userId): void
    {
        $user = User::find($userId);
        if (!$user || !$user->hasRole('coordenador_curso')) {
            return;
        }

        // admin_full tem acesso total via Gate — não mexer
        if ($user->hasRole('admin_full')) {
            return;
        }

        $aindaCoordena = CoordenadorCurso::where('user_id', $userId)
            ->where('activo', true)
            ->exists();

        if (!$aindaCoordena) {
            $user->removeRole('coordenador_curso');

            activity()
                ->causedBy(auth()->user())
                ->performedOn($user)
                ->log('Role coordenador_curso removido (sem atribuições activas)');
        }
    }
}
