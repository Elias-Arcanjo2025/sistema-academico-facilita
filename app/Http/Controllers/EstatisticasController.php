<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Services\ConfiguracaoService;
use App\Services\EstatisticasService;
use App\Services\PdfExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstatisticasController extends Controller
{
    public function __construct(
        protected EstatisticasService $estatisticasService,
        protected ConfiguracaoService $configuracaoService,
        protected PdfExportService $pdfExportService,
    ) {}

    /**
     * Dashboard de estatísticas gerais
     * Dados calculados das notas oficiais do trimestre (MT / MFT2 / CA).
     */
    public function index(Request $request)
    {
        $this->authorize('viewStatistics', Aluno::class);

        $anoLectivo   = Configuracao::getAnoLectivoActual();
        $anoLectivoId = Configuracao::getAnoLectivoActualId();
        $periodo      = EstatisticasService::resolverPeriodo(
            $request->get('trimestre', Configuracao::getTrimestreActual() ?? 1)
        )['periodo'];
        $trimestre    = $periodo;

        // Stats baseadas nas notas oficiais do trimestre (MT / MFT2 / CA) ou anual (CA final)
        $statsGerais  = $this->estatisticasService->getEstatisticasGeraisPorNotas($anoLectivo, $periodo);
        $statsPorClasse = $this->estatisticasService->getEstatisticasPorClassePorNotas($anoLectivo, $periodo);

        // Preparar dados para gráficos
        $chartDados = $this->prepararDadosGraficos($statsGerais, $statsPorClasse);

        $trimestresOpts = [
            1 => 'I Trimestre',
            2 => 'II Trimestre',
            3 => 'III Trimestre',
            EstatisticasService::PERIODO_ANUAL => 'Anual (resultado final)',
        ];

        return view('estatisticas.index', compact(
            'anoLectivo',
            'anoLectivoId',
            'trimestre',
            'trimestresOpts',
            'statsGerais',
            'statsPorClasse',
            'chartDados'
        ));
    }

    /**
     * Exportar estatísticas para PDF (baseado em notas MT reais)
     */
    public function exportPdf(Request $request)
    {
        $this->authorize('viewStatistics', Aluno::class);

        $anoLectivo = Configuracao::getAnoLectivoActual();
        $periodo    = EstatisticasService::resolverPeriodo(
            $request->get('trimestre', Configuracao::getTrimestreActual() ?? 1)
        )['periodo'];
        $trimestre  = $periodo;

        $statsGerais    = $this->estatisticasService->getEstatisticasGeraisPorNotas($anoLectivo, $periodo);
        $statsPorClasse = $this->estatisticasService->getEstatisticasPorClassePorNotas($anoLectivo, $periodo);

        $instituicao = $this->configuracaoService->getInstituicao();
        $pdfConfig   = $this->configuracaoService->getPdfConfig();
        $gerado_em   = now();
        $logoBase64  = $this->resolveLogoBase64($instituicao['logo'] ?? null);

        $trimestresOpts = [
            1 => 'I',
            2 => 'II',
            3 => 'III',
            EstatisticasService::PERIODO_ANUAL => 'Anual',
        ];

        $pdf = $this->pdfExportService->loadView('exports.pdf.estatisticas-alunos', compact(
            'anoLectivo',
            'trimestre',
            'trimestresOpts',
            'statsGerais',
            'statsPorClasse',
            'instituicao',
            'pdfConfig',
            'gerado_em',
            'logoBase64'
        ), 'landscape');

        $periodoSlug = $periodo === EstatisticasService::PERIODO_ANUAL ? 'anual' : 't'.$trimestre;
        $filename = 'estatisticas-alunos-' . str_replace('/', '-', $anoLectivo) . '-' . $periodoSlug . '-' . now()->format('YmdHis') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Consolida situações acadêmicas
     */
    public function consolidar(Request $request)
    {
        $this->authorize('consolidarSituacoes', Aluno::class);

        $request->validate([
            'ano_lectivo_id' => 'required|exists:anos_lectivos,id',
            'confirmar' => 'required|accepted',
        ]);

        $resultado = $this->estatisticasService->consolidarSituacoesAcademicas(
            $request->ano_lectivo_id
        );

        return redirect()
            ->route('estatisticas.index')
            ->with('success', "Consolidação concluída! {$resultado['atualizados']} alunos actualizados ({$resultado['aprovados']} aprovados, {$resultado['reprovados']} reprovados).");
    }

    /**
     * Mapa de Aproveitamento e Estatística (formato oficial por trimestre)
     */
    public function mapaAproveitamento(Request $request)
    {
        $this->authorize('viewStatistics', Aluno::class);

        $anoLectivo  = Configuracao::getAnoLectivoActual();
        $periodo     = EstatisticasService::resolverPeriodo(
            $request->get('trimestre', Configuracao::getTrimestreActual() ?? 1)
        )['periodo'];
        $trimestre   = $periodo;
        $cursoId     = $request->filled('curso_id') ? (int) $request->get('curso_id') : null;

        $mapa   = $this->estatisticasService->getMapaAproveitamento($anoLectivo, $periodo, $cursoId);
        $cursos = Curso::activo()->orderBy('nome')->get();

        $trimestresOpts = [
            1 => 'I Trimestre',
            2 => 'II Trimestre',
            3 => 'III Trimestre',
            EstatisticasService::PERIODO_ANUAL => 'Anual (resultado final)',
        ];

        return view('estatisticas.mapa-aproveitamento', compact(
            'anoLectivo',
            'trimestre',
            'cursoId',
            'mapa',
            'cursos',
            'trimestresOpts'
        ));
    }

    /**
     * Exportar Mapa de Aproveitamento para PDF
     */
    public function mapaAproveitamentoPdf(Request $request)
    {
        $this->authorize('viewStatistics', Aluno::class);

        $anoLectivo = Configuracao::getAnoLectivoActual();
        $periodo    = EstatisticasService::resolverPeriodo($request->get('trimestre', 1))['periodo'];
        $trimestre  = $periodo;
        $cursoId    = $request->filled('curso_id') ? (int) $request->get('curso_id') : null;

        $mapa       = $this->estatisticasService->getMapaAproveitamento($anoLectivo, $periodo, $cursoId);
        $instituicao = $this->configuracaoService->getInstituicao();
        $gerado_em   = now();
        $tituloMapa  = EstatisticasService::tituloMapaPdf($periodo, $anoLectivo);

        // Coordenador do curso (se apenas 1 curso seleccionado)
        $coordenador = null;
        $cursoNome   = null;
        if ($cursoId) {
            $anoLectivoId = Configuracao::getAnoLectivoActualId();
            $coord = DB::table('coordenador_curso')
                ->join('users', 'coordenador_curso.user_id', '=', 'users.id')
                ->where('coordenador_curso.curso_id', $cursoId)
                ->where('coordenador_curso.ano_lectivo_id', $anoLectivoId)
                ->where('coordenador_curso.activo', true)
                ->select('users.name')
                ->first();
            $coordenador = $coord?->name;

            $curso     = Curso::find($cursoId);
            $cursoNome = $curso?->nome;
        }

        // Subdirector pedagógico
        $subdirector = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'subdiretor_pedagogico')
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->select('users.name')
            ->first();

        $trimestreRomano = match ($periodo) {
            EstatisticasService::PERIODO_ANUAL => 'Anual',
            default => [1 => 'I', 2 => 'II', 3 => 'III'][$periodo] ?? (string) $periodo,
        };

        $logoBase64 = $this->resolveLogoBase64($instituicao['logo'] ?? null);

        $pdf = $this->pdfExportService->loadView('exports.pdf.mapa-aproveitamento', compact(
            'anoLectivo',
            'periodo',
            'trimestre',
            'trimestreRomano',
            'tituloMapa',
            'mapa',
            'instituicao',
            'gerado_em',
            'coordenador',
            'cursoNome',
            'subdirector',
            'logoBase64'
        ), 'landscape');

        $periodoSlug = $periodo === EstatisticasService::PERIODO_ANUAL ? 'anual' : 't'.$periodo;
        $filename = 'mapa-aproveitamento-'.$periodoSlug.'-'.str_replace('/', '-', $anoLectivo).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Converte o logo da instituição para base64 (compatível com dompdf no Windows).
     */
    protected function resolveLogoBase64(?string $logoRelPath): ?string
    {
        if (empty($logoRelPath)) {
            return null;
        }

        $logoPath = storage_path('app/public/' . $logoRelPath);

        if (!file_exists($logoPath)) {
            return null;
        }

        $ext  = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png'       => 'image/png',
            'gif'       => 'image/gif',
            'svg'       => 'image/svg+xml',
            default     => 'image/jpeg',
        };

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
    }

    /**
     * Prepara dados para gráficos Chart.js (baseados em notas reais)
     */
    protected function prepararDadosGraficos(array $statsGerais, array $statsPorClasse): array
    {
        // Gráfico de rosca — distribuição por aproveitamento (baseado em notas MT)
        $chartSituacao = [
            'labels' => ['C/Aproveitamento', 'S/Aproveitamento', 'Desistentes', 'Transferidos'],
            'data'   => [
                $statsGerais['com_mf'],
                $statsGerais['sem_mf'],
                $statsGerais['desistentes_mf'],
                $statsGerais['transferidos_mf'],
            ],
            'colors' => ['#10b981', '#ef4444', '#f59e0b', '#06b6d4'],
        ];

        // Gráfico de barras — total de alunos por classe
        $labels = array_map(fn($c) => $c['nome'], $statsPorClasse['classes']);
        $chartPorClasse = [
            'labels' => array_values($labels),
            'data'   => array_values(array_map(fn($c) => $c['total'], $statsPorClasse['classes'])),
        ];

        // Gráfico de barras empilhadas — aproveitamento por classe
        $chartSituacaoPorClasse = [
            'labels'   => array_values($labels),
            'datasets' => [
                [
                    'label'           => 'C/Aproveitamento',
                    'data'            => array_values(array_map(fn($c) => $c['com_mf'], $statsPorClasse['classes'])),
                    'backgroundColor' => '#10b981',
                ],
                [
                    'label'           => 'S/Aproveitamento',
                    'data'            => array_values(array_map(fn($c) => $c['sem_mf'], $statsPorClasse['classes'])),
                    'backgroundColor' => '#ef4444',
                ],
                [
                    'label'           => 'Desistentes',
                    'data'            => array_values(array_map(fn($c) => $c['desistentes'], $statsPorClasse['classes'])),
                    'backgroundColor' => '#f59e0b',
                ],
            ],
        ];

        return [
            'situacao'          => $chartSituacao,
            'porClasse'         => $chartPorClasse,
            'situacaoPorClasse' => $chartSituacaoPorClasse,
        ];
    }
}
