<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesNotasTurma;
use App\Models\Configuracao;
use App\Models\Turma;
use App\Services\RecuperacaoService;
use App\Support\NotaLancamentoRules;
use App\Support\TurmaExameRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Exame de Recuperação anual — 10.ª e 11.ª classes (Art.º 30.º DE 424/25).
 */
class RecuperacaoController extends Controller
{
    use AuthorizesNotasTurma;

    public function __construct(private RecuperacaoService $recuperacaoService)
    {
        $this->middleware(function ($request, $next) {
            if (! NotaLancamentoRules::podeAcederModuloExames($request->user())) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function index(Request $request): View|RedirectResponse
    {
        $redirect = TurmaExameRedirect::paraLancamento(
            $request,
            'recuperacoes.lancar',
            RecuperacaoService::CLASSES_RECUPERACAO,
        );
        if ($redirect) {
            return $redirect;
        }

        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);
        $user = $request->user();
        $visaoProfessor = NotaLancamentoRules::visaoElegibilidadeRestritaProfessor($user);

        $turmasQuery = Turma::with(['classe', 'curso', 'anoLectivo'])
            ->where('activo', true)
            ->whereHas('classe', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('codigo', 'like', '10%')->orWhere('nome', 'like', '10%')
                        ->orWhere('codigo', 'like', '11%')->orWhere('nome', 'like', '11%');
                });
            })
            ->orderBy('nome');

        if ($visaoProfessor) {
            $turmaIds = NotaLancamentoRules::turmasElegibilidadeParaUtilizador($user, $anoLectivo)
                ->filter(fn (Turma $t) => in_array($t->numeroClasse(), RecuperacaoService::CLASSES_RECUPERACAO, true))
                ->pluck('id');
            $turmasQuery->whereIn('id', $turmaIds);
        }

        $turmas = $turmasQuery->get();
        $associacoesProfessor = $visaoProfessor
            ? NotaLancamentoRules::associacoesExameProfessor($user, (int) $anoLectivoId, RecuperacaoService::CLASSES_RECUPERACAO)
            : collect();

        $config = $this->recuperacaoService->configuracoes();

        return view('recuperacoes.index', compact('turmas', 'anoLectivo', 'config', 'visaoProfessor', 'associacoesProfessor'));
    }

    public function lancar(Request $request, Turma $turma): View
    {
        $this->assertAcessoTurma($turma);

        abort_unless(
            in_array($turma->numeroClasse(), RecuperacaoService::CLASSES_RECUPERACAO, true),
            404,
            'Recuperação anual aplica-se apenas às turmas da 10.ª e 11.ª classes.',
        );

        $turma->load(['alunos', 'curso.disciplinas', 'classe', 'anoLectivo']);

        $anoLectivo = $request->input('ano_lectivo')
            ?: ($turma->anoLectivo?->nome ?? $turma->ano_lectivo)
            ?: Configuracao::getAnoLectivoActual();

        $elegibilidade = $this->recuperacaoService->calcularElegibilidadeAnual($turma, $anoLectivo);
        $config = $this->recuperacaoService->configuracoes();
        $disciplinas = $turma->curso?->disciplinas->keyBy('id') ?? collect();

        $recuperacoesExistentes = [];
        foreach ($this->recuperacaoService->recuperacoesAnuaisTurma($turma, $anoLectivo) as $n) {
            $recuperacoesExistentes[$n->aluno_id][$n->disciplina_id] = (float) $n->valor;
        }

        $user = $request->user();
        $visaoProfessor = NotaLancamentoRules::visaoElegibilidadeRestritaProfessor($user);
        $disciplinasProfessorIds = $visaoProfessor
            ? NotaLancamentoRules::disciplinasProfessorNaTurma($user, $turma)
            : [];

        $permiteLancamentoSemElegibilidade = NotaLancamentoRules::permiteLancamentoExameSemElegibilidade();

        return view('recuperacoes.lancar', compact(
            'turma',
            'anoLectivo',
            'elegibilidade',
            'config',
            'disciplinas',
            'recuperacoesExistentes',
            'visaoProfessor',
            'disciplinasProfessorIds',
            'permiteLancamentoSemElegibilidade',
        ));
    }

    public function guardar(Request $request, Turma $turma): RedirectResponse
    {
        $user = $request->user();
        if (NotaLancamentoRules::visaoElegibilidadeRestritaProfessor($user)) {
            $this->assertAcessoTurma($turma);
            abort_unless($user->can('notas.lancar'), 403);
        } else {
            $this->authorizeLancamentoNotasTurma($turma);
        }

        abort_unless(
            in_array($turma->numeroClasse(), RecuperacaoService::CLASSES_RECUPERACAO, true),
            404,
        );

        $turma->load('anoLectivo');

        $anoLectivo = $request->input('ano_lectivo')
            ?: ($turma->anoLectivo?->nome ?? $turma->ano_lectivo)
            ?: Configuracao::getAnoLectivoActual();

        $validated = $request->validate([
            'recuperacoes' => ['nullable', 'array'],
            'recuperacoes.*' => ['nullable', 'array'],
            'recuperacoes.*.*' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ]);

        $payload = $validated['recuperacoes'] ?? [];
        $elegibilidade = $this->recuperacaoService->calcularElegibilidadeAnual($turma, $anoLectivo)
            ->keyBy('aluno_id');

        $recuperacoesActivas = [];
        foreach ($this->recuperacaoService->recuperacoesAnuaisTurma($turma, $anoLectivo) as $nota) {
            $recuperacoesActivas[$nota->aluno_id][$nota->disciplina_id] = true;
        }

        $gravadas = 0;
        $rejeitadas = 0;

        DB::transaction(function () use ($payload, $turma, $anoLectivo, $elegibilidade, $user, $recuperacoesActivas, &$gravadas, &$rejeitadas) {
            foreach ($payload as $alunoId => $notasPorDisc) {
                $alunoElig = $elegibilidade->get((int) $alunoId);

                foreach ($notasPorDisc as $discId => $valor) {
                    $discId = (int) $discId;
                    $possuiNota = isset($recuperacoesActivas[(int) $alunoId][$discId]);
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

                    $this->recuperacaoService->gravarRecuperacao(
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

        $msg = "Recuperações guardadas: {$gravadas}.";
        if ($rejeitadas > 0) {
            $msg .= " {$rejeitadas} entrada(s) rejeitada(s) (aluno ou disciplina sem elegibilidade).";
        }

        return redirect()
            ->route('recuperacoes.lancar', [
                'turma' => $turma,
                'ano_lectivo' => $anoLectivo,
            ])
            ->with('success', $msg);
    }
}
