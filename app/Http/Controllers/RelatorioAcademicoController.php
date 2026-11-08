<?php

namespace App\Http\Controllers;

use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\Turma;
use App\Services\ConfiguracaoService;
use App\Services\PdfExportService;
use App\Services\RelatorioAcademicoService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RelatorioAcademicoController extends Controller
{
    public function __construct(
        private RelatorioAcademicoService $relatorioService,
        private ConfiguracaoService $configuracaoService,
        private PdfExportService $pdfExportService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('relatorios.academico');

        [$anoLectivoId, $anoLectivo, $trimestre, $cursoId, $turmaId, $cursos, $turmasFiltro, $periodo] = $this->resolveParams($request);

        $relatorio = $this->relatorioService->getRelatorio(
            $anoLectivoId,
            $anoLectivo,
            $trimestre,
            $cursoId,
            $turmaId,
        );

        return view('relatorios.academico', array_merge($relatorio, [
            'anoLectivoId' => $anoLectivoId,
            'anoLectivo' => $anoLectivo,
            'trimestre' => $trimestre,
            'periodo' => $periodo,
            'cursoId' => $cursoId,
            'turmaId' => $turmaId,
            'cursos' => $cursos,
            'turmasFiltro' => $turmasFiltro,
            'anosLectivos' => AnoLectivo::orderBy('nome', 'desc')->pluck('nome', 'id'),
            'trimestresOpts' => [1 => 'I Trimestre', 2 => 'II Trimestre', 3 => 'III Trimestre'],
        ]));
    }

    public function exportPdf(Request $request)
    {
        $this->authorize('relatorios.academico');

        [$anoLectivoId, $anoLectivo, $trimestre, $cursoId, $turmaId, $cursos, , $periodo] = $this->resolveParams($request);

        $relatorio = $this->relatorioService->getRelatorio(
            $anoLectivoId,
            $anoLectivo,
            $trimestre,
            $cursoId,
            $turmaId,
        );

        $instituicao = $this->configuracaoService->getInstituicao();
        $logoBase64 = $this->resolveLogoBase64($instituicao['logo'] ?? null);

        $cursoNome = $cursoId
            ? ($cursos->find($cursoId)?->nome ?? 'Todos os cursos')
            : 'Todos os cursos';

        $turmaNome = $turmaId
            ? (Turma::find($turmaId)?->nome ?? '')
            : '';

        $trimestresNomes = [1 => 'I Trimestre', 2 => 'II Trimestre', 3 => 'III Trimestre'];
        $trimestreNome = $trimestre !== null
            ? ($trimestresNomes[$trimestre] ?? "T{$trimestre}")
            : 'Anual (pauta T3)';

        $pdf = $this->pdfExportService->loadView('exports.pdf.relatorio-academico', array_merge($relatorio, [
            'anoLectivoId' => $anoLectivoId,
            'anoLectivo' => $anoLectivo,
            'trimestre' => $trimestre,
            'periodo' => $periodo,
            'trimestreNome' => $trimestreNome,
            'cursoNome' => $cursoNome,
            'turmaNome' => $turmaNome,
            'instituicao' => $instituicao,
            'logoBase64' => $logoBase64,
            'dataGeracao' => now()->format('d/m/Y H:i'),
        ]));

        $suffix = $trimestre !== null ? "t{$trimestre}" : 'anual';
        $filename = "relatorio-academico-{$suffix}-".now()->format('Ymd').'.pdf';

        return $pdf->download($filename);
    }

    private function resolveParams(Request $request): array
    {
        $anosLectivos = AnoLectivo::orderBy('nome', 'desc')->get();
        $anoLectivoNome = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivoNome)
            ?? Configuracao::getAnoLectivoActualId();
        $anoLectivo = AnoLectivo::find($anoLectivoId)?->nome ?? $anoLectivoNome;

        $periodo = $request->get('periodo', 'trimestral');
        $trimestre = $periodo === 'anual'
            ? null
            : (int) $request->get('trimestre', Configuracao::getTrimestreActual() ?? 1);

        $cursoId = $request->get('curso_id') ? (int) $request->get('curso_id') : null;
        $turmaId = $request->get('turma_id') ? (int) $request->get('turma_id') : null;
        $cursos = Curso::activo()->orderBy('nome')->get();

        $turmasFiltro = Turma::activo()
            ->where('ano_lectivo_id', $anoLectivoId)
            ->when($cursoId, fn ($q) => $q->where('curso_id', $cursoId))
            ->with('curso')
            ->orderBy('nome')
            ->get();

        return [$anoLectivoId, $anoLectivo, $trimestre, $cursoId, $turmaId, $cursos, $turmasFiltro, $periodo];
    }

    private function resolveLogoBase64(?string $logoRelPath): string
    {
        if (! $logoRelPath) {
            return '';
        }

        $fullPath = storage_path('app/public/'.$logoRelPath);

        if (! file_exists($fullPath)) {
            return '';
        }

        $mime = mime_content_type($fullPath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($fullPath));
    }
}
