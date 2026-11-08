<?php

namespace App\Http\Controllers;

use App\Models\CalendarioProva;
use App\Models\Configuracao;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\TurmaCalendarioProvaConfig;
use App\Services\CalendarioProvaIaGeradorService;
use App\Services\HorarioEscolarAccess;
use App\Services\HorarioEscolarService;
use App\Services\PdfExportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CalendarioProvaController extends Controller
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

        $turmaId = $request->input('turma_id', $turmas->first()?->id);
        $turma = $turmas->firstWhere('id', (int) $turmaId) ?? $turmas->first();

        $provas = collect();
        $disciplinas = collect();
        $calendarioConfig = null;

        if ($turma) {
            $provas = CalendarioProva::with('disciplina')
                ->where('turma_id', $turma->id)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->orderBy('data_prova')
                ->orderBy('slot')
                ->orderBy('hora_inicio')
                ->get();

            $calendarioConfig = TurmaCalendarioProvaConfig::paraTurma($turma->id, $anoLectivoId);

            $disciplinas = ProfessorDisciplinaTurma::with('disciplina')
                ->where('turma_id', $turma->id)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('activo', true)
                ->get()
                ->pluck('disciplina')
                ->filter()
                ->unique('id')
                ->sortBy('nome');
        }

        return view('calendario-provas.index', compact('turmas', 'turma', 'provas', 'disciplinas', 'calendarioConfig'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(HorarioEscolarAccess::podeGerir($request->user()), 403);

        $validated = $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
            'data_prova' => 'required|date',
            'slot' => 'required|integer|in:1,2',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fim' => 'nullable|date_format:H:i|after:hora_inicio',
            'tipo' => 'required|in:normal,recurso,melhoria,extraordinario',
            'periodo' => 'nullable|string|max:30',
            'observacoes' => 'nullable|string|max:500',
            'supervisor_nome' => 'nullable|string|max:120',
        ]);

        $turma = Turma::findOrFail($validated['turma_id']);
        abort_unless(HorarioEscolarAccess::turmaAcessivel($request->user(), $turma), 403);

        $prova = CalendarioProva::create([
            ...$validated,
            'ano_lectivo_id' => Configuracao::getAnoLectivoActualId(),
        ]);

        try {
            app(\App\Services\Portal\PortalNotificacaoService::class)->notificarProvaAgendada($prova);
        } catch (\Throwable) {
            // não bloquear o fluxo
        }

        return redirect()->route('calendario-provas.index', ['turma_id' => $turma->id])
            ->with('success', 'Prova adicionada ao calendário.');
    }

    public function destroy(Request $request, CalendarioProva $prova): RedirectResponse
    {
        abort_unless(HorarioEscolarAccess::podeGerir($request->user()), 403);
        abort_unless(HorarioEscolarAccess::turmaAcessivel($request->user(), $prova->turma), 403);

        $turmaId = $prova->turma_id;
        $prova->delete();

        return redirect()->route('calendario-provas.index', ['turma_id' => $turmaId])
            ->with('success', 'Prova removida do calendário.');
    }

    public function pdf(Request $request, Turma $turma): Response|RedirectResponse
    {
        abort_unless(HorarioEscolarAccess::podeVer($request->user()), 403);
        abort_unless(HorarioEscolarAccess::turmaAcessivel($request->user(), $turma), 403);

        $anoLectivoId = Configuracao::getAnoLectivoActualId();
        $provas = CalendarioProva::with('disciplina')
            ->where('turma_id', $turma->id)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->orderBy('data_prova')
            ->orderBy('slot')
            ->get();

        if ($provas->isEmpty()) {
            return redirect()->route('calendario-provas.index', ['turma_id' => $turma->id])
                ->with('error', 'Não há provas agendadas para exportar.');
        }

        $dados = $this->horarioService->prepararDadosPdfCalendarioProvas($turma, $provas, $anoLectivoId);
        $pdf = $this->pdfExportService->loadView('exports.pdf.calendario-provas-turma', $dados, 'landscape');
        $filename = 'calendario_provas_'.$turma->nome.'_'.Configuracao::getAnoLectivoActual().'.pdf';

        return $pdf->download($filename);
    }

    public function updateConfig(Request $request, Turma $turma): RedirectResponse
    {
        abort_unless(HorarioEscolarAccess::podeGerir($request->user()), 403);
        abort_unless(HorarioEscolarAccess::turmaAcessivel($request->user(), $turma), 403);

        $validated = $request->validate([
            'hora_1a_inicio' => 'required|date_format:H:i',
            'hora_1a_fim' => 'required|date_format:H:i|after:hora_1a_inicio',
            'hora_2a_inicio' => 'required|date_format:H:i',
            'hora_2a_fim' => 'required|date_format:H:i|after:hora_2a_inicio',
        ]);

        TurmaCalendarioProvaConfig::updateOrCreate(
            [
                'turma_id' => $turma->id,
                'ano_lectivo_id' => Configuracao::getAnoLectivoActualId(),
            ],
            $validated
        );

        return redirect()->route('calendario-provas.index', ['turma_id' => $turma->id])
            ->with('success', 'Horários das provas actualizados.');
    }

    public function gerarIa(Request $request, CalendarioProvaIaGeradorService $gerador): RedirectResponse
    {
        abort_unless(HorarioEscolarAccess::podeGerir($request->user()), 403);
        abort_unless($request->user()->can('calendario_provas.ia.gerar'), 403);

        $validated = $request->validate([
            'turma_id' => 'nullable|exists:turmas,id',
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after_or_equal:data_inicio',
            'periodo' => 'nullable|string|max:50',
        ]);

        $turmaId = $validated['turma_id'] ?? null;
        if ($turmaId) {
            $turma = Turma::findOrFail($turmaId);
            abort_unless(HorarioEscolarAccess::turmaAcessivel($request->user(), $turma), 403);
        }

        try {
            $resultado = $gerador->gerar(
                $validated['data_inicio'],
                $validated['data_fim'],
                $turmaId ? (int) $turmaId : null,
                Configuracao::getAnoLectivoActualId(),
                $validated['periodo'] ?? 'Período de Provas'
            );
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $msg = "Calendário gerado: {$resultado['criados']} prova(s) em {$resultado['turmas']} turma(s), "
            ."distribuídas por {$resultado['dias']} dia(s).";
        if (($resultado['supervisores'] ?? 0) > 0) {
            $msg .= " {$resultado['supervisores']} supervisor(es) atribuído(s) com base no horário anual.";
        }
        if (($resultado['pares_dificil_facil'] ?? 0) > 0) {
            $msg .= " {$resultado['pares_dificil_facil']} dia(s) com prova difícil emparelhada com disciplina fácil.";
        }
        if (! empty($resultado['conflitos'])) {
            $msg .= ' Algumas provas foram ignoradas por conflito.';
        }

        return redirect()->route('calendario-provas.index', ['turma_id' => $turmaId])
            ->with('success', $msg);
    }
}
