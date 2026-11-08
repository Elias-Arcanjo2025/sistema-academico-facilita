<?php

namespace App\Http\Controllers;

use App\Models\Configuracao;
use App\Models\Disciplina;
use App\Models\Turma;
use App\Services\PautaService;
use App\Services\TurmaEscopoService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller para Pautas Administrativas
 *
 * Directores de Turma, Coordenadores e Directora Geral veem pautas completas.
 * Acesso controlado por permissão: 'pautas.ver'
 */
class PautaController extends Controller
{
    public function __construct(
        protected PautaService $pautaService,
        protected TurmaEscopoService $turmaEscopo,
    ) {
        // Apenas usuários com permissão pautas.ver (administrativos)
        $this->middleware('can:pautas.ver');
    }

    /**
     * Página inicial de pautas administrativas
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $turmaId = $request->input('turma_id');
        $disciplinaId = $request->input('disciplina_id');
        $trimestre = $request->input('trimestre');
        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo)
            ?? Configuracao::getAnoLectivoActualId();

        // Determinar quais turmas o usuário pode acessar
        if ($user->temVisaoInstitucional()) {
            $turmas = Turma::activo()
                ->when($anoLectivoId, fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
                ->with('curso')->orderBy('nome')->get();
        } elseif ($user->hasRole('coordenador_curso')) {
            // Coordenador vê turmas dos cursos onde é coordenador
            $cursosIds = $user->coordenadorCursos()
                ->where('activo', true)
                ->pluck('curso_id');
            $turmas = Turma::activo()
                ->whereIn('curso_id', $cursosIds)
                ->with('curso')
                ->orderBy('nome')
                ->get();
        } elseif ($user->hasRole('director_turma')) {
            // Director vê apenas suas turmas
            $turmas = $user->turmasComoDirector()
                ->where('director_turma.activo', true)
                ->where('turmas.activo', true)
                ->with('curso')
                ->orderBy('nome')
                ->get();
        } else {
            // Outros usuários não têm acesso
            $turmas = collect();
        }

        // Obter disciplinas da turma selecionada
        $classeNumero = null;
        if ($turmaId) {
            $turma = Turma::with('classe')->find($turmaId);
            $disciplinas = $turma && $turma->curso_id && $turma->classe_id
                ? Disciplina::whereHas('cursos', function ($query) use ($turma) {
                        $query->where('cursos.id', $turma->curso_id);
                    })
                    ->whereHas('classes', function ($query) use ($turma) {
                        $query->where('classes.id', $turma->classe_id);
                    })
                    ->activo()
                    ->orderBy('nome')
                    ->get()
                : collect();
            if ($turma?->classe) {
                $classeNumero = (int) preg_replace(
                    '/\D/',
                    '',
                    $turma->classe->codigo ?? $turma->classe->nome ?? '',
                );
            }
        } else {
            $disciplinas = collect();
        }

        // Buscar anos lectivos
        $anosLectivos = \App\Models\AnoLectivo::orderBy('nome', 'desc')->pluck('nome', 'id');

        if ($anosLectivos->isEmpty()) {
            $anosLectivos = collect([Configuracao::getAnoLectivoActual() => Configuracao::getAnoLectivoActual()]);
        }

        // Variáveis para compatibilidade com a view (mini-pauta foi movida para MiniPautaController)
        $miniPauta = null;
        $estatisticas = null;
        $turma = null;
        $disciplina = null;

        return view('pautas.index', compact(
            'turmas',
            'disciplinas',
            'turmaId',
            'disciplinaId',
            'trimestre',
            'anoLectivo',
            'anosLectivos',
            'miniPauta',
            'estatisticas',
            'turma',
            'disciplina',
            'classeNumero',
        ));
    }

    /**
     * Visualizar pauta final de uma disciplina
     */
    public function pautaFinalDisciplina(Request $request): View
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
            'ano_lectivo' => 'nullable|string|max:9',
        ]);

        $turmaId = (int) $request->input('turma_id');
        $disciplinaId = (int) $request->input('disciplina_id');
        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());

        $this->turmaEscopo->assertAcessoTurma(auth()->user(), $turmaId, $disciplinaId);

        $turma = Turma::with('curso')->findOrFail($turmaId);
        $disciplina = Disciplina::findOrFail($disciplinaId);

        $pautaFinal = $this->pautaService->gerarPautaFinalDisciplina(
            $turmaId,
            $disciplinaId,
            $anoLectivo
        );

        $estatisticas = $this->pautaService->getEstatisticasPautaFinal($pautaFinal);

        return view('pautas.pauta-final-disciplina', compact(
            'turma',
            'disciplina',
            'anoLectivo',
            'pautaFinal',
            'estatisticas'
        ));
    }

    /**
     * Visualizar pauta final completa da turma
     */
    public function pautaFinalTurma(Request $request): View
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'ano_lectivo' => 'nullable|string|max:9',
        ]);

        $turmaId = (int) $request->input('turma_id');
        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());

        $this->turmaEscopo->assertAcessoTurma(auth()->user(), $turmaId);

        $dados = $this->pautaService->gerarPautaFinalCompletaDetalhada($turmaId, $anoLectivo);

        return view('pautas.pauta-final-completa-detalhada', [
            'turma' => $dados['turma'],
            'disciplinas' => $dados['disciplinas'],
            'anoLectivo' => $dados['ano_lectivo'],
            'alunos' => $dados['alunos'],
            'classeNumero' => $dados['classe_numero'] ?? 0,
            'usaPtT3' => $dados['usa_pt_t3'] ?? true,
            'colsPorDisciplina' => $dados['cols_por_disciplina'] ?? 16,
        ]);
    }

    /**
     * Visualizar pauta final completa detalhada (todas disciplinas, todos trimestres)
     */
    public function pautaFinalCompletaDetalhada(Request $request): View
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'ano_lectivo' => 'nullable|string|max:9',
        ]);

        $turmaId = (int) $request->input('turma_id');
        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());

        $this->turmaEscopo->assertAcessoTurma(auth()->user(), $turmaId);

        $dados = $this->pautaService->gerarPautaFinalCompletaDetalhada($turmaId, $anoLectivo);

        return view('pautas.pauta-final-completa-detalhada', [
            'turma' => $dados['turma'],
            'disciplinas' => $dados['disciplinas'],
            'anoLectivo' => $dados['ano_lectivo'],
            'alunos' => $dados['alunos'],
            'classeNumero' => $dados['classe_numero'] ?? 0,
            'usaPtT3' => $dados['usa_pt_t3'] ?? true,
            'colsPorDisciplina' => $dados['cols_por_disciplina'] ?? 16,
        ]);
    }

    /**
     * Visualizar pauta trimestral completa (1 trimestre, todas disciplinas).
     * Layout oficial idêntico ao modelo Excel da escola.
     */
    public function pautaTrimestralCompleta(Request $request, \App\Services\ExportService $exportService): View
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'trimestre' => 'required|integer|in:1,2,3',
            'ano_lectivo' => 'nullable|string|max:9',
            'momento' => 'nullable|string|in:pre_conselho,pos_conselho',
        ]);

        $turmaId = (int) $request->input('turma_id');
        $trimestre = (int) $request->input('trimestre');
        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());
        $momento = \App\Support\PautaMomentoConselho::normalizar($request->input('momento'));

        $this->turmaEscopo->assertAcessoTurma(auth()->user(), $turmaId);

        $dados = $exportService->gerarDadosPautaTrimestralCompleta(
            $turmaId,
            $trimestre,
            $anoLectivo,
            $momento,
        );
        $dados['instituicao'] = app(\App\Services\ConfiguracaoService::class)->getInstituicao();
        $dados['pautaAprovacao'] = app(\App\Services\PautaAprovacaoService::class)
            ->obter($turmaId, $trimestre, $anoLectivo);
        $dados['podeAprovarPauta'] = app(\App\Services\PautaAprovacaoService::class)
            ->podeAprovar(auth()->user());

        return view('pautas.pauta-trimestral-completa', $dados);
    }

    public function aprovar(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'trimestre' => 'required|integer|in:1,2,3',
            'ano_lectivo' => 'nullable|string|max:9',
            'observacao' => 'nullable|string|max:500',
        ]);

        $this->turmaEscopo->assertAcessoTurma(auth()->user(), (int) $data['turma_id']);

        app(\App\Services\PautaAprovacaoService::class)->aprovar(
            auth()->user(),
            (int) $data['turma_id'],
            (int) $data['trimestre'],
            $data['ano_lectivo'] ?? null,
            $data['observacao'] ?? null,
        );

        return back()->with('success', 'Pauta do '.$data['trimestre'].'.º trimestre aprovada.');
    }

    public function reabrir(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'trimestre' => 'required|integer|in:1,2,3',
            'ano_lectivo' => 'nullable|string|max:9',
        ]);

        $this->turmaEscopo->assertAcessoTurma(auth()->user(), (int) $data['turma_id']);

        app(\App\Services\PautaAprovacaoService::class)->reabrir(
            auth()->user(),
            (int) $data['turma_id'],
            (int) $data['trimestre'],
            $data['ano_lectivo'] ?? null,
        );

        return back()->with('success', 'Aprovação da pauta reaberta.');
    }

    /**
     * API: Obter todas as disciplinas de uma turma
     */
    public function getDisciplinas(Request $request)
    {
        $turmaId = $request->input('turma_id');

        if (!$turmaId) {
            return response()->json([]);
        }

        $turma = Turma::with('curso')->find($turmaId);
        if (!$turma || !$turma->curso || !$turma->classe_id) {
            return response()->json([]);
        }

        // Administrativos veem TODAS as disciplinas da turma
        $disciplinas = Disciplina::whereHas('cursos', function ($query) use ($turma) {
                $query->where('cursos.id', $turma->curso_id);
            })
            ->whereHas('classes', function ($query) use ($turma) {
                $query->where('classes.id', $turma->classe_id);
            })
            ->where('activo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'codigo']);

        return response()->json($disciplinas->map(fn($d) => [
            'id' => $d->id,
            'nome' => $d->nome,
        ])->values());
    }
}
