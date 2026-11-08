<?php

namespace App\Http\Controllers\Assiduidade;

use App\Http\Controllers\Controller;
use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\PresencaAula;
use App\Models\PresencaRegisto;
use App\Models\TurmaHorario;
use App\Services\PresencaService;
use App\Support\NotaLancamentoRules;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PresencaController extends Controller
{
    public function __construct(
        private PresencaService $presencas,
    ) {
        $this->middleware('auth');
        $this->middleware('can:presencas.ver')->only(['index', 'show', 'aula']);
        $this->middleware('can:presencas.lancar')->only(['store']);
        $this->middleware('can:presencas.justificar')->only(['justificar']);
    }

    public function index(Request $request): View
    {
        $data = $request->input('data', now()->toDateString());
        $anoLectivoId = (int) $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());
        $slots = $this->presencas->aulasDoDia($request->user(), $data, $anoLectivoId);

        $existentes = PresencaAula::query()
            ->whereDate('data', $data)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereIn('turma_horario_id', $slots->pluck('id')->filter())
            ->get()
            ->keyBy('turma_horario_id');

        return view('assiduidade.presencas.index', [
            'data' => $data,
            'slots' => $slots,
            'existentes' => $existentes,
            'trimestre' => Configuracao::getTrimestreActual(),
            'anoLectivoId' => $anoLectivoId,
            'anosLectivos' => AnoLectivo::getParaSelect(),
            'podeEditar' => Configuracao::podeEditarAnoLectivoById($anoLectivoId),
        ]);
    }

    public function aula(Request $request, TurmaHorario $horario): View
    {
        $data = $request->input('data', now()->toDateString());
        $this->presencas->assertPodeLancarAula($request->user(), $horario);

        $anoLectivoId = (int) ($horario->ano_lectivo_id ?: Configuracao::getAnoLectivoActualId());
        $podeEditar = Configuracao::podeEditarAnoLectivoById($anoLectivoId, $request->user());

        $aula = $this->presencas->aulaExistente($horario, $data);
        $alunos = NotaLancamentoRules::queryAlunosDaTurmaParaNotas(
            (int) $horario->turma_id,
            $anoLectivoId,
        )->get();

        $registos = $aula
            ? $aula->registos->keyBy('aluno_id')
            : collect();

        return view('assiduidade.presencas.aula', [
            'horario' => $horario->load(['turma', 'disciplina', 'professor', 'sala']),
            'data' => $data,
            'aula' => $aula,
            'alunos' => $alunos,
            'registos' => $registos,
            'estados' => PresencaRegisto::estados(),
            'anoLectivoId' => $anoLectivoId,
            'podeEditar' => $podeEditar,
        ]);
    }

    public function store(Request $request, TurmaHorario $horario): RedirectResponse
    {
        $anoLectivoId = (int) ($horario->ano_lectivo_id ?: Configuracao::getAnoLectivoActualId());
        if (! Configuracao::podeEditarAnoLectivoById($anoLectivoId, $request->user())) {
            return back()->with('error', 'Ano lectivo anterior — só o administrador pode alterar presenças.');
        }

        $data = $request->validate([
            'data' => ['required', 'date'],
            'observacao' => ['nullable', 'string', 'max:500'],
            'estados' => ['required', 'array'],
            'estados.*' => ['required', 'in:'.implode(',', array_keys(PresencaRegisto::estados()))],
        ]);

        $aula = $this->presencas->gravarChamada(
            $request->user(),
            $horario,
            $data['data'],
            $data['estados'],
            $data['observacao'] ?? null,
        );

        return redirect()
            ->route('presencas.aula', ['horario' => $horario->id, 'data' => Carbon::parse($data['data'])->toDateString()])
            ->with('success', 'Chamada guardada ('.$aula->registos()->count().' alunos).');
    }

    public function show(Request $request, PresencaAula $aula): View
    {
        if (! $this->presencas->podeVerTurma($request->user(), (int) $aula->turma_id, (int) $aula->disciplina_id)) {
            abort(403);
        }

        $aula->load(['turma', 'disciplina', 'professor', 'registos.aluno', 'registadoPor']);

        return view('assiduidade.presencas.show', [
            'aula' => $aula,
            'estados' => PresencaRegisto::estados(),
        ]);
    }

    public function justificar(Request $request, PresencaRegisto $registo): RedirectResponse
    {
        $registo->loadMissing('aula');
        $anoLectivoId = (int) ($registo->aula?->ano_lectivo_id ?: Configuracao::getAnoLectivoActualId());
        if (! Configuracao::podeEditarAnoLectivoById($anoLectivoId, $request->user())) {
            return back()->with('error', 'Ano lectivo anterior — só o administrador pode justificar faltas.');
        }

        $data = $request->validate([
            'justificacao' => ['required', 'string', 'max:500'],
        ]);

        $this->presencas->justificar($request->user(), $registo, $data['justificacao']);

        return back()->with('success', 'Falta justificada com sucesso.');
    }
}
