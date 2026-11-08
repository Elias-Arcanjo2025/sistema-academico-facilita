<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesNotasTurma;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\Turma;
use App\Services\MelhoriaService;
use App\Support\TurmaExameRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Gestão do Exame de Melhoria de Nota.
 *
 * Base legal: Decreto Executivo n.º 424/25 — Art.º 34.º.
 *  - Aluno aprovado com nota positiva entre [min, max] pode solicitar melhoria.
 *  - Nota final = MAX(original, melhoria).
 */
class MelhoriaController extends Controller
{
    use AuthorizesNotasTurma;

    public function __construct(private MelhoriaService $melhoriaService)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $redirect = TurmaExameRedirect::paraLancamento($request, 'melhorias.lancar', [12]);
        if ($redirect) {
            return $redirect;
        }

        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());

        $turmas = Turma::with(['classe', 'curso', 'anoLectivo'])
            ->where('activo', true)
            ->whereHas('classe', function ($q) {
                $q->where('codigo', 'like', '12%')->orWhere('nome', 'like', '12%');
            })
            ->orderBy('nome')
            ->get();

        $config = $this->melhoriaService->configuracoes();

        return view('melhorias.index', compact('turmas', 'anoLectivo', 'config'));
    }

    /**
     * IMPORTANTE: o ano lectivo usado é SEMPRE o da turma (porque a melhoria recai
     * sobre notas desse ano). Só se aceita override via ?ano_lectivo=... explicito.
     */
    public function lancar(Request $request, Turma $turma): View
    {
        $this->assertAcessoTurma($turma);

        abort_unless($turma->numeroClasse() === 12, 404, 'Exame de melhoria neste módulo aplica-se à 12.ª classe.');

        $turma->load(['alunos', 'curso.disciplinas', 'classe', 'anoLectivo']);

        $anoLectivo = $request->input('ano_lectivo')
            ?: ($turma->anoLectivo?->nome ?? $turma->ano_lectivo)
            ?: Configuracao::getAnoLectivoActual();

        $elegibilidade = $this->melhoriaService->calcularElegibilidade($turma, $anoLectivo);
        $config = $this->melhoriaService->configuracoes();

        $disciplinas = $turma->curso?->disciplinas->keyBy('id') ?? collect();

        // Notas de melhoria já lançadas (para pré-preencher)
        $melhoriasExistentes = [];
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);
        $melhorias = Nota::whereIn('aluno_id', $turma->alunos->pluck('id'))
            ->where('tipo', Nota::TIPO_MELHORIA)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('trimestre', 3)
            ->get();
        foreach ($melhorias as $m) {
            $melhoriasExistentes[$m->aluno_id][$m->disciplina_id] = (float) $m->valor;
        }

        return view('melhorias.lancar', compact(
            'turma', 'anoLectivo', 'elegibilidade', 'config', 'disciplinas', 'melhoriasExistentes'
        ));
    }

    public function guardar(Request $request, Turma $turma): RedirectResponse
    {
        $this->authorizeLancamentoNotasTurma($turma);

        $turma->load('anoLectivo');
        $anoLectivo = $request->input('ano_lectivo')
            ?: ($turma->anoLectivo?->nome ?? $turma->ano_lectivo)
            ?: Configuracao::getAnoLectivoActual();

        $validated = $request->validate([
            'melhorias' => ['nullable', 'array'],
            'melhorias.*' => ['nullable', 'array'],
            'melhorias.*.*' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ]);

        $payload = $validated['melhorias'] ?? [];

        $elegibilidade = $this->melhoriaService->calcularElegibilidade($turma, $anoLectivo)
            ->keyBy('aluno_id');

        $gravadas = 0;
        $rejeitadas = 0;

        DB::transaction(function () use ($payload, $turma, $anoLectivo, $elegibilidade, &$gravadas, &$rejeitadas) {
            foreach ($payload as $alunoId => $notasPorDisc) {
                $alunoElig = $elegibilidade->get((int) $alunoId);
                if (!$alunoElig || !$alunoElig['elegivel']) {
                    $rejeitadas += count(array_filter($notasPorDisc, fn ($v) => $v !== null && $v !== ''));
                    continue;
                }
                $discsElegiveisIds = collect($alunoElig['disciplinas_elegiveis'])->pluck('disciplina_id')->all();

                foreach ($notasPorDisc as $discId => $valor) {
                    if (!in_array((int) $discId, $discsElegiveisIds, true)) {
                        if ($valor !== null && $valor !== '') {
                            $rejeitadas++;
                        }
                        continue;
                    }
                    $this->melhoriaService->gravarMelhoria(
                        (int) $alunoId,
                        (int) $discId,
                        $turma->id,
                        $valor === null || $valor === '' ? null : (float) $valor,
                        $anoLectivo
                    );
                    $gravadas++;
                }
            }
        });

        $msg = "Notas de melhoria guardadas: {$gravadas}.";
        if ($rejeitadas > 0) {
            $msg .= " {$rejeitadas} entrada(s) rejeitada(s) (aluno/disciplina sem elegibilidade).";
        }

        return redirect()
            ->route('melhorias.lancar', ['turma' => $turma, 'ano_lectivo' => $anoLectivo])
            ->with('success', $msg);
    }
}
