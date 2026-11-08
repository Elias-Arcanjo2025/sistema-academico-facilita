<?php

namespace App\Http\Controllers;

use App\Models\Configuracao;
use App\Models\Disciplina;
use App\Models\Turma;
use App\Services\RecuperacaoService;
use App\Services\RecuperacaoTransicaoService;
use App\Services\RecursoService;
use App\Support\NotaLancamentoRules;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lista consolidada de alunos sujeitos a exames de recuperação (10.ª/11.ª)
 * e exame de recurso (12.ª).
 *
 * Professores: apenas turmas/disciplinas que leccionam.
 */
class ExameElegibilidadeController extends Controller
{
    public function __construct(
        private RecuperacaoService $recuperacaoService,
        private RecuperacaoTransicaoService $transicaoService,
        private RecursoService $recursoService,
    ) {
        $this->middleware(function ($request, $next) {
            if (! NotaLancamentoRules::utilizadorPodeVerElegibilidadeExames($request->user())) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $visaoProfessor = NotaLancamentoRules::visaoElegibilidadeRestritaProfessor($user);

        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());
        $turmaId = $request->input('turma_id') ? (int) $request->input('turma_id') : null;
        $disciplinaId = $request->input('disciplina_id') ? (int) $request->input('disciplina_id') : null;
        $trimestre = $request->input('trimestre') ? (int) $request->input('trimestre') : null;
        $tipo = $request->input('tipo', 'todos');

        $turmas = NotaLancamentoRules::turmasElegibilidadeParaUtilizador($user, $anoLectivo);

        if ($turmaId && ! $turmas->contains('id', $turmaId)) {
            abort(403, 'Não tem permissão para consultar a elegibilidade desta turma.');
        }

        $turmaSelecionada = $turmaId ? Turma::with('classe')->find($turmaId) : null;
        $classeNumero = $turmaSelecionada
            ? $this->transicaoService->classeNumero($turmaSelecionada)
            : null;

        $disciplinasTurma = collect();
        $disciplinaSelecionada = null;

        if ($turmaSelecionada) {
            $disciplinasTurma = NotaLancamentoRules::disciplinasLancamentoNaTurma($user, $turmaSelecionada);

            if ($disciplinaId && ! $disciplinasTurma->contains('id', $disciplinaId)) {
                $disciplinaId = null;
            }

            if (! $disciplinaId && $disciplinasTurma->isNotEmpty() && $visaoProfessor) {
                $disciplinaId = (int) $disciplinasTurma->first()->id;
            }

            $disciplinaSelecionada = $disciplinaId
                ? $disciplinasTurma->firstWhere('id', $disciplinaId) ?? Disciplina::find($disciplinaId)
                : null;
        }

        $disciplinasPorTurma = $turmas->mapWithKeys(function (Turma $turma) use ($user) {
            return [
                $turma->id => NotaLancamentoRules::disciplinasLancamentoNaTurma($user, $turma)
                    ->map(fn ($d) => [
                        'id' => $d->id,
                        'nome' => $d->codigo ?: $d->nome,
                    ])
                    ->values()
                    ->all(),
            ];
        });

        if ($visaoProfessor && $classeNumero !== null) {
            $tipo = match (true) {
                in_array($classeNumero, [10, 11], true) => in_array($tipo, ['recuperacao', 'recuperacao_terminal'], true)
                    ? $tipo
                    : 'recuperacao_terminal',
                $classeNumero === 12 => 'recurso',
                default => $tipo,
            };
            $trimestre = $trimestre ?? 3;
        }

        $recuperacaoAnual = collect();
        $recurso = collect();
        $resumoTransicao = collect();

        if (in_array($tipo, ['todos', 'recuperacao', 'recuperacao_terminal'], true)) {
            $recuperacaoAnual = $this->recuperacaoService->listarElegiveisPlano($turmaId, null, $anoLectivo);
        }

        if (in_array($tipo, ['todos', 'recurso'], true)) {
            $recurso = $this->recursoService->listarElegiveisPlano($turmaId, $anoLectivo);
        }

        if ($turmaId && in_array($tipo, ['todos', 'resumo_transicao'], true) && ! $visaoProfessor) {
            $turma = Turma::with('classe')->find($turmaId);
            if ($turma) {
                $resumoTransicao = $this->transicaoService->resumoPorTurma($turma, $anoLectivo);
            }
        }

        $mostrarResumoTransicao = $turmaId
            && ! $visaoProfessor
            && in_array($classeNumero, [10, 11], true)
            && ($trimestre === 3 || $trimestre === null)
            && in_array($tipo, ['todos', 'resumo_transicao'], true);

        $resumoFiltrado = $mostrarResumoTransicao
            ? $resumoTransicao->filter(fn ($a) => in_array($a['situacao'] ?? '', [
                'transita_deficiencia',
                'reprova',
            ], true))->values()
            : collect();

        $recuperacaoAnual = NotaLancamentoRules::filtrarLinhasElegibilidadePorDisciplina(
            $recuperacaoAnual,
            $disciplinaId,
        );
        $recurso = NotaLancamentoRules::filtrarLinhasElegibilidadePorDisciplina(
            $recurso,
            $disciplinaId,
        );

        if ($visaoProfessor && $disciplinaId) {
            $recuperacaoAnual = $recuperacaoAnual->filter(
                fn ($l) => (int) ($l['turma_id'] ?? 0) === $turmaId
            )->values();
            $recurso = $recurso->filter(
                fn ($l) => (int) ($l['turma_id'] ?? 0) === $turmaId
            )->values();
        }

        $linhas = $recuperacaoAnual->concat($recurso)->values();

        $stats = [
            'recuperacao_anual' => $recuperacaoAnual->count(),
            'recurso' => $recurso->count(),
            'total' => $linhas->count(),
            'alunos_recuperacao_anual' => $recuperacaoAnual->pluck('aluno_id')->unique()->count(),
            'alunos_recurso' => $recurso->pluck('aluno_id')->unique()->count(),
            'alunos_reprova' => $resumoTransicao->where('situacao', 'reprova')->count(),
            'alunos_recuperacao_t3' => $resumoTransicao->filter(fn ($a) => ($a['situacao'] ?? '') === 'transita_deficiencia'
                && count($a['terminais_recuperacao'] ?? []) > 0)->count(),
            'alunos_deficiencias' => $resumoTransicao->filter(fn ($a) => ($a['situacao'] ?? '') === 'transita_deficiencia'
                && count($a['continuas_deficientes'] ?? []) > 0)->count(),
            'alunos_sem_avaliacao' => $mostrarResumoTransicao
                ? $resumoTransicao->where('situacao', 'sem_avaliacao')->count()
                : 0,
        ];

        return view('exames.elegibilidade', compact(
            'linhas',
            'turmas',
            'anoLectivo',
            'turmaId',
            'disciplinaId',
            'disciplinasTurma',
            'disciplinaSelecionada',
            'visaoProfessor',
            'trimestre',
            'tipo',
            'stats',
            'resumoTransicao',
            'resumoFiltrado',
            'mostrarResumoTransicao',
            'turmaSelecionada',
            'classeNumero',
            'recuperacaoAnual',
            'disciplinasPorTurma',
        ));
    }

    /**
     * Disciplinas do professor numa turma (AJAX para filtros).
     */
    public function getDisciplinas(Request $request): \Illuminate\Http\JsonResponse
    {
        $turmaId = (int) $request->get('turma_id');
        if (! $turmaId) {
            return response()->json([]);
        }

        $user = $request->user();
        if (! NotaLancamentoRules::utilizadorPodeVerElegibilidadeTurmaDisciplina($user, $turmaId)) {
            abort(403);
        }

        $turma = Turma::with(['curso', 'classe'])->find($turmaId);
        if (! $turma) {
            return response()->json([]);
        }

        return response()->json(
            NotaLancamentoRules::disciplinasLancamentoNaTurma($user, $turma)
                ->map(fn ($d) => ['id' => $d->id, 'nome' => $d->codigo ?: $d->nome])
                ->values()
        );
    }
}
