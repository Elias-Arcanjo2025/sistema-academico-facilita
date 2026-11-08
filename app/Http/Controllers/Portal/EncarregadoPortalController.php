<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Aluno;
use App\Models\BoletimAssinatura;
use App\Models\Configuracao;
use App\Services\AgendaService;
use App\Services\ConfiguracaoService;
use App\Services\MaterialAcademicoService;
use App\Services\Portal\AlunoPortalAcessoService;
use App\Services\Portal\AlunoPortalNotasService;
use App\Services\Portal\EncarregadoDashboardService;
use App\Services\Portal\EncarregadoEngajamentoService;
use App\Services\Portal\EncarregadoPortalAcessoService;
use App\Services\Portal\EncarregadoResumoSemanalService;
use App\Services\ProactiveNotificacaoDispatcher;
use App\Services\SignedDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EncarregadoPortalController extends Controller
{
    public function __construct(
        private readonly EncarregadoPortalAcessoService $acesso,
        private readonly AlunoPortalNotasService $notasService,
        private readonly AlunoPortalAcessoService $alunoAcesso,
        private readonly EncarregadoDashboardService $dashboardService,
        private readonly EncarregadoResumoSemanalService $resumoSemanal,
        private readonly EncarregadoEngajamentoService $engajamento,
        private readonly ConfiguracaoService $configuracao,
        private readonly SignedDocumentService $signedDocuments,
        private readonly \App\Services\PresencaService $presencaService,
        private readonly AgendaService $agendaService,
        private readonly MaterialAcademicoService $materialService,
    ) {
        $this->middleware(function ($request, $next) {
            if (! $request->user()?->hasRole('encarregado') || ! $request->user()->encarregado_id) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function dashboard(Request $request): View
    {
        $encarregado = $this->acesso->encarregadoDoUser($request->user());
        $filhos = $this->acesso->filhos($encarregado);
        $dashboard = $this->dashboardService->resumoFamilia($encarregado, $filhos);
        $resumoSemanalPreview = $this->resumoSemanal->gerarParaFamilia($encarregado);
        $acoesAgora = $this->engajamento->acoesAgora($encarregado, $request->user(), $filhos);

        app(ProactiveNotificacaoDispatcher::class)->aoVisitarArea($request->user());

        return view('portal.encarregado.dashboard', compact(
            'encarregado',
            'filhos',
            'dashboard',
            'resumoSemanalPreview',
            'acoesAgora',
        ));
    }

    public function resumoSemanal(Request $request): View
    {
        $encarregado = $this->acesso->encarregadoDoUser($request->user());
        $resumo = $this->resumoSemanal->gerarParaFamilia($encarregado);

        app(ProactiveNotificacaoDispatcher::class)->aoVisitarArea($request->user());

        return view('portal.encarregado.resumo-semanal', compact('encarregado', 'resumo'));
    }

    public function filho(Request $request, Aluno $aluno): View
    {
        $encarregado = $this->acesso->encarregadoDoUser($request->user());
        $this->acesso->assertPodeVer($encarregado, $aluno);

        $aluno->load(['turma.curso', 'turma.classe']);
        $dashboard = $this->dashboardService->resumoFilho($encarregado, $aluno);
        $podeNotas = (bool) $dashboard['card']['pode_notas'];
        $trimestre = $dashboard['trimestre'];
        $notaMinima = $dashboard['nota_minima'];
        $notas = $dashboard['notas'];
        $notasResumo = $dashboard['notas_resumo'];
        $graficoMt = $dashboard['grafico_mt'];
        $evolucao = $dashboard['evolucao'];
        $assiduidadeChart = $dashboard['assiduidade_chart'];
        $resumoAssiduidade = $dashboard['resumo_assiduidade'];
        $card = $dashboard['card'];
        $anoActualId = Configuracao::getAnoLectivoActualId();

        $assinaturas = BoletimAssinatura::query()
            ->where('aluno_id', $aluno->id)
            ->where('encarregado_id', $encarregado->id)
            ->latest('assinado_em')
            ->limit(10)
            ->get();

        $boletimUrl = $podeNotas
            ? $this->signedDocuments->urlBoletim($aluno, Configuracao::getAnoLectivoActual(), $trimestre)
            : null;

        $ocorrenciasRecentes = \App\Models\Ocorrencia::query()
            ->where('aluno_id', $aluno->id)
            ->when($anoActualId, fn ($q) => $q->where('ano_lectivo_id', $anoActualId))
            ->latest('data')
            ->limit(5)
            ->get();

        $acoesAgora = $this->engajamento->acoesParaFilho($encarregado, $request->user(), $aluno);
        $timelinePreview = $this->engajamento->timeline($encarregado, $request->user(), $aluno, 5);

        return view('portal.encarregado.filho', compact(
            'encarregado',
            'aluno',
            'podeNotas',
            'notas',
            'notasResumo',
            'graficoMt',
            'evolucao',
            'assiduidadeChart',
            'assinaturas',
            'boletimUrl',
            'trimestre',
            'notaMinima',
            'resumoAssiduidade',
            'ocorrenciasRecentes',
            'card',
            'acoesAgora',
            'timelinePreview',
        ));
    }

    public function timeline(Request $request, Aluno $aluno): View
    {
        $encarregado = $this->acesso->encarregadoDoUser($request->user());
        $this->acesso->assertPodeVer($encarregado, $aluno);
        $aluno->load(['turma.curso', 'turma.classe']);

        $eventos = $this->engajamento->timeline($encarregado, $request->user(), $aluno);
        $acoesAgora = $this->engajamento->acoesParaFilho($encarregado, $request->user(), $aluno);
        $card = $this->dashboardService->cartaoFilho($encarregado, $aluno);

        return view('portal.encarregado.timeline', compact(
            'encarregado',
            'aluno',
            'eventos',
            'acoesAgora',
            'card',
        ));
    }

    public function assiduidade(Request $request, Aluno $aluno): View
    {
        $encarregado = $this->acesso->encarregadoDoUser($request->user());
        $this->acesso->assertPodeVer($encarregado, $aluno);

        $resumo = $this->presencaService->resumoAluno((int) $aluno->id);

        return view('portal.encarregado.assiduidade', [
            'encarregado' => $encarregado,
            'aluno' => $aluno->load('turma'),
            'totais' => $resumo['totais'],
            'recentes' => $resumo['recentes'],
            'estados' => \App\Models\PresencaRegisto::estados(),
        ]);
    }

    public function ocorrencias(Request $request, Aluno $aluno): View
    {
        $encarregado = $this->acesso->encarregadoDoUser($request->user());
        $this->acesso->assertPodeVer($encarregado, $aluno);
        $anoId = Configuracao::getAnoLectivoActualId();

        $ocorrencias = \App\Models\Ocorrencia::query()
            ->where('aluno_id', $aluno->id)
            ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
            ->latest('data')
            ->paginate(20);

        return view('portal.encarregado.ocorrencias', [
            'encarregado' => $encarregado,
            'aluno' => $aluno->load('turma'),
            'ocorrencias' => $ocorrencias,
            'tipos' => \App\Models\Ocorrencia::tipos(),
            'severidades' => \App\Models\Ocorrencia::severidades(),
        ]);
    }

    public function agenda(Request $request, Aluno $aluno): View
    {
        $encarregado = $this->acesso->encarregadoDoUser($request->user());
        $this->acesso->assertPodeVer($encarregado, $aluno);
        $aluno->load(['turma.curso']);

        $eventos = $this->agendaService->paraAluno($aluno);

        return view('portal.encarregado.agenda', [
            'encarregado' => $encarregado,
            'aluno' => $aluno,
            'eventos' => $eventos,
            'tipos' => \App\Models\AgendaEvento::tipos(),
        ]);
    }

    public function materiais(Request $request, Aluno $aluno): View
    {
        $encarregado = $this->acesso->encarregadoDoUser($request->user());
        $this->acesso->assertPodeVer($encarregado, $aluno);
        $aluno->load(['turma.curso']);

        $materiais = $this->materialService
            ->queryParaAluno($aluno)
            ->paginate(20);

        return view('portal.encarregado.materiais', [
            'encarregado' => $encarregado,
            'aluno' => $aluno,
            'materiais' => $materiais,
        ]);
    }

    public function materialDownload(Request $request, Aluno $aluno, \App\Models\MaterialAcademico $material): BinaryFileResponse
    {
        $encarregado = $this->acesso->encarregadoDoUser($request->user());
        $this->acesso->assertPodeVer($encarregado, $aluno);

        if (! $this->materialService->podeDescarregarAluno($aluno, $material)) {
            abort(403);
        }

        return response()->download(
            $this->materialService->caminhoAbsoluto($material),
            $this->materialService->nomeDownload($material)
        );
    }

    public function assinarBoletim(Request $request, Aluno $aluno): RedirectResponse
    {
        $encarregado = $this->acesso->encarregadoDoUser($request->user());
        $this->acesso->assertPodeVer($encarregado, $aluno);
        $this->acesso->assertPodeVerNotas($encarregado, $aluno);
        $this->alunoAcesso->assertPodeConsultarNotas($aluno);

        $data = $request->validate([
            'confirmacao' => ['accepted'],
            'trimestre' => ['nullable', 'integer', 'min:1', 'max:3'],
        ]);

        $ano = Configuracao::getAnoLectivoActual();
        $trimestre = (int) ($data['trimestre'] ?? $this->configuracao->getTrimestreActual());
        $hash = hash('sha256', $aluno->public_uuid.'|'.$ano.'|'.$trimestre.'|'.now()->toDateString());

        $this->acesso->assinarBoletim(
            $encarregado,
            $aluno,
            $hash,
            $ano,
            $trimestre,
            $request->ip(),
            (string) $request->userAgent(),
        );

        return back()->with('success', 'Boletim assinado digitalmente. Obrigado pela confirmação.');
    }
}
