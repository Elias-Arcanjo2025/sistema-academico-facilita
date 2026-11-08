<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class RelatorioController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:boletins.ver')->only(['boletins', 'boletinsLote']);
        $this->middleware('can:pautas.ver')->only(['pautas']);
        $this->middleware('can:alunos.ver')->only(['listaAlunos']);
    }

    /**
     * Pagina para exportar boletim individual de aluno
     */
    public function boletins(Request $request): View
    {
        $anoLectivo = $request->get('ano_lectivo', Configuracao::getAnoLectivoActual());
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);
        $turmaId = $request->get('turma_id');

        $turmas = $this->turmasAcessiveis($request->user(), $anoLectivoId);

        $alunos = collect();
        if ($turmaId && $turmas->contains('id', (int) $turmaId)) {
            $alunos = Aluno::where('turma_id', $turmaId)
                ->where('activo', true)
                ->orderBy('numero_ordem')
                ->get();
        }

        $anosLectivos = AnoLectivo::orderBy('nome', 'desc')->pluck('nome', 'id');

        return view('relatorios.boletins', compact(
            'turmas',
            'alunos',
            'anoLectivo',
            'turmaId',
            'anosLectivos'
        ));
    }

    /**
     * Pagina para exportar boletins em lote (toda a turma)
     */
    public function boletinsLote(Request $request): View
    {
        $anoLectivo = $request->get('ano_lectivo', Configuracao::getAnoLectivoActual());
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);

        $turmas = $this->turmasAcessiveis($request->user(), $anoLectivoId, true);

        $anosLectivos = AnoLectivo::orderBy('nome', 'desc')->pluck('nome', 'id');

        $estadoLote = null;
        $turmaIdSelecionada = $request->get('turma_id');
        if ($turmaIdSelecionada && auth()->check() && $turmas->contains('id', (int) $turmaIdSelecionada)) {
            $estadoLote = cache()->get("boletins_lote_".auth()->id()."_{$turmaIdSelecionada}");
        }

        return view('relatorios.boletins-lote', compact(
            'turmas',
            'anoLectivo',
            'anosLectivos',
            'estadoLote',
            'turmaIdSelecionada'
        ));
    }

    /**
     * Pagina para exportar pautas
     */
    public function pautas(Request $request): View
    {
        $anoLectivo = $request->get('ano_lectivo', Configuracao::getAnoLectivoActual());
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);
        $turmaId = $request->get('turma_id');

        $turmas = $this->turmasAcessiveis($request->user(), $anoLectivoId);

        $disciplinas = collect();
        if ($turmaId && $turmas->contains('id', (int) $turmaId)) {
            $turma = Turma::with('curso.disciplinas')->find($turmaId);
            if ($turma && $turma->curso) {
                $disciplinas = $turma->curso->disciplinas()->orderBy('nome')->get();
            }
        }

        $anosLectivos = AnoLectivo::orderBy('nome', 'desc')->pluck('nome', 'id');

        return view('relatorios.pautas', compact(
            'turmas',
            'disciplinas',
            'anoLectivo',
            'turmaId',
            'anosLectivos'
        ));
    }

    /**
     * Pagina para exportar lista de alunos
     */
    public function listaAlunos(Request $request): View
    {
        $anoLectivo = $request->get('ano_lectivo', Configuracao::getAnoLectivoActual());
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);

        $turmas = $this->turmasAcessiveis($request->user(), $anoLectivoId, true);

        $anosLectivos = AnoLectivo::orderBy('nome', 'desc')->pluck('nome', 'id');

        return view('relatorios.lista-alunos', compact(
            'turmas',
            'anoLectivo',
            'anosLectivos'
        ));
    }

    /**
     * @return Collection<int, Turma>
     */
    protected function turmasAcessiveis(User $user, ?int $anoLectivoId, bool $withAlunosCount = false): Collection
    {
        if ($user->temVisaoInstitucional() || $user->temAcessoSecretariaAlunos()) {
            $query = Turma::with('curso')
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('activo', true)
                ->orderBy('nome');
        } elseif ($user->hasRole('coordenador_curso')) {
            $cursosQuery = $user->coordenadorCursos()->where('activo', true);
            if ($anoLectivoId) {
                $noAno = (clone $cursosQuery)->where('ano_lectivo_id', $anoLectivoId)->pluck('curso_id');
                $cursosIds = $noAno->isNotEmpty()
                    ? $noAno
                    : $cursosQuery->pluck('curso_id');
            } else {
                $cursosIds = $cursosQuery->pluck('curso_id');
            }

            $query = Turma::with('curso')
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('activo', true)
                ->whereIn('curso_id', $cursosIds)
                ->orderBy('nome');
        } elseif ($user->hasRole('coordenador_disciplina')) {
            $ids = app(\App\Services\Pedagogico\DisciplinaCoordEscopoService::class)
                ->turmaIdsPorClasses($user, $anoLectivoId);

            $query = Turma::with('curso')
                ->whereIn('id', $ids->isEmpty() ? [-1] : $ids)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('activo', true)
                ->orderBy('nome');
        } elseif ($user->hasRole('director_turma')) {
            $ids = $user->turmasComoDirector()
                ->where('director_turma.activo', true)
                ->where('turmas.activo', true)
                ->pluck('turmas.id');

            $query = Turma::with('curso')
                ->whereIn('id', $ids)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('activo', true)
                ->orderBy('nome');
        } elseif ($user->turmasComoProfessor()->where('professor_disciplina_turma.activo', true)->exists()) {
            // Professor com boletins.ver: só turmas que lecciona
            $ids = $user->turmasComoProfessor()
                ->where('professor_disciplina_turma.activo', true)
                ->pluck('turmas.id');

            $query = Turma::with('curso')
                ->whereIn('id', $ids)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('activo', true)
                ->orderBy('nome');
        } else {
            return collect();
        }

        if ($withAlunosCount) {
            $query->with(['alunos' => function ($q) {
                $q->where('activo', true);
            }])->withCount(['alunos' => function ($q) {
                $q->where('activo', true);
            }]);
        }

        return $query->get();
    }
}
