<?php

namespace App\Http\Controllers;

use App\Models\AgendaEvento;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Turma;
use App\Services\AgendaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function __construct(
        private AgendaService $agenda,
    ) {
        $this->middleware('can:agenda.ver');
        $this->middleware('can:agenda.gerir')->only(['create', 'store', 'destroy']);
    }

    public function index(Request $request): View
    {
        $ano = (int) $request->get('ano', now()->year);
        $mes = (int) $request->get('mes', now()->month);
        $anoLectivoId = $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());

        $porDia = $this->agenda->listarPorMes($request->user(), $ano, $mes, $anoLectivoId ? (int) $anoLectivoId : null);
        $podeGerir = $request->user()->can('agenda.gerir');

        return view('agenda.index', [
            'porDia' => $porDia,
            'ano' => $ano,
            'mes' => $mes,
            'anoLectivoId' => $anoLectivoId,
            'anosLectivos' => AnoLectivo::orderByDesc('nome')->pluck('nome', 'id'),
            'podeGerir' => $podeGerir,
            'tipos' => AgendaEvento::tipos(),
        ]);
    }

    public function create(Request $request): View
    {
        $anoId = Configuracao::getAnoLectivoActualId();
        $turmaIds = $this->agenda->turmaIdsEscopo($request->user(), $anoId);
        $cursoIds = $this->agenda->cursoIdsEscopo($request->user(), $anoId);

        $turmas = Turma::query()
            ->when(
                ! $request->user()->temVisaoInstitucional() && ! $request->user()->hasRole('secretaria'),
                fn ($q) => $q->whereIn('id', $turmaIds->isEmpty() ? [-1] : $turmaIds)
            )
            ->where('ano_lectivo_id', $anoId)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $cursos = Curso::query()
            ->when(
                $request->user()->hasRole('coordenador_curso') && ! $request->user()->temVisaoInstitucional(),
                fn ($q) => $q->whereIn('id', $cursoIds->isEmpty() ? [-1] : $cursoIds)
            )
            ->where('activo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $soEscopo = ! $request->user()->temVisaoInstitucional() && ! $request->user()->hasRole('secretaria');
        $disciplinaIds = $this->agenda->disciplinaIdsEscopo($request->user(), $anoId);

        $disciplinas = Disciplina::query()
            ->when(
                $soEscopo,
                fn ($q) => $q->whereIn('id', $disciplinaIds->isEmpty() ? [-1] : $disciplinaIds)
            )
            ->where('activo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return view('agenda.create', [
            'turmas' => $turmas,
            'cursos' => $cursos,
            'disciplinas' => $disciplinas,
            'tipos' => AgendaEvento::tipos(),
            'visibilidades' => AgendaEvento::visibilidades(),
            'anosLectivos' => AnoLectivo::orderByDesc('nome')->pluck('nome', 'id'),
            'anoLectivoId' => $anoId,
            'soEscopo' => $soEscopo,
            'ehCoordDisciplina' => $request->user()->hasRole('coordenador_disciplina'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:200'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'tipo' => ['required', 'in:'.implode(',', array_keys(AgendaEvento::tipos()))],
            'inicia_em' => ['required', 'date'],
            'termina_em' => ['nullable', 'date', 'after_or_equal:inicia_em'],
            'ano_lectivo_id' => ['required', 'exists:anos_lectivos,id'],
            'visibilidade' => ['required', 'in:'.implode(',', array_keys(AgendaEvento::visibilidades()))],
            'turma_id' => ['nullable', 'exists:turmas,id'],
            'curso_id' => ['nullable', 'exists:cursos,id'],
            'disciplina_id' => ['nullable', 'exists:disciplinas,id'],
        ]);

        $this->agenda->criar($request->user(), $data);

        return redirect()->route('agenda.index')
            ->with('success', 'Evento adicionado à agenda.');
    }

    public function destroy(Request $request, AgendaEvento $agenda): RedirectResponse
    {
        $this->agenda->eliminar($request->user(), $agenda);

        return back()->with('success', 'Evento removido da agenda.');
    }
}
