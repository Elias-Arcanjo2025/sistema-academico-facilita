<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesNotasTurma;
use App\Models\Configuracao;
use App\Models\Turma;
use App\Services\RecursoService;
use App\Support\NotaLancamentoRules;
use App\Support\TurmaExameRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Gestão do Exame de Recurso (12.ª classe, 3.º trimestre).
 *
 * Base legal: Decreto Executivo n.º 424/25 — Art.º 31.º.
 */
class RecursoController extends Controller
{
    use AuthorizesNotasTurma;

    public function __construct(private RecursoService $recursoService)
    {
        $this->middleware(function ($request, $next) {
            if (! NotaLancamentoRules::podeAcederModuloExames($request->user())) {
                abort(403);
            }

            return $next($request);
        });
    }

    /**
     * Lista as turmas da 12.ª elegíveis para lançamento de recurso.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $redirect = TurmaExameRedirect::paraLancamento($request, 'recursos.lancar', [12]);
        if ($redirect) {
            return $redirect;
        }

        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);
        $user = $request->user();
        $visaoProfessor = NotaLancamentoRules::visaoElegibilidadeRestritaProfessor($user);

        $turmasQuery = Turma::with(['classe', 'curso', 'anoLectivo'])
            ->whereHas('classe', function ($q) {
                $q->where('codigo', 'like', '12%')->orWhere('nome', 'like', '12%');
            })
            ->where('activo', true)
            ->orderBy('nome');

        if ($visaoProfessor) {
            $turmaIds = NotaLancamentoRules::turmasElegibilidadeParaUtilizador($user, $anoLectivo)
                ->filter(fn (Turma $t) => $t->numeroClasse() === 12)
                ->pluck('id');
            $turmasQuery->whereIn('id', $turmaIds);
        }

        $turmas = $turmasQuery->get();
        $associacoesProfessor = $visaoProfessor
            ? NotaLancamentoRules::associacoesExameProfessor($user, (int) $anoLectivoId, [12])
            : collect();

        $config = $this->recursoService->configuracoes();

        return view('recursos.index', compact('turmas', 'anoLectivo', 'config', 'visaoProfessor', 'associacoesProfessor'));
    }

    /**
     * Formulário de lançamento das notas de recurso de uma turma.
     *
     * IMPORTANTE: o ano lectivo usado é SEMPRE o da turma (porque a pauta da turma
     * pertence a esse ano). Só se aceita override via ?ano_lectivo=... explicito.
     */
    public function lancar(Request $request, Turma $turma): View
    {
        $this->assertAcessoTurma($turma);

        abort_unless($turma->numeroClasse() === 12, 404, 'Exame de recurso aplica-se apenas à 12.ª classe.');

        $turma->load(['alunos', 'curso.disciplinas', 'classe', 'anoLectivo']);

        // Ano lectivo da TURMA é a fonte da verdade — não o ano "actual do sistema".
        $anoLectivo = $request->input('ano_lectivo')
            ?: ($turma->anoLectivo?->nome ?? $turma->ano_lectivo)
            ?: Configuracao::getAnoLectivoActual();

        $elegibilidade = $this->recursoService->calcularElegibilidade($turma, $anoLectivo);
        $config = $this->recursoService->configuracoes();
        $user = $request->user();
        $visaoProfessor = NotaLancamentoRules::visaoElegibilidadeRestritaProfessor($user);
        $disciplinasProfessorIds = $visaoProfessor
            ? NotaLancamentoRules::disciplinasProfessorNaTurma($user, $turma)
            : [];
        $permiteLancamentoSemElegibilidade = NotaLancamentoRules::permiteLancamentoExameSemElegibilidade();

        // Map de nomes de disciplinas para apresentação
        $disciplinas = $turma->curso?->disciplinas->keyBy('id') ?? collect();

        // Carregar notas RECURSO já lançadas (para pré-preencher)
        $recursosExistentes = []; // [aluno_id][disciplina_id] = valor
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);
        $recursos = \App\Models\Nota::whereIn('aluno_id', $turma->alunos->pluck('id'))
            ->where('tipo', \App\Models\Nota::TIPO_RECURSO)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('trimestre', 3)
            ->whereNull('archived_at')
            ->get();
        foreach ($recursos as $r) {
            $recursosExistentes[$r->aluno_id][$r->disciplina_id] = (float) $r->valor;
        }

