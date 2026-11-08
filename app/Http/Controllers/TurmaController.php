<?php

namespace App\Http\Controllers;

use App\Http\Requests\TurmaRequest;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\Sala;
use App\Models\Turma;
use App\Models\Turno;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TurmaController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Turma::class);

        // Filtro de ano lectivo
        $anoLectivoId = $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());

        $query = Turma::with(['curso', 'classe', 'sala', 'turno', 'anoLectivo'])
            ->withCount('alunos')
            ->where('ano_lectivo_id', $anoLectivoId);

        // Filtros adicionais podem ser adicionados aqui no futuro
        // if ($request->filled('curso_id')) { $query->where('curso_id', $request->curso_id); }
        // if ($request->filled('classe_id')) { $query->where('classe_id', $request->classe_id); }

        $turmas = $query->orderBy('nome')
            ->paginate(10)
            ->withQueryString();

        // Dados para o filtro
        $anosLectivos = AnoLectivo::getParaSelect();
        $modoConsulta = Configuracao::isModoConsultaById($anoLectivoId);

        return view('turmas.index', compact('turmas', 'anosLectivos', 'anoLectivoId', 'modoConsulta'));
    }

    public function create(): View
    {
        $this->authorize('create', Turma::class);

        $cursos = Curso::activo()->orderBy('nome')->get();
        $classes = Classe::queryPermitidas()->get();
        $salas = Sala::activo()->orderBy('nome')->get();
        $turnos = Turno::activo()->orderBy('nome')->get();
        $anosLectivos = AnoLectivo::orderBy('nome', 'desc')->pluck('nome', 'id');

        return view('turmas.create', compact('cursos', 'classes', 'salas', 'turnos', 'anosLectivos'));
    }

    public function store(TurmaRequest $request): RedirectResponse
    {
        $this->authorize('create', Turma::class);

        Turma::create($request->validated());

        return redirect()->route('turmas.index')
            ->with('success', 'Turma criada com sucesso.');
    }

    public function show(Turma $turma): View
    {
        $this->authorize('view', $turma);

        $turma->load(['curso', 'sala', 'turno', 'alunos' => fn($q) => $q->orderBy('numero_ordem')]);

        return view('turmas.show', compact('turma'));
    }

    public function edit(Turma $turma): View
    {
        $this->authorize('update', $turma);

        $cursos = Curso::activo()->orderBy('nome')->get();
        $classes = Classe::queryPermitidas()->get();
        $salas = Sala::activo()->orderBy('nome')->get();
        $turnos = Turno::activo()->orderBy('nome')->get();
        $anosLectivos = AnoLectivo::orderBy('nome', 'desc')->pluck('nome', 'id');

        return view('turmas.edit', compact('turma', 'cursos', 'classes', 'salas', 'turnos', 'anosLectivos'));
    }

    public function update(TurmaRequest $request, Turma $turma): RedirectResponse
    {
        $this->authorize('update', $turma);

        $turma->update($request->validated());

        return redirect()->route('turmas.index')
            ->with('success', 'Turma actualizada com sucesso.');
    }

    public function destroy(Turma $turma): RedirectResponse
    {
        $this->authorize('delete', $turma);

        if ($turma->alunos()->exists()) {
            return redirect()->route('turmas.index')
                ->with('error', 'Não é possível eliminar esta turma porque tem alunos associados.');
        }

        $turma->delete();

        return redirect()->route('turmas.index')
            ->with('success', 'Turma eliminada com sucesso.');
    }
}
