<?php

namespace App\Http\Controllers\Pedagogico;

use App\Http\Controllers\Controller;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Disciplina;
use App\Models\Turma;
use App\Services\Pedagogico\DashboardPedagogicoService;
use App\Services\PdfExportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DashboardPedagogicoController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private DashboardPedagogicoService $dashboardService,
        private PdfExportService $pdfExportService,
    ) {}

    public function index(Request $request): View
    {
        abort_unless(auth()->user()->can('pedagogico.dashboard.ver'), 403);

        $filtros = $request->only(['ano_lectivo_id', 'trimestre', 'turma_id', 'disciplina_id']);
        $filtros['ano_lectivo_id'] = (int) ($filtros['ano_lectivo_id'] ?? Configuracao::getAnoLectivoActualId());

        $kpis = $this->dashboardService->obterKpis(auth()->user(), $filtros);

        $anoLectivoId = $filtros['ano_lectivo_id'];
        $anosLectivos = AnoLectivo::getParaSelect();
        $turmas = Turma::query()
            ->when(
                ! \App\Support\NotaLancamentoRules::anoLectivoEhHistorico($anoLectivoId),
                fn ($q) => $q->activo()
            )
            ->where('ano_lectivo_id', $anoLectivoId)
            ->orderBy('nome')
            ->get();
        $disciplinas = Disciplina::orderBy('nome')->get();

        return view('pedagogico.dashboard.index', compact('kpis', 'filtros', 'turmas', 'disciplinas', 'anosLectivos', 'anoLectivoId'));
    }

    public function exportPdf(Request $request): Response
    {
        abort_unless(auth()->user()->can('pedagogico.dashboard.ver'), 403);

        $filtros = $request->only(['ano_lectivo_id', 'trimestre', 'turma_id', 'disciplina_id']);
        $filtros['ano_lectivo_id'] = $filtros['ano_lectivo_id'] ?? Configuracao::getAnoLectivoActualId();

        $kpis = $this->dashboardService->obterKpis(auth()->user(), $filtros);

        $pdf = $this->pdfExportService->loadView('pedagogico.pdf.dashboard', compact('kpis', 'filtros'), 'landscape');

        return $pdf->download('dashboard_pedagogico.pdf');
    }
}
