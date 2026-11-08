<?php

namespace App\Http\Controllers;

use App\Models\Configuracao;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\TurmaHorario;
use App\Models\TurmaHorarioConfig;
use App\Models\User;
use App\Services\HorarioEscolarAccess;
use App\Services\HorarioEscolarService;
use App\Services\PdfExportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class HorarioTurmaController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected HorarioEscolarService $horarioService,
        protected PdfExportService $pdfExportService,
    ) {}

    public function index(Request $request): View
    {
        abort_unless(HorarioEscolarAccess::podeVer($request->user()), 403);

        $anoLectivoId = Configuracao::getAnoLectivoActualId();
        $turmas = HorarioEscolarAccess::turmasDisponiveis($request->user(), $anoLectivoId);

        $contagens = TurmaHorario::query()
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereIn('turma_id', $turmas->pluck('id'))
            ->selectRaw('turma_id, count(*) as total')
            ->groupBy('turma_id')
            ->pluck('total', 'turma_id');

        return view('horarios.index', compact('turmas', 'contagens'));
    }

    public function show(Request $request, Turma $turma): View
    {
        abort_unless(HorarioEscolarAccess::podeVer($request->user()), 403);
        abort_unless(HorarioEscolarAccess::turmaAcessivel($request->user(), $turma), 403);

        $anoLectivoId = Configuracao::getAnoLectivoActualId();
        $horarios = $this->carregarHorarios($turma, $anoLectivoId);
        $horarioConfig = TurmaHorarioConfig::paraTurma($turma->id, $anoLectivoId);
        $linhas = $this->horarioService->montarLinhasGrade($horarios, $horarioConfig);

        return view('horarios.show', compact('turma', 'horarios', 'linhas', 'horarioConfig'));
    }

    public function pdf(Request $request, Turma $turma): Response|RedirectResponse
    {
        abort_unless(HorarioEscolarAccess::podeVer($request->user()), 403);
        abort_unless(HorarioEscolarAccess::turmaAcessivel($request->user(), $turma), 403);

        $dados = $this->horarioService->prepararDadosPdf($turma);

        if ($dados['horarios']->isEmpty()) {
            return redirect()->route('horarios.show', $turma)
                ->with('error', 'Não há horário definido para exportar.');
        }

        $pdf = $this->pdfExportService->loadView('exports.pdf.horario-turma', $dados, 'portrait');
        $filename = 'horario_'.$turma->nome.'_'.Configuracao::getAnoLectivoActual().'.pdf';

        return $pdf->download($filename);
    }

    public function edit(Request $request, Turma $turma): View
    {
        abort_unless(HorarioEscolarAccess::podeGerir($request->user()), 403);
        abort_unless(HorarioEscolarAccess::turmaAcessivel($request->user(), $turma), 403);

        $anoLectivoId = Configuracao::getAnoLectivoActualId();
        $horarios = $this->carregarHorarios($turma, $anoLectivoId);

        $disciplinas = ProfessorDisciplinaTurma::query()
            ->with(['disciplina', 'professor'])
            ->where('turma_id', $turma->id)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('activo', true)
            ->get();

        $professores = User::whereIn('id', $disciplinas->pluck('user_id')->unique())->orderBy('name')->get();
        $horarioConfig = TurmaHorarioConfig::paraTurma($turma->id, $anoLectivoId);

        return view('horarios.edit', compact('turma', 'horarios', 'disciplinas', 'professores', 'horarioConfig'));
    }

    public function store(Request $request, Turma $turma): RedirectResponse
    {
        abort_unless(HorarioEscolarAccess::podeGerir($request->user()), 403);
        abort_unless(HorarioEscolarAccess::turmaAcessivel($request->user(), $turma), 403);

        $validated = $request->validate([
            'disciplina_id' => 'required|exists:disciplinas,id',
            'professor_id' => 'required|exists:users,id',
            'dia_semana' => 'required|integer|min:1|max:6',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fim' => 'required|date_format:H:i|after:hora_inicio',
        ]);

        $anoLectivoId = Configuracao::getAnoLectivoActualId();

        $conflitoProfessor = TurmaHorario::where('ano_lectivo_id', $anoLectivoId)
            ->where('professor_id', $validated['professor_id'])
            ->where('dia_semana', $validated['dia_semana'])
            ->where('hora_inicio', $validated['hora_inicio'])
            ->exists();

        if ($conflitoProfessor) {
            return back()->withInput()->with('error', 'Este professor já tem aula nesse dia e horário noutra turma.');
        }

        $conflitoTurma = TurmaHorario::where('ano_lectivo_id', $anoLectivoId)
            ->where('turma_id', $turma->id)
            ->where('dia_semana', $validated['dia_semana'])
            ->where('hora_inicio', $validated['hora_inicio'])
            ->exists();

        if ($conflitoTurma) {
            return back()->withInput()->with('error', 'Esta turma já tem aula atribuída nesse dia e horário.');
        }

        TurmaHorario::create([
            ...$validated,
            'turma_id' => $turma->id,
            'sala_id' => $turma->sala_id,
            'ano_lectivo_id' => $anoLectivoId,
        ]);

        $this->marcarHorarioComoRascunho($turma->id, $anoLectivoId);

        return redirect()->route('horarios.show', $turma)
            ->with('success', 'Horário adicionado com sucesso.');
    }

    public function destroy(Request $request, TurmaHorario $horario): RedirectResponse
    {
        abort_unless(HorarioEscolarAccess::podeGerir($request->user()), 403);
        abort_unless(HorarioEscolarAccess::turmaAcessivel($request->user(), $horario->turma), 403);

        $turma = $horario->turma;
        $anoLectivoId = (int) $horario->ano_lectivo_id;
        $horario->delete();
        $this->marcarHorarioComoRascunho($turma->id, $anoLectivoId);

        return redirect()->route('horarios.show', $turma)
            ->with('success', 'Horário removido.');
    }

    public function updateConfig(Request $request, Turma $turma): RedirectResponse
    {
        abort_unless(HorarioEscolarAccess::podeGerir($request->user()), 403);
        abort_unless(HorarioEscolarAccess::turmaAcessivel($request->user(), $turma), 403);

        $validated = $request->validate([
            'intervalo_activo' => 'nullable|boolean',
            'intervalo_apos_periodo' => 'required|integer|min:1|max:12',
            'intervalo_duracao_minutos' => 'required|integer|min:5|max:120',
            'intervalo_texto' => 'nullable|string|max:120',
        ]);

        $anoLectivoId = Configuracao::getAnoLectivoActualId();

        TurmaHorarioConfig::updateOrCreate(
            [
                'turma_id' => $turma->id,
                'ano_lectivo_id' => $anoLectivoId,
            ],
            [
                'intervalo_activo' => $request->boolean('intervalo_activo'),
                'intervalo_apos_periodo' => $validated['intervalo_apos_periodo'],
                'intervalo_duracao_minutos' => $validated['intervalo_duracao_minutos'],
                'intervalo_texto' => $validated['intervalo_texto'] ?: null,
                'status' => TurmaHorarioConfig::STATUS_RASCUNHO,
                'aprovado_por' => null,
                'aprovado_em' => null,
            ]
        );

        return redirect()->route('horarios.edit', $turma)
            ->with('success', 'Configuração do intervalo guardada.');
    }

    public function aprovar(Request $request, Turma $turma): RedirectResponse
    {
        abort_unless(HorarioEscolarAccess::podeAprovar($request->user()), 403);
        abort_unless(HorarioEscolarAccess::turmaAcessivel($request->user(), $turma), 403);

        $anoLectivoId = Configuracao::getAnoLectivoActualId();
        $config = TurmaHorarioConfig::paraTurma($turma->id, $anoLectivoId);
        if (! $config->exists) {
            $config->save();
        }

        $config->forceFill([
            'status' => TurmaHorarioConfig::STATUS_APROVADO,
            'aprovado_por' => $request->user()->id,
            'aprovado_em' => now(),
        ])->save();

        activity()
            ->performedOn($turma)
            ->causedBy($request->user())
            ->log('Horário escolar aprovado');

        return redirect()->route('horarios.show', $turma)
            ->with('success', 'Horário aprovado com sucesso.');
    }

    public function reabrir(Request $request, Turma $turma): RedirectResponse
    {
        abort_unless(HorarioEscolarAccess::podeAprovar($request->user()), 403);
        abort_unless(HorarioEscolarAccess::turmaAcessivel($request->user(), $turma), 403);

        $anoLectivoId = Configuracao::getAnoLectivoActualId();
        $config = TurmaHorarioConfig::paraTurma($turma->id, $anoLectivoId);
        if ($config->exists) {
            $config->marcarRascunho();
        }

        return redirect()->route('horarios.show', $turma)
            ->with('success', 'Horário reaberto para edição (rascunho).');
    }

    protected function marcarHorarioComoRascunho(int $turmaId, int $anoLectivoId): void
    {
        $config = TurmaHorarioConfig::firstOrCreate(
            ['turma_id' => $turmaId, 'ano_lectivo_id' => $anoLectivoId],
            TurmaHorarioConfig::padrao($turmaId, $anoLectivoId)->only([
                'intervalo_activo',
                'intervalo_apos_periodo',
                'intervalo_duracao_minutos',
                'intervalo_texto',
                'status',
            ])
        );

        if ($config->estaAprovado()) {
            $config->marcarRascunho();
        }
    }

    protected function carregarHorarios(Turma $turma, int $anoLectivoId)
    {
        return TurmaHorario::with(['disciplina', 'professor', 'sala'])
            ->where('turma_id', $turma->id)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio')
            ->get();
    }
}
