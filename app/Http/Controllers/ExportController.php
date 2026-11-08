<?php

namespace App\Http\Controllers;

use App\Exports\BoletimExport;
use App\Exports\ListaAlunosExport;
use App\Exports\ListaTransicaoTurmaExport;
use App\Exports\MiniPautaAnualExport;
use App\Exports\MiniPautaExport;
use App\Exports\PautaFinalCompletaExport;
use App\Exports\PautaFinalDisciplinaExport;
use App\Exports\PautaFinalTurmaExport;
use App\Exports\PautaTrimestralCompletaExport;
use App\Jobs\GerarBoletinsEmLote;
use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\Turma;
use App\Services\BoletimLayoutService;
use App\Services\ConfiguracaoService;
use App\Services\ExportService;
use App\Services\ListaTransicaoTurmaService;
use App\Services\PdfExportService;
use App\Services\Portal\AlunoPortalAcessoService;
use App\Services\TurmaEscopoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function __construct(
        protected ExportService $exportService,
        protected ConfiguracaoService $configuracaoService,
        protected PdfExportService $pdfExportService,
        protected TurmaEscopoService $turmaEscopo,
        protected AlunoPortalAcessoService $portalAcesso,
        protected BoletimLayoutService $boletimLayout,
    ) {
        $this->middleware('can:boletins.ver')->only([
            'boletimPdf', 'boletimExcel', 'boletinsTurmaPdf',
            'boletinsEmLote', 'boletinsEmLoteEstado', 'boletinsEmLoteDownload',
        ]);
        $this->middleware('can:mini-pautas.ver')->only([
            'miniPautaPdf', 'miniPautaExcel',
            'miniPautaAnualPdf', 'miniPautaAnualExcel',
        ]);
        $this->middleware('can:pautas.ver')->only([
            'pautaFinalDisciplinaPdf', 'pautaFinalDisciplinaExcel',
            'pautaFinalTurmaPdf', 'pautaFinalTurmaExcel',
            'pautaFinalCompletaPdf', 'pautaFinalCompletaExcel',
            'pautaTrimestralCompletaExcel',
            'listaTransicaoTurmaExcel',
            'listaTransicaoTurmaPdf',
        ]);
        $this->middleware('can:alunos.ver')->only([
            'listaAlunosPdf', 'listaAlunosExcel',
        ]);
    }

    /**
     * Obter dados da instituicao para os templates
     */
    protected function getDadosInstituicao(): array
    {
        return $this->configuracaoService->getInstituicao();
    }

    /**
     * Sanitizar nome para usar em nome de ficheiro
     * Mantém primeira letra maiúscula, remove acentos
     */
    protected function sanitizeNomeAluno(string $nome): string
    {
        // Converter para ASCII (remove acentos mantendo maiúsculas/minúsculas)
        $nome = Str::ascii($nome);

        // Remover caracteres especiais, manter apenas letras, números e espaços
        $nome = preg_replace('/[^a-zA-Z0-9\s]/', '', $nome);

        // Substituir múltiplos espaços por underscore único
        $nome = preg_replace('/\s+/', '_', trim($nome));

        // Converter para Title Case (primeira letra de cada palavra maiúscula)
        $nome = ucwords(strtolower($nome), '_');

        return $nome;
    }

    /**
     * Exportar boletim individual em PDF
     */
    public function boletimPdf(Aluno $aluno, Request $request)
    {
        $this->turmaEscopo->assertAcessoAluno(auth()->user(), $aluno);

        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());
        $trimestre = $request->input('trimestre');

        // Sanitizar nome do aluno para usar no nome do arquivo
        $nomeAluno = $this->sanitizeNomeAluno($aluno->nome);

        // Se trimestre foi especificado, gerar boletim por trimestre
        if ($trimestre !== null && $trimestre !== '') {
            $dados = $this->exportService->gerarDadosBoletimTrimestre($aluno, (int)$trimestre, $anoLectivo);
            $view = $this->boletimLayout->viewTrimestre();
            $filename = "boletim_{$nomeAluno}_{$trimestre}trim_{$anoLectivo}.pdf";
        } else {
            // Caso contrário, gerar boletim anual (todos os trimestres)
            $dados = $this->exportService->gerarDadosBoletim($aluno, $anoLectivo);
            $view = $this->boletimLayout->viewAnual();
            $filename = "boletim_{$nomeAluno}_{$anoLectivo}.pdf";
        }

        $this->registarExportacao('boletim', 'pdf', $aluno, ['trimestre' => $trimestre]);

        $dados['instituicao'] = $this->getDadosInstituicao();
        $dados = array_merge($dados, $this->boletimLayout->dadosParaView());
        $dados = $this->anexarQrBoletim($dados, $aluno, $anoLectivo, $trimestre);

        $pdf = $this->pdfExportService->loadView($view, $dados);

        return $pdf->download($filename);
    }

    /**
     * Boletim PDF via URL assinada (sem sessão autenticada).
     */
    public function boletimPdfSigned(Aluno $aluno, Request $request)
    {
        if (! $this->portalAcesso->podeConsultarNotas($aluno)) {
            return response()->view('portal.aluno.bloqueado', [
                'aluno' => $aluno,
                'mensagem' => $this->portalAcesso->mensagemBloqueio($aluno),
            ], 403);
        }

        $anoLectivo = $request->query('ano_lectivo', Configuracao::getAnoLectivoActual());
        $trimestre = $request->query('trimestre');

        $nomeAluno = $this->sanitizeNomeAluno($aluno->nome);

        if ($trimestre !== null && $trimestre !== '') {
            $dados = $this->exportService->gerarDadosBoletimTrimestre($aluno, (int) $trimestre, $anoLectivo);
            $view = $this->boletimLayout->viewTrimestre();
            $filename = "boletim_{$nomeAluno}_{$trimestre}trim_{$anoLectivo}.pdf";
        } else {
            $dados = $this->exportService->gerarDadosBoletim($aluno, $anoLectivo);
            $view = $this->boletimLayout->viewAnual();
            $filename = "boletim_{$nomeAluno}_{$anoLectivo}.pdf";
        }

        $dados['instituicao'] = $this->getDadosInstituicao();
        $dados = array_merge($dados, $this->boletimLayout->dadosParaView());
        $dados = $this->anexarQrBoletim($dados, $aluno, $anoLectivo, $trimestre);

        $pdf = $this->pdfExportService->loadView($view, $dados);

        return $pdf->download($filename);
    }

    /**
     * Exportar boletins de toda a turma num único PDF — 2 por folha A4 (poupa papel).
     * Aceita ?trimestre=1|2|3 para boletim trimestral, ou nada para boletim anual.
     */
    public function boletinsTurmaPdf(Request $request)
    {
        $request->validate([
            'turma_id'   => 'required|exists:turmas,id',
            'ano_lectivo'=> 'nullable|string',
            'trimestre'  => 'nullable|integer|min:1|max:3',
        ]);

        $turma      = Turma::with(['curso', 'classe'])->findOrFail($request->input('turma_id'));
        $this->turmaEscopo->assertAcessoTurma(auth()->user(), (int) $turma->id);

        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());
        $trimestre  = $request->input('trimestre');
        $trimestre  = $trimestre !== null && $trimestre !== '' ? (int) $trimestre : null;

        $alunos = Aluno::where('turma_id', $turma->id)
            ->where('activo', true)
            ->orderBy('numero_ordem')
            ->orderBy('nome')
            ->get();

        if ($alunos->isEmpty()) {
            return back()->with('error', 'Esta turma não tem alunos activos.');
        }

        $notaMinima = (float) Configuracao::get('nota_minima_aprovacao', 10);

        $boletins = [];
        foreach ($alunos as $aluno) {
            if ($trimestre) {
                // Boletim trimestral — normalizar para a estrutura unificada usada pela view
                $dados = $this->exportService->gerarDadosBoletimTrimestre($aluno, $trimestre, $anoLectivo);
                $chaveResultado = $dados['chave_resultado'] ?? 'mt';

                $dados['disciplinas'] = collect($dados['notas'] ?? [])->map(function ($linha) use ($notaMinima, $chaveResultado) {
                    $valorResultado = $linha['valor_resultado'] ?? ($linha[$chaveResultado] ?? null);
                    $situacao = $valorResultado === null
                        ? 'pendente'
                        : ((float) $valorResultado >= $notaMinima ? 'aprovado' : 'reprovado');

                    $row = [
                        'disciplina' => (object) [
                            'nome' => $linha['disciplina'] ?? '-',
                            'codigo' => $linha['disciplina_codigo'] ?? null,
                        ],
                        'situacao' => $situacao,
                    ];

                    foreach ($linha as $key => $value) {
                        if (! in_array($key, ['disciplina', 'valor_resultado'], true)) {
                            $row[$key] = $value;
                        }
                    }

                    return $row;
                })->all();
            } else {
                $dados = $this->exportService->gerarDadosBoletim($aluno, $anoLectivo);

                // Calcular resumo do anual (igual estrutura que o trimestral)
                $totalDisc  = count($dados['disciplinas'] ?? []);
                $reprovadas = $dados['disciplinas_reprovadas'] ?? 0;
                $pendentes  = collect($dados['disciplinas'] ?? [])
                    ->where('situacao', 'pendente')->count();
                $aprovadas  = max(0, $totalDisc - $reprovadas - $pendentes);

                $dados['resumo'] = [
                    'total'       => $totalDisc,
                    'aprovadas'   => $aprovadas,
                    'reprovadas'  => $reprovadas,
                    'pendentes'   => $pendentes,
                    'media_geral' => $dados['media_geral'] ?? null,
                ];
            }

            // Marcar trimestre no array (a view distingue anual vs trimestral por este campo)
            $dados['trimestre'] = $trimestre;
            $boletins[] = $dados;
        }

        $instituicao = $this->getDadosInstituicao();
        $logoBase64  = $this->resolveLogoBase64($instituicao['logo'] ?? null);
        $layoutView = $this->boletimLayout->dadosParaView();
        $boletins = array_map(
            fn (array $b) => array_merge($b, $layoutView),
            $boletins
        );

        $this->registarExportacao('boletins-turma', 'pdf', null, [
            'turma_id'  => $turma->id,
            'trimestre' => $trimestre,
            'total'     => count($boletins),
        ]);

        $pdf = $this->pdfExportService->loadView('exports.pdf.boletins-turma-duplo', array_merge([
            'boletins'    => $boletins,
            'turma'       => $turma,
            'ano_lectivo' => $anoLectivo,
            'instituicao' => $instituicao,
            'logoBase64'  => $logoBase64,
        ], $layoutView), 'landscape');

        $sufixo   = $trimestre ? "T{$trimestre}" : 'anual';
        $filename = "boletins_{$turma->nome}_{$sufixo}_{$anoLectivo}.pdf";
        $filename = Str::ascii(preg_replace('/\s+/', '_', $filename));

        return $pdf->download($filename);
    }

    /**
     * Converte logo (path relativo em storage/app/public) para data URI base64.
     * Necessário porque o DomPDF não lida bem com paths Windows ou symlinks.
     */
    protected function resolveLogoBase64(?string $logoRelPath): string
    {
        if (!$logoRelPath) {
            return '';
        }

        $fullPath = storage_path('app/public/' . $logoRelPath);

        if (!file_exists($fullPath)) {
            return '';
        }

        $mime = mime_content_type($fullPath) ?: 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fullPath));
    }

    /**
     * Exportar boletim individual em Excel
     */
    public function boletimExcel(Aluno $aluno, Request $request): BinaryFileResponse
    {
        $this->turmaEscopo->assertAcessoAluno(auth()->user(), $aluno);

        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());
        $trimestre = $request->input('trimestre');

        // Sanitizar nome do aluno para usar no nome do arquivo
        $nomeAluno = $this->sanitizeNomeAluno($aluno->nome);

        // Se trimestre foi especificado, gerar boletim por trimestre
        if ($trimestre !== null && $trimestre !== '') {
            $dados = $this->exportService->gerarDadosBoletimTrimestre($aluno, (int)$trimestre, $anoLectivo);
            $filename = "boletim_{$nomeAluno}_{$trimestre}trim_{$anoLectivo}.xlsx";
        } else {
            // Caso contrário, gerar boletim anual (todos os trimestres)
            $dados = $this->exportService->gerarDadosBoletim($aluno, $anoLectivo);
            $filename = "boletim_{$nomeAluno}_{$anoLectivo}.xlsx";
        }

        $this->registarExportacao('boletim', 'excel', $aluno, ['trimestre' => $trimestre]);

        return Excel::download(new BoletimExport($dados), $filename);
    }

    /**
     * Exportar mini-pauta em PDF
     */
    public function miniPautaPdf(Request $request)
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
            'trimestre' => 'required|integer|min:1|max:3',
        ]);

        $this->turmaEscopo->assertAcessoTurma(
            auth()->user(),
            (int) $request->input('turma_id'),
            (int) $request->input('disciplina_id')
        );

        $dados = $this->exportService->gerarDadosMiniPauta(
            $request->input('turma_id'),
            $request->input('disciplina_id'),
            $request->input('trimestre'),
            $request->input('ano_lectivo')
        );

        $this->registarExportacao('mini-pauta', 'pdf', null, [
            'turma_id' => $request->input('turma_id'),
            'disciplina_id' => $request->input('disciplina_id'),
            'trimestre' => $request->input('trimestre'),
        ]);

        $dados['instituicao'] = $this->getDadosInstituicao();
        $dados['logoBase64'] = $this->resolveLogoBase64($dados['instituicao']['logo'] ?? null);

        $pdf = $this->pdfExportService->loadView('exports.pdf.mini-pauta', $dados);
        $pdf->setOptions([
            'isPhpEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => $this->pdfExportService->config()['fonts']['family'] ?? 'DejaVu Sans',
            'dpi' => 96,
        ]);

        $turma = $dados['turma']->nome;
        $disciplina = $dados['disciplina']->codigo ?? substr($dados['disciplina']->nome, 0, 10);
        $trimestre = $dados['trimestre'];
        $filename = "mini_pauta_{$turma}_{$disciplina}_T{$trimestre}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Exportar mini-pauta em Excel
     */
    public function miniPautaExcel(Request $request): BinaryFileResponse
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
            'trimestre' => 'required|integer|min:1|max:3',
        ]);

        $this->turmaEscopo->assertAcessoTurma(
            auth()->user(),
            (int) $request->input('turma_id'),
            (int) $request->input('disciplina_id')
        );

        $dados = $this->exportService->gerarDadosMiniPauta(
            $request->input('turma_id'),
            $request->input('disciplina_id'),
            $request->input('trimestre'),
            $request->input('ano_lectivo')
        );

        $this->registarExportacao('mini-pauta', 'excel', null, [
            'turma_id' => $request->input('turma_id'),
            'disciplina_id' => $request->input('disciplina_id'),
            'trimestre' => $request->input('trimestre'),
        ]);

        $dados['instituicao'] = $this->getDadosInstituicao();

        $turma = $dados['turma']->nome;
        $disciplina = $dados['disciplina']->codigo ?? substr($dados['disciplina']->nome, 0, 10);
        $trimestre = $dados['trimestre'];
        $filename = "mini_pauta_{$turma}_{$disciplina}_T{$trimestre}.xlsx";

        return Excel::download(new MiniPautaExport($dados), $filename);
    }

    /**
     * Exportar mini-pauta anual (1.º + 2.º + 3.º trimestre) em PDF
     */
    public function miniPautaAnualPdf(Request $request)
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
        ]);

        $this->turmaEscopo->assertAcessoTurma(
            auth()->user(),
            (int) $request->input('turma_id'),
            (int) $request->input('disciplina_id')
        );

        $dados = $this->exportService->gerarDadosMiniPautaAnual(
            (int) $request->input('turma_id'),
            (int) $request->input('disciplina_id'),
            $request->input('ano_lectivo')
        );

        $this->registarExportacao('mini-pauta-anual', 'pdf', null, [
            'turma_id' => $request->input('turma_id'),
            'disciplina_id' => $request->input('disciplina_id'),
        ]);

        $dados['instituicao'] = $this->getDadosInstituicao();
        $dados['logoBase64'] = $this->resolveLogoBase64($dados['instituicao']['logo'] ?? null);

        $pdf = $this->pdfExportService->loadView(
            'exports.pdf.mini-pauta-anual',
            $dados,
            'landscape'
        );
        $pdf->setOptions([
            'isPhpEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => $this->pdfExportService->config()['fonts']['family'] ?? 'DejaVu Sans',
            'dpi' => 96,
        ]);

        $turma = $dados['turma']->nome;
        $disciplina = $dados['disciplina']->codigo ?? substr($dados['disciplina']->nome, 0, 10);
        $filename = "mini_pauta_anual_{$turma}_{$disciplina}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Exportar mini-pauta anual (1.º + 2.º + 3.º trimestre) em Excel
     */
    public function miniPautaAnualExcel(Request $request): BinaryFileResponse
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
        ]);

        $this->turmaEscopo->assertAcessoTurma(
            auth()->user(),
            (int) $request->input('turma_id'),
            (int) $request->input('disciplina_id')
        );

        $dados = $this->exportService->gerarDadosMiniPautaAnual(
            (int) $request->input('turma_id'),
            (int) $request->input('disciplina_id'),
            $request->input('ano_lectivo')
        );

        $this->registarExportacao('mini-pauta-anual', 'excel', null, [
            'turma_id' => $request->input('turma_id'),
            'disciplina_id' => $request->input('disciplina_id'),
        ]);

        foreach ($dados['trimestres'] as $t => $bloco) {
            $dados['trimestres'][$t]['instituicao'] = $this->getDadosInstituicao();
        }

        $turma = $dados['turma']->nome;
        $disciplina = $dados['disciplina']->codigo ?? substr($dados['disciplina']->nome, 0, 10);
        $filename = "mini_pauta_anual_{$turma}_{$disciplina}.xlsx";

        return Excel::download(new MiniPautaAnualExport($dados['trimestres']), $filename);
    }

    /**
     * Exportar pauta final de disciplina em PDF
     */
    public function pautaFinalDisciplinaPdf(Request $request)
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
        ]);

        $this->turmaEscopo->assertAcessoTurma(
            auth()->user(),
            (int) $request->input('turma_id'),
            (int) $request->input('disciplina_id')
        );

        $dados = $this->exportService->gerarDadosPautaFinalDisciplina(
            $request->input('turma_id'),
            $request->input('disciplina_id'),
            $request->input('ano_lectivo')
        );

        $this->registarExportacao('pauta-final-disciplina', 'pdf', null, [
            'turma_id' => $request->input('turma_id'),
            'disciplina_id' => $request->input('disciplina_id'),
        ]);

        $dados['instituicao'] = $this->getDadosInstituicao();

        $pdf = $this->pdfExportService->loadView('exports.pdf.pauta-final-disciplina', $dados);

        $turma = $dados['turma']->nome;
        $disciplina = $dados['disciplina']->codigo ?? substr($dados['disciplina']->nome, 0, 10);
        $filename = "pauta_final_{$turma}_{$disciplina}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Exportar pauta final de disciplina em Excel
     */
    public function pautaFinalDisciplinaExcel(Request $request): BinaryFileResponse
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
        ]);

        $this->turmaEscopo->assertAcessoTurma(
            auth()->user(),
            (int) $request->input('turma_id'),
            (int) $request->input('disciplina_id')
        );

        $dados = $this->exportService->gerarDadosPautaFinalDisciplina(
            $request->input('turma_id'),
            $request->input('disciplina_id'),
            $request->input('ano_lectivo')
        );

        $this->registarExportacao('pauta-final-disciplina', 'excel', null, [
            'turma_id' => $request->input('turma_id'),
            'disciplina_id' => $request->input('disciplina_id'),
        ]);

        $turma = $dados['turma']->nome;
        $disciplina = $dados['disciplina']->codigo ?? substr($dados['disciplina']->nome, 0, 10);
        $filename = "pauta_final_{$turma}_{$disciplina}.xlsx";

        return Excel::download(new PautaFinalDisciplinaExport($dados), $filename);
    }

    /**
     * Exportar pauta final da turma em PDF
     */
    public function pautaFinalTurmaPdf(Request $request)
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
        ]);

        $this->turmaEscopo->assertAcessoTurma(auth()->user(), (int) $request->input('turma_id'));

        $dados = $this->exportService->gerarDadosPautaFinalTurma(
            $request->input('turma_id'),
            $request->input('ano_lectivo')
        );

        $this->registarExportacao('pauta-final-turma', 'pdf', null, [
            'turma_id' => $request->input('turma_id'),
        ]);

        $dados['instituicao'] = $this->getDadosInstituicao();

        $pdf = $this->pdfExportService->loadView('exports.pdf.pauta-final-turma', $dados, 'landscape');

        $turma = $dados['turma']->nome;
        $filename = "pauta_final_turma_{$turma}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Exportar pauta final da turma em Excel
     */
    public function pautaFinalTurmaExcel(Request $request): BinaryFileResponse
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
        ]);

        $this->turmaEscopo->assertAcessoTurma(auth()->user(), (int) $request->input('turma_id'));

        $dados = $this->exportService->gerarDadosPautaFinalTurma(
            $request->input('turma_id'),
            $request->input('ano_lectivo')
        );

        $this->registarExportacao('pauta-final-turma', 'excel', null, [
            'turma_id' => $request->input('turma_id'),
        ]);

        $turma = $dados['turma']->nome;
        $filename = "pauta_final_turma_{$turma}.xlsx";

        return Excel::download(new PautaFinalTurmaExport($dados), $filename);
    }

    /**
     * Exportar pauta final completa em PDF
     */
    public function pautaFinalCompletaPdf(Request $request)
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
        ]);

        $this->turmaEscopo->assertAcessoTurma(auth()->user(), (int) $request->input('turma_id'));

        $dados = $this->exportService->gerarDadosPautaFinalCompleta(
            $request->input('turma_id'),
            $request->input('ano_lectivo')
        );

        $this->registarExportacao('pauta-final-completa', 'pdf', null, [
            'turma_id' => $request->input('turma_id'),
        ]);

        $dados['instituicao'] = $this->getDadosInstituicao();

        $pdf = $this->pdfExportService->loadView('exports.pdf.pauta-final-completa', $dados, 'landscape');

        $turma = $dados['turma']->nome;
        $filename = "pauta_final_completa_{$turma}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Exportar pauta trimestral completa em Excel (1 trimestre, todas disciplinas).
     * Layout oficial idêntico ao modelo Excel "PAUTA FINAL DE APROVEITAMENTO"
     * (logos nos lados, nome instituição centralizado, ÁREA/CURSO, etc.).
     */
    public function pautaTrimestralCompletaExcel(Request $request): BinaryFileResponse
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'trimestre' => 'required|integer|in:1,2,3',
            'ano_lectivo' => 'nullable|string|max:9',
            'momento' => 'nullable|string|in:pre_conselho,pos_conselho',
        ]);

        $this->turmaEscopo->assertAcessoTurma(auth()->user(), (int) $request->input('turma_id'));

        $momento = \App\Support\PautaMomentoConselho::normalizar($request->input('momento'));

        $dados = $this->exportService->gerarDadosPautaTrimestralCompleta(
            (int) $request->input('turma_id'),
            (int) $request->input('trimestre'),
            $request->input('ano_lectivo'),
            $momento,
        );

        $this->registarExportacao('pauta-trimestral-completa', 'excel', null, [
            'turma_id' => $request->input('turma_id'),
            'trimestre' => $request->input('trimestre'),
            'momento' => $momento,
        ]);

        $dados['instituicao'] = $this->getDadosInstituicao();

        $turma = $dados['turma']->nome;
        $trim = $dados['trimestre'];
        $sufixoMomento = \App\Support\PautaMomentoConselho::isPre($momento) ? '_pre' : '_pos';
        $filename = "pauta_aproveitamento_{$turma}_T{$trim}{$sufixoMomento}.xlsx";

        return Excel::download(new PautaTrimestralCompletaExport($dados), $filename);
    }

    /**
     * Listas por resultado de transição (TRANSITA, N/TRANSITA, etc.) — PDF.
     */
    public function listaTransicaoTurmaPdf(
        Request $request,
        ListaTransicaoTurmaService $listaService,
    ) {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'trimestre' => 'required|integer|in:3',
            'ano_lectivo' => 'nullable|string|max:9',
            'resultado' => 'nullable|string|max:32',
        ]);

        $turmaId = (int) $request->input('turma_id');
        $this->turmaEscopo->assertAcessoTurma(auth()->user(), $turmaId);

        $export = $listaService->prepararExportacao(
            $turmaId,
            (int) $request->input('trimestre'),
            $request->input('ano_lectivo'),
            $request->input('resultado'),
        );

        $dados = $export['dados'];
        $filtro = $export['filtro'];

        $this->registarExportacao('lista-transicao-turma', 'pdf', null, [
            'turma_id' => $turmaId,
            'trimestre' => 3,
            'resultado' => $filtro,
        ]);

        $viewData = [
            'turma' => $dados['turma'],
            'trimestre' => $dados['trimestre'],
            'ano_lectivo' => $dados['ano_lectivo'],
            'classe_numero' => $dados['classe_numero'],
            'resumo' => $dados['resumo'],
            'gruposExibir' => $export['gruposExibir'],
            'filtro' => $filtro,
            'instituicao' => $this->getDadosInstituicao(),
            'gerado_em' => now(),
        ];

        $pdf = $this->pdfExportService->loadView('exports.pdf.lista-transicao-turma', $viewData, 'landscape');

        $turma = $dados['turma']->nome;
        $sufixo = $filtro ? '_'.str_replace(['/', ' '], '_', $filtro) : '';
        $filename = "lista_transicao_{$turma}_T3{$sufixo}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Listas por resultado de transição (TRANSITA, N/TRANSITA, etc.) — Excel.
     */
    public function listaTransicaoTurmaExcel(
        Request $request,
        ListaTransicaoTurmaService $listaService,
    ): BinaryFileResponse {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'trimestre' => 'required|integer|in:3',
            'ano_lectivo' => 'nullable|string|max:9',
            'resultado' => 'nullable|string|max:32',
        ]);

        $turmaId = (int) $request->input('turma_id');
        $this->turmaEscopo->assertAcessoTurma(auth()->user(), $turmaId);

        $export = $listaService->prepararExportacao(
            $turmaId,
            (int) $request->input('trimestre'),
            $request->input('ano_lectivo'),
            $request->input('resultado'),
        );

        $dados = $export['dados'];
        $filtro = $export['filtro'];
        $apenasGrupos = $filtro ? [$filtro] : null;

        $this->registarExportacao('lista-transicao-turma', 'excel', null, [
            'turma_id' => $turmaId,
            'trimestre' => 3,
            'resultado' => $filtro,
        ]);

        $turma = $dados['turma']->nome;
        $sufixo = $filtro ? '_'.str_replace(['/', ' '], '_', $filtro) : '';
        $filename = "lista_transicao_{$turma}_T3{$sufixo}.xlsx";

        return Excel::download(new ListaTransicaoTurmaExport($dados, $apenasGrupos), $filename);
    }

    /**
     * Exportar pauta final completa em Excel
     */
    public function pautaFinalCompletaExcel(Request $request): BinaryFileResponse
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
        ]);

        $this->turmaEscopo->assertAcessoTurma(auth()->user(), (int) $request->input('turma_id'));

        $dados = $this->exportService->gerarDadosPautaFinalCompleta(
            $request->input('turma_id'),
            $request->input('ano_lectivo')
        );

        $this->registarExportacao('pauta-final-completa', 'excel', null, [
            'turma_id' => $request->input('turma_id'),
        ]);

        $turma = $dados['turma']->nome;
        $filename = "pauta_final_completa_{$turma}.xlsx";

        return Excel::download(new PautaFinalCompletaExport($dados), $filename);
    }

    /**
     * Exportar lista de alunos em PDF
     */
    public function listaAlunosPdf(Request $request)
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
        ]);

        $this->turmaEscopo->assertAcessoTurma(auth()->user(), (int) $request->input('turma_id'));

        $dados = $this->exportService->gerarDadosListaAlunos(
            $request->input('turma_id')
        );

        $this->registarExportacao('lista-alunos', 'pdf', null, [
            'turma_id' => $request->input('turma_id'),
        ]);

        $dados['instituicao'] = $this->getDadosInstituicao();

        $pdf = $this->pdfExportService->loadView('exports.pdf.lista-alunos', $dados);

        $turma = $dados['turma']->nome;
        $filename = "lista_alunos_{$turma}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Livro de turma (PDF)
     */
    public function livroTurmaPdf(Request $request)
    {
        abort_unless(auth()->user()?->can('alunos.ver'), 403);

        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
        ]);

        $this->turmaEscopo->assertAcessoTurma(auth()->user(), (int) $request->input('turma_id'));

        $turma = \App\Models\Turma::with(['curso', 'classe', 'anoLectivo'])->findOrFail($request->input('turma_id'));
        $alunos = \App\Models\Aluno::query()
            ->where('turma_id', $turma->id)
            ->activo()
            ->orderByRaw('numero_ordem IS NULL')
            ->orderBy('numero_ordem')
            ->orderBy('nome')
            ->get();

        $this->registarExportacao('livro-turma', 'pdf', null, [
            'turma_id' => $turma->id,
        ]);

        $pdf = $this->pdfExportService->loadView('exports.pdf.livro-turma', [
            'turma' => $turma,
            'alunos' => $alunos,
            'instituicao' => $this->getDadosInstituicao(),
            'pdfConfig' => app(\App\Services\ConfiguracaoService::class)->getPdfConfig(),
        ]);

        return $pdf->download('livro_turma_'.\Illuminate\Support\Str::slug($turma->nome).'.pdf');
    }

    /**
     * Etiquetas dos alunos da turma (PDF)
     */
    public function etiquetasAlunosPdf(Request $request)
    {
        abort_unless(auth()->user()?->can('alunos.ver'), 403);

        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
        ]);

        $this->turmaEscopo->assertAcessoTurma(auth()->user(), (int) $request->input('turma_id'));

        $turma = \App\Models\Turma::findOrFail($request->input('turma_id'));
        $alunos = \App\Models\Aluno::query()
            ->where('turma_id', $turma->id)
            ->activo()
            ->orderByRaw('numero_ordem IS NULL')
            ->orderBy('numero_ordem')
            ->orderBy('nome')
            ->get();

        $this->registarExportacao('etiquetas-alunos', 'pdf', null, [
            'turma_id' => $turma->id,
        ]);

        $pdf = $this->pdfExportService->loadView('exports.pdf.etiquetas-alunos', [
            'turma' => $turma,
            'alunos' => $alunos,
        ]);

        return $pdf->download('etiquetas_'.\Illuminate\Support\Str::slug($turma->nome).'.pdf');
    }

    /**
     * Exportar lista de alunos em Excel
     */
    public function listaAlunosExcel(Request $request): BinaryFileResponse
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
        ]);

        $this->turmaEscopo->assertAcessoTurma(auth()->user(), (int) $request->input('turma_id'));

        $dados = $this->exportService->gerarDadosListaAlunos(
            $request->input('turma_id')
        );

        $this->registarExportacao('lista-alunos', 'excel', null, [
            'turma_id' => $request->input('turma_id'),
        ]);

        $turma = $dados['turma']->nome;
        $filename = "lista_alunos_{$turma}.xlsx";

        return Excel::download(new ListaAlunosExport($dados), $filename);
    }

    /**
     * Iniciar geracao em lote de boletins
     */
    public function boletinsEmLote(Request $request): RedirectResponse
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
        ]);

        $turmaId = (int) $request->input('turma_id');
        $this->turmaEscopo->assertAcessoTurma(auth()->user(), $turmaId);

        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());
        $userId = auth()->id();

        // Verificar se ja existe um job em processamento
        $cacheKey = "boletins_lote_{$userId}_{$turmaId}";
        if (cache()->has($cacheKey)) {
            $existente = cache()->get($cacheKey);
            if (isset($existente['ficheiro']) && Storage::disk('local')->exists($existente['ficheiro'])) {
                return back()->with('info', 'Ja existe um ficheiro ZIP disponivel para download.');
            }
        }

        // Dispatch do job
        GerarBoletinsEmLote::dispatch($turmaId, $anoLectivo, $userId);

        $turma = Turma::find($turmaId);

        return back()->with('success', "A geracao dos boletins da turma {$turma->codigo} foi iniciada. Sera notificado quando estiver pronto.");
    }

    /**
     * Verificar estado da geracao em lote
     */
    public function boletinsEmLoteEstado(Request $request)
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
        ]);

        $turmaId = (int) $request->input('turma_id');
        $this->turmaEscopo->assertAcessoTurma(auth()->user(), $turmaId);
        $userId = auth()->id();
        $cacheKey = "boletins_lote_{$userId}_{$turmaId}";

        if (!cache()->has($cacheKey)) {
            return response()->json([
                'status' => 'nao_encontrado',
                'message' => 'Nenhuma geracao em lote encontrada para esta turma.',
            ]);
        }

        $dados = cache()->get($cacheKey);

        if (isset($dados['ficheiro']) && Storage::disk('local')->exists($dados['ficheiro'])) {
            return response()->json([
                'status' => 'pronto',
                'message' => 'O ficheiro ZIP esta pronto para download.',
                'url' => route('export.boletins-em-lote.download', ['turma_id' => $turmaId]),
                'turma' => $dados['turma'],
                'gerados' => $dados['gerados'],
                'erros' => $dados['erros'],
                'criado_em' => $dados['criado_em'],
            ]);
        }

        return response()->json([
            'status' => 'processando',
            'message' => 'A geracao ainda esta em processamento.',
        ]);
    }

    /**
     * Download do ficheiro ZIP de boletins
     */
    public function boletinsEmLoteDownload(Request $request)
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
        ]);

        $turmaId = (int) $request->input('turma_id');
        $this->turmaEscopo->assertAcessoTurma(auth()->user(), $turmaId);
        $userId = auth()->id();
        $cacheKey = "boletins_lote_{$userId}_{$turmaId}";

        if (!cache()->has($cacheKey)) {
            return back()->with('error', 'Ficheiro nao encontrado.');
        }

        $dados = cache()->get($cacheKey);

        if (!isset($dados['ficheiro']) || !Storage::disk('local')->exists($dados['ficheiro'])) {
            return back()->with('error', 'O ficheiro ZIP ainda nao esta pronto ou expirou.');
        }

        $this->registarExportacao('boletins-em-lote', 'zip', null, [
            'turma_id' => $turmaId,
            'gerados' => $dados['gerados'],
        ]);

        return Storage::disk('local')->download($dados['ficheiro']);
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    protected function anexarQrBoletim(array $dados, Aluno $aluno, ?string $anoLectivo, $trimestre): array
    {
        if (! config('facilita.boletim_qr_enabled', true)) {
            return $dados;
        }

        $url = app(\App\Services\SignedDocumentService::class)->urlBoletim(
            $aluno,
            $anoLectivo,
            $trimestre !== null && $trimestre !== '' ? (int) $trimestre : null,
        );

        $dados['qr_verificacao_url'] = $url;
        $dados['qr_verificacao_data_uri'] = app(\App\Services\QrCodeService::class)->dataUri($url, 140);

        return $dados;
    }

    /**
     * Registar exportacao no log de auditoria
     */
    protected function registarExportacao(string $tipo, string $formato, $subject = null, array $propriedades = []): void
    {
        $activityLogger = activity()
            ->causedBy(auth()->user())
            ->withProperties(array_merge([
                'tipo' => $tipo,
                'formato' => $formato,
            ], $propriedades));

        if ($subject) {
            $activityLogger->performedOn($subject);
        }

        $activityLogger->log("Exportacao de {$tipo} em {$formato}");
    }
}