        return view('recursos.lancar', compact(
            'turma', 'anoLectivo', 'elegibilidade', 'config', 'disciplinas', 'recursosExistentes',
            'visaoProfessor', 'disciplinasProfessorIds', 'permiteLancamentoSemElegibilidade',
        ));
    }

    /**
     * Grava as notas de recurso lançadas.
     */
    public function guardar(Request $request, Turma $turma): RedirectResponse
    {
        $user = $request->user();
        if (NotaLancamentoRules::visaoElegibilidadeRestritaProfessor($user)) {
            $this->assertAcessoTurma($turma);
            abort_unless($user->can('notas.lancar'), 403);
        } else {
            $this->authorizeLancamentoNotasTurma($turma);
        }

        $turma->load('anoLectivo');
        $anoLectivo = $request->input('ano_lectivo')
            ?: ($turma->anoLectivo?->nome ?? $turma->ano_lectivo)
            ?: Configuracao::getAnoLectivoActual();

        $validated = $request->validate([
            'recursos' => ['nullable', 'array'],
            'recursos.*' => ['nullable', 'array'],
            'recursos.*.*' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ]);

        $payload = $validated['recursos'] ?? [];

        // Validar elegibilidade — só aceitamos notas para alunos+disciplinas que estavam elegíveis
        $elegibilidade = $this->recursoService->calcularElegibilidade($turma, $anoLectivo)
            ->keyBy('aluno_id');

        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);
        $recursosActivos = [];
        foreach (\App\Models\Nota::where('turma_id', $turma->id)
            ->where('tipo', \App\Models\Nota::TIPO_RECURSO)
            ->where('trimestre', 3)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereNull('archived_at')
            ->get() as $nota) {
            $recursosActivos[$nota->aluno_id][$nota->disciplina_id] = true;
        }

        $gravadas = 0;
        $rejeitadas = 0;

        DB::transaction(function () use ($payload, $turma, $anoLectivo, $elegibilidade, $user, $recursosActivos, &$gravadas, &$rejeitadas) {
            foreach ($payload as $alunoId => $notasPorDisc) {
                $alunoElig = $elegibilidade->get((int) $alunoId);

                foreach ($notasPorDisc as $discId => $valor) {
                    $discId = (int) $discId;
                    $possuiNota = isset($recursosActivos[(int) $alunoId][$discId]);
                    $elegivelFormal = NotaLancamentoRules::alunoElegivelExameDisciplina($alunoElig, $discId);
                    $podeLancar = NotaLancamentoRules::podeLancarExameAlunoDisciplina($alunoElig, $discId, $possuiNota);

                    if (! NotaLancamentoRules::utilizadorPodeLancar($user, $discId, $turma->id)) {
                        if ($valor !== null && $valor !== '') {
                            $rejeitadas++;
                        }
                        continue;
                    }

                    if (! $podeLancar && $valor !== null && $valor !== '') {
                        $rejeitadas++;
                        continue;
                    }

                    if (! $podeLancar && ($valor === null || $valor === '')) {
                        continue;
                    }

                    $foraElegibilidade = ! $elegivelFormal && ($valor !== null && $valor !== '');

                    $this->recursoService->gravarRecurso(
                        (int) $alunoId,
                        $discId,
                        $turma->id,
                        $valor === null || $valor === '' ? null : (float) $valor,
                        $anoLectivo,
                        $foraElegibilidade,
                    );
                    $gravadas++;
                }
            }
        });

        $msg = "Recursos guardados: {$gravadas}.";
        if ($rejeitadas > 0) {
            $msg .= " {$rejeitadas} entrada(s) rejeitada(s) (aluno ou disciplina sem elegibilidade).";
        }

        return redirect()
            ->route('recursos.lancar', ['turma' => $turma, 'ano_lectivo' => $anoLectivo])
            ->with('success', $msg);
    }
}
