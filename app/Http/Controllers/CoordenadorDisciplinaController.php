<?php

namespace App\Http\Controllers;

use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\CoordenadorDisciplina;
use App\Models\Disciplina;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoordenadorDisciplinaController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CoordenadorDisciplina::class);

        $anoLectivoId = $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());

        $coordenadores = CoordenadorDisciplina::with(['coordenador', 'disciplina', 'classes'])
            ->where('ano_lectivo_id', $anoLectivoId)
            ->orderByDesc('activo')
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        $statsQuery = CoordenadorDisciplina::where('ano_lectivo_id', $anoLectivoId);
        $totalRegistos = (clone $statsQuery)->count();
        $totalActivos = (clone $statsQuery)->where('activo', true)->count();
        $totalInactivos = (clone $statsQuery)->where('activo', false)->count();
        $totalDisciplinas = (clone $statsQuery)->distinct('disciplina_id')->count('disciplina_id');

        $anosLectivos = AnoLectivo::orderBy('nome', 'desc')->pluck('nome', 'id');
        if ($anosLectivos->isEmpty()) {
            $anosLectivos = collect([Configuracao::getAnoLectivoActual() => Configuracao::getAnoLectivoActual()]);
        }

        $anoLectivo = AnoLectivo::getNomeById($anoLectivoId) ?? Configuracao::getAnoLectivoActual();
        $podeEditar = Configuracao::podeEditarAnoLectivo($anoLectivo);

        return view('coordenadores-disciplina.index', compact(
            'coordenadores',
            'anosLectivos',
            'anoLectivoId',
            'anoLectivo',
            'podeEditar',
            'totalRegistos',
            'totalActivos',
            'totalInactivos',
            'totalDisciplinas',
        ));
    }

    public function create(): View
    {
        $this->authorize('create', CoordenadorDisciplina::class);

        $anoLectivoActual = Configuracao::getAnoLectivoActual();

        if (! Configuracao::podeEditarAnoLectivo($anoLectivoActual)) {
            abort(403, 'Não tem permissão para criar coordenadores de disciplina neste ano lectivo.');
        }

        $professores = User::role(['professor', 'coordenador_disciplina'])
            ->where('estado', 'activo')
            ->orderBy('name')
            ->get();
        $disciplinas = Disciplina::where('activo', true)->orderBy('nome')->get();
        $classes = Classe::queryPermitidas()->get();
        $anosLectivos = AnoLectivo::orderBy('nome', 'desc')->pluck('nome', 'id');

        return view('coordenadores-disciplina.create', compact(
            'professores',
            'disciplinas',
            'classes',
            'anosLectivos',
            'anoLectivoActual',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CoordenadorDisciplina::class);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'disciplina_id' => ['required', 'exists:disciplinas,id'],
            'ano_lectivo_id' => ['required', 'exists:anos_lectivos,id'],
            'classe_ids' => ['required', 'array', 'min:1'],
            'classe_ids.*' => ['integer', 'exists:classes,id'],
            'activo' => ['boolean'],
        ], [
            'user_id.required' => 'O professor é obrigatório.',
            'disciplina_id.required' => 'A disciplina é obrigatória.',
            'ano_lectivo_id.required' => 'O ano lectivo é obrigatório.',
            'classe_ids.required' => 'Seleccione pelo menos uma classe.',
            'classe_ids.min' => 'Seleccione pelo menos uma classe.',
        ]);

        $ano = AnoLectivo::findOrFail($validated['ano_lectivo_id']);
        $classeIds = array_map('intval', $validated['classe_ids']);
        $activo = $request->boolean('activo', true);

        if (! Configuracao::podeEditarAnoLectivo($ano->nome)) {
            return back()->withInput()
                ->with('error', 'Não tem permissão para criar coordenadores neste ano lectivo.');
        }

        $existente = CoordenadorDisciplina::query()
            ->where('user_id', $validated['user_id'])
            ->where('disciplina_id', $validated['disciplina_id'])
            ->where('ano_lectivo_id', $validated['ano_lectivo_id'])
            ->first();

        if ($existente) {
            return back()->withInput()->with(
                'error',
                'Este professor já coordena esta disciplina neste ano lectivo. Remova a atribuição actual ou edite as classes numa nova atribuição após remover a anterior.'
            );
        }

        $overlap = CoordenadorDisciplina::query()
            ->where('user_id', $validated['user_id'])
            ->where('disciplina_id', $validated['disciplina_id'])
            ->where('ano_lectivo_id', $validated['ano_lectivo_id'])
            ->whereHas('classes', fn ($q) => $q->whereIn('classes.id', $classeIds))
            ->exists();

        if ($overlap) {
            return back()->withInput()->with(
                'error',
                'Este professor já coordena uma ou mais destas classes nesta disciplina neste ano lectivo.'
            );
        }

        $coordenador = CoordenadorDisciplina::create([
            'user_id' => $validated['user_id'],
            'disciplina_id' => $validated['disciplina_id'],
            'ano_lectivo_id' => $validated['ano_lectivo_id'],
            'ano_lectivo' => $ano->nome,
            'activo' => $activo,
        ]);
        $coordenador->classes()->sync($classeIds);

        $user = User::findOrFail($validated['user_id']);
        if (! $user->hasRole('coordenador_disciplina')) {
            $user->assignRole('coordenador_disciplina');
        }

        return redirect()->route('coordenadores-disciplina.index')
            ->with('success', 'Coordenador de disciplina atribuído com sucesso.');
    }

    public function destroy(CoordenadorDisciplina $coordenadoresDisciplina): RedirectResponse
    {
        $this->authorize('delete', $coordenadoresDisciplina);

        if (! Configuracao::podeEditarAnoLectivo($coordenadoresDisciplina->ano_lectivo)) {
            return back()->with('error', 'Não tem permissão para alterar atribuições deste ano lectivo.');
        }

        $userId = $coordenadoresDisciplina->user_id;
        $coordenadoresDisciplina->delete();

        $aindaCoordena = CoordenadorDisciplina::query()
            ->where('user_id', $userId)
            ->where('activo', true)
            ->exists();

        if (! $aindaCoordena) {
            $user = User::find($userId);
            if ($user?->hasRole('coordenador_disciplina')) {
                $user->removeRole('coordenador_disciplina');
            }
        }

        return redirect()->route('coordenadores-disciplina.index')
            ->with('success', 'Atribuição removida.');
    }
}
