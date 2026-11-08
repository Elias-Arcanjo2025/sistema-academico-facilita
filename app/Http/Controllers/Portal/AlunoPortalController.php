<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Configuracao;
use App\Models\CalendarioProva;
use App\Models\TurmaHorario;
use App\Models\TurmaHorarioConfig;
use App\Models\Nota;
use App\Services\AgendaService;
use App\Services\BoletimLayoutService;
use App\Services\ConfiguracaoService;
use App\Services\ExportService;
use App\Services\HorarioEscolarService;
use App\Services\MaterialAcademicoService;
use App\Services\PdfExportService;
use App\Services\Portal\AlunoPortalAcessoService;
use App\Services\Portal\AlunoPortalNotasService;
use App\Services\SaudeAlunoTurmaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class AlunoPortalController extends Controller
{
    public function __construct(
        protected ConfiguracaoService $configuracao,
        protected AlunoPortalNotasService $notasService,
        protected ExportService $exportService,
        protected AlunoPortalAcessoService $acessoService,
        protected PdfExportService $pdfExportService,
        protected BoletimLayoutService $boletimLayout,
        protected HorarioEscolarService $horarioService,
        protected \App\Services\PresencaService $presencaService,
        protected AgendaService $agendaService,
        protected MaterialAcademicoService $materialService,
    ) {
        $this->middleware(function ($request, $next) {
            if (! $request->user()?->hasRole('aluno') || ! $request->user()->aluno_id) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function dashboard(Request $request): View
    {
        $aluno = $request->user()->aluno()->with(['turma.curso', 'turma.classe', 'turma.turno'])->firstOrFail();
        $podeConsultarNotas = $this->acessoService->podeConsultarNotas($aluno);
        $mensagemBloqueio = $podeConsultarNotas ? null : $this->acessoService->mensagemBloqueio($aluno);

        $trimestre = $this->configuracao->getTrimestreActual();
        $notaMinima = $this->configuracao->getNotaMinimaAprovacao();
        $anoActualId = Configuracao::getAnoLectivoActualId();
        $anoLectivoActual = Configuracao::getAnoLectivoActual();

        $notasResumo = collect();
        $resumo = ['total_disciplinas' => 0, 'negativas' => 0];

        if ($podeConsultarNotas) {
            $filtros = $this->notasService->normalizarFiltros([
                'ano_lectivo_id' => $anoActualId,
                'periodo' => (string) $trimestre,
                'disciplina_id' => 'todas',
            ]);

            $notasLista = $this->notasService->consultar($aluno, $filtros);
            $notasResumo = $notasLista->groupBy(fn (Nota $n) => $n->disciplina?->nome ?? 'Disciplina');

            $resumo = [
                'total_disciplinas' => $notasResumo->count(),
                'negativas' => $aluno->notas()
                    ->whereNull('archived_at')
                    ->where('ano_lectivo_id', $anoActualId)
                    ->where('tipo', Nota::TIPO_CFD)
                    ->where('valor', '<', $notaMinima)
                    ->count(),
            ];

            $graficoNotas = $notasLista
                ->where('tipo', Nota::TIPO_MT)
                ->where('trimestre', $trimestre)
                ->map(fn (Nota $n) => [
                    'disciplina' => $n->disciplina?->nome ?? 'Disciplina',
                    'valor' => (float) $n->valor,
                ])
                ->values();
        } else {
            $graficoNotas = collect();
        }

        $examesEspeciais = $podeConsultarNotas
            ? $aluno->notas()
                ->whereNull('archived_at')
                ->where('ano_lectivo_id', $anoActualId)
                ->whereIn('tipo', [Nota::TIPO_RECUPERACAO, Nota::TIPO_RECURSO])
                ->with('disciplina')
                ->orderBy('disciplina_id')
                ->get()
            : collect();

        $recuperacaoPendente = $examesEspeciais->filter(fn (Nota $n) => (float) $n->valor < $notaMinima);

        $graficoNotas = $graficoNotas ?? collect();

        $saude = app(SaudeAlunoTurmaService::class)->indicadores($aluno, $anoLectivoActual);

        try {
            app(\App\Services\ProactiveNotificacaoDispatcher::class)->aoVisitarArea($request->user());
        } catch (\Throwable) {
        }

        return view('portal.aluno.dashboard', compact(
            'aluno',
            'trimestre',
            'notaMinima',
            'notasResumo',
            'resumo',
            'anoActualId',
            'anoLectivoActual',
            'podeConsultarNotas',
            'mensagemBloqueio',
            'recuperacaoPendente',
            'examesEspeciais',
            'graficoNotas',
            'saude',
        ));
    }

    public function notas(Request $request): View
    {
        $aluno = $request->user()->aluno()->with(['turma.curso'])->firstOrFail();
        $this->acessoService->assertPodeConsultarNotas($aluno);

        $notaMinima = $this->configuracao->getNotaMinimaAprovacao();

        $filtros = $this->notasService->normalizarFiltros($request->only([
            'ano_lectivo_id',
            'periodo',
            'disciplina_id',
        ]));

        if (! $request->has('periodo')) {
            $filtros['periodo'] = (string) $this->configuracao->getTrimestreActual();
        }

        $anosLectivos = $this->notasService->anosLectivosDisponiveis($aluno);
        $disciplinas = $this->notasService->disciplinasDisponiveis($aluno, $filtros['ano_lectivo_id']);
        $notas = $this->notasService->consultar($aluno, $filtros);
        $tabelaNotas = $this->notasService->agruparParaTabela($notas, $filtros['periodo'], $aluno);

        return view('portal.aluno.notas', compact(
            'aluno',
            'notas',
            'tabelaNotas',
            'notaMinima',
            'filtros',
            'anosLectivos',
            'disciplinas'
        ));
    }

    public function boletimPdf(Request $request): Response
    {
        $aluno = $request->user()->aluno()->firstOrFail();
        $this->acessoService->assertPodeConsultarNotas($aluno);

        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());
        $trimestre = $request->input('trimestre');

        $nomeAluno = Str::ascii(preg_replace('/\s+/', '_', trim($aluno->nome)));
        $nomeAluno = ucwords(strtolower($nomeAluno), '_');

        if ($trimestre !== null && $trimestre !== '') {
            $dados = $this->exportService->gerarDadosBoletimTrimestre($aluno, (int) $trimestre, $anoLectivo);
            $view = $this->boletimLayout->viewTrimestre();
            $filename = "boletim_{$nomeAluno}_{$trimestre}trim_{$anoLectivo}.pdf";
        } else {
            $dados = $this->exportService->gerarDadosBoletim($aluno, $anoLectivo);
            $view = $this->boletimLayout->viewAnual();
            $filename = "boletim_{$nomeAluno}_{$anoLectivo}.pdf";
        }

        $dados['instituicao'] = $this->configuracao->getInstituicao();
        $dados = array_merge($dados, $this->boletimLayout->dadosParaView());

        if (config('facilita.boletim_qr_enabled', true)) {
            $url = app(\App\Services\SignedDocumentService::class)->urlBoletim(
                $aluno,
                $anoLectivo,
                $trimestre !== null && $trimestre !== '' ? (int) $trimestre : null,
            );
            $dados['qr_verificacao_url'] = $url;
            $dados['qr_verificacao_data_uri'] = app(\App\Services\QrCodeService::class)->dataUri($url, 140);
        }

        $pdf = $this->pdfExportService->loadView($view, $dados);

        return $pdf->download($filename);
    }

    public function horario(Request $request): View
    {
        $aluno = $request->user()->aluno()->with(['turma.curso', 'turma.classe'])->firstOrFail();
        $anoLectivoId = Configuracao::getAnoLectivoActualId();

        $horarios = TurmaHorario::with(['disciplina', 'professor', 'sala'])
            ->where('turma_id', $aluno->turma_id)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->orderBy('hora_inicio')
            ->orderBy('dia_semana')
            ->get();

        $horarioConfig = TurmaHorarioConfig::paraTurma($aluno->turma_id, $anoLectivoId);
        $linhas = $this->horarioService->montarLinhasGrade($horarios, $horarioConfig);

        return view('portal.aluno.horario', compact('aluno', 'horarios', 'linhas', 'horarioConfig'));
    }

    public function horarioPdf(Request $request): Response|RedirectResponse
    {
        $aluno = $request->user()->aluno()->with('turma')->firstOrFail();
        $turma = $aluno->turma;

        if (! $turma) {
            return redirect()->route('portal.aluno.horario')
                ->with('error', 'Turma não encontrada.');
        }

        $dados = $this->horarioService->prepararDadosPdf($turma);

        if ($dados['horarios']->isEmpty()) {
            return redirect()->route('portal.aluno.horario')
                ->with('error', 'O horário ainda não foi publicado.');
        }

        $pdf = $this->pdfExportService->loadView('exports.pdf.horario-turma', $dados, 'portrait');
        $filename = 'horario_'.$turma->nome.'_'.Configuracao::getAnoLectivoActual().'.pdf';

        return $pdf->download($filename);
    }

    public function provas(Request $request): RedirectResponse
    {
        return redirect()->route('portal.aluno.calendario', ['tab' => 'provas']);
    }

    public function calendario(Request $request): View
    {
        $aluno = $request->user()->aluno()->with(['turma.curso'])->firstOrFail();
        $anoLectivoId = Configuracao::getAnoLectivoActualId();
        $tab = $request->get('tab', 'agenda') === 'provas' ? 'provas' : 'agenda';

        $eventos = $this->agendaService->paraAluno($aluno, $anoLectivoId);
        $provas = CalendarioProva::with('disciplina')
            ->where('turma_id', $aluno->turma_id)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->orderBy('data_prova')
            ->orderBy('hora_inicio')
            ->get();

        return view('portal.aluno.calendario', compact('aluno', 'eventos', 'provas', 'tab'));
    }

    public function materiais(Request $request): View
    {
        $aluno = $request->user()->aluno()->with(['turma.curso'])->firstOrFail();
        $materiais = $this->materialService
            ->queryParaAluno($aluno)
            ->paginate(20);

        return view('portal.aluno.materiais', compact('aluno', 'materiais'));
    }

    public function materialDownload(Request $request, \App\Models\MaterialAcademico $material): BinaryFileResponse
    {
        $aluno = $request->user()->aluno()->firstOrFail();

        if (! $this->materialService->podeDescarregarAluno($aluno, $material)) {
            abort(403);
        }

        return response()->download(
            $this->materialService->caminhoAbsoluto($material),
            $this->materialService->nomeDownload($material)
        );
    }

    public function assiduidade(Request $request): View
    {
        $aluno = $request->user()->aluno()->with(['turma'])->firstOrFail();
        $resumo = $this->presencaService->resumoAluno((int) $aluno->id);

        return view('portal.aluno.assiduidade', [
            'aluno' => $aluno,
            'totais' => $resumo['totais'],
            'recentes' => $resumo['recentes'],
            'estados' => \App\Models\PresencaRegisto::estados(),
        ]);
    }

    public function ocorrencias(Request $request): View
    {
        $aluno = $request->user()->aluno()->with(['turma'])->firstOrFail();
        $anoId = Configuracao::getAnoLectivoActualId();

        $ocorrencias = \App\Models\Ocorrencia::query()
            ->where('aluno_id', $aluno->id)
            ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
            ->latest('data')
            ->paginate(20);

        return view('portal.aluno.ocorrencias', [
            'aluno' => $aluno,
            'ocorrencias' => $ocorrencias,
            'tipos' => \App\Models\Ocorrencia::tipos(),
            'severidades' => \App\Models\Ocorrencia::severidades(),
        ]);
    }

    public function provasPdf(Request $request): Response|RedirectResponse
    {
        $aluno = $request->user()->aluno()->with('turma')->firstOrFail();
        $turma = $aluno->turma;

        if (! $turma) {
            return redirect()->route('portal.aluno.calendario', ['tab' => 'provas'])
                ->with('error', 'Turma não encontrada.');
        }

        $anoLectivoId = Configuracao::getAnoLectivoActualId();
        $provas = CalendarioProva::with('disciplina')
            ->where('turma_id', $turma->id)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->orderBy('data_prova')
            ->orderBy('hora_inicio')
            ->get();

        if ($provas->isEmpty()) {
            return redirect()->route('portal.aluno.calendario', ['tab' => 'provas'])
                ->with('error', 'Ainda não há provas agendadas para exportar.');
        }

        $dados = $this->horarioService->prepararDadosPdfCalendarioProvas($turma, $provas, $anoLectivoId);
        $pdf = $this->pdfExportService->loadView('exports.pdf.calendario-provas-turma', $dados, 'landscape');
        $filename = 'calendario_provas_'.$turma->nome.'_'.Configuracao::getAnoLectivoActual().'.pdf';

        return $pdf->download($filename);
    }

    public function perfil(Request $request): View
    {
        $aluno = $request->user()->aluno()->with(['turma.curso', 'turma.classe'])->firstOrFail();
        $user = $request->user();

        return view('portal.aluno.perfil', compact('aluno', 'user'));
    }

    public function actualizarPreferencias(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tema' => 'nullable|in:light,dark,system',
        ]);

        $user = $request->user();
        $preferencias = $user->preferencias ?? [];

        if (array_key_exists('tema', $validated) && $validated['tema']) {
            $preferencias['tema'] = $validated['tema'];
        }

        $user->forceFill(['preferencias' => $preferencias])->save();

        return back()->with('success', 'Preferências actualizadas.');
    }
}
