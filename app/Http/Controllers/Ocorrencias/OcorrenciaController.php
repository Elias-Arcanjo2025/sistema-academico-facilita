<?php

namespace App\Http\Controllers\Ocorrencias;

use App\Http\Controllers\Controller;
use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Ocorrencia;
use App\Models\Turma;
use App\Services\OcorrenciaService;
use App\Support\NotaLancamentoRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OcorrenciaController extends Controller
{
    public function __construct(
        private OcorrenciaService $ocorrencias,
    ) {
        $this->middleware('auth');
        $this->middleware('can:ocorrencias.ver')->only(['index', 'show']);
        $this->middleware('can:ocorrencias.registar')->only(['create', 'store']);
        $this->middleware('can:ocorrencias.resolver')->only(['resolver']);
    }

    public function index(Request $request): View
    {
        $anoLectivoId = (int) $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());
        $filtros = array_merge(
            $request->only(['estado', 'turma_id', 'severidade', 'aluno_id']),
            ['ano_lectivo_id' => $anoLectivoId],
        );
        $lista = $this->ocorrencias->listar($request->user(), $filtros);

        $turmas = Turma::query()
            ->when(
                ! NotaLancamentoRules::anoLectivoEhHistorico($anoLectivoId),
                fn ($q) => $q->where('activo', true)
            )
            ->where('ano_lectivo_id', $anoLectivoId)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return view('assiduidade.ocorrencias.index', [
            'ocorrencias' => $lista,
            'filtros' => $filtros,
            'turmas' => $turmas,
            'tipos' => Ocorrencia::tipos(),
            'severidades' => Ocorrencia::severidades(),
            'anoLectivoId' => $anoLectivoId,
            'anosLectivos' => AnoLectivo::getParaSelect(),
            'podeEditar' => Configuracao::podeEditarAnoLectivoById($anoLectivoId),
        ]);
    }

    public function create(Request $request): View
    {
        $anoLectivoId = (int) $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());
        $podeEditar = Configuracao::podeEditarAnoLectivoById($anoLectivoId, $request->user());
        abort_unless($podeEditar, 403, 'Ano lectivo anterior — só o administrador pode registar ocorrências.');

        $turmaId = $request->integer('turma_id') ?: null;
        $alunos = collect();
        if ($turmaId && $this->ocorrencias->podeRegistarEmTurma($request->user(), $turmaId)) {
            $alunos = NotaLancamentoRules::queryAlunosDaTurmaParaNotas($turmaId, $anoLectivoId)
                ->get(['id', 'nome', 'numero_ordem', 'turma_id']);
        }

        $turmasQuery = Turma::query()
            ->when(
                ! NotaLancamentoRules::anoLectivoEhHistorico($anoLectivoId),
                fn ($q) => $q->where('activo', true)
            )
            ->where('ano_lectivo_id', $anoLectivoId)
            ->orderBy('nome');

        return view('assiduidade.ocorrencias.create', [
            'turmas' => $turmasQuery->get(['id', 'nome']),
            'alunos' => $alunos,
            'turmaId' => $turmaId,
            'tipos' => Ocorrencia::tipos(),
            'severidades' => Ocorrencia::severidades(),
            'anoLectivoId' => $anoLectivoId,
            'anosLectivos' => AnoLectivo::getParaSelect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'aluno_id' => ['required', 'exists:alunos,id'],
            'data' => ['required', 'date'],
            'tipo' => ['required', 'in:'.implode(',', array_keys(Ocorrencia::tipos()))],
            'severidade' => ['required', 'in:'.implode(',', array_keys(Ocorrencia::severidades()))],
            'descricao' => ['required', 'string', 'max:2000'],
            'medida' => ['nullable', 'string', 'max:2000'],
        ]);

        $ocorrencia = $this->ocorrencias->criar($request->user(), $data);

        return redirect()
            ->route('ocorrencias.show', $ocorrencia)
            ->with('success', 'Ocorrência registada.');
    }

    public function show(Request $request, Ocorrencia $ocorrencia): View
    {
        if (! $this->ocorrencias->podeVer($request->user(), $ocorrencia)) {
            abort(403);
        }

        $ocorrencia->load(['aluno', 'turma', 'registadoPor', 'resolvidoPor']);

        return view('assiduidade.ocorrencias.show', [
            'ocorrencia' => $ocorrencia,
            'tipos' => Ocorrencia::tipos(),
            'severidades' => Ocorrencia::severidades(),
        ]);
    }

    public function resolver(Request $request, Ocorrencia $ocorrencia): RedirectResponse
    {
        $data = $request->validate([
            'medida' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->ocorrencias->resolver($request->user(), $ocorrencia, $data['medida'] ?? null);

        return back()->with('success', 'Ocorrência marcada como resolvida.');
    }
}
