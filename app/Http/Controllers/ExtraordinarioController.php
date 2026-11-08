<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesNotasTurma;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\Turma;
use App\Services\ExtraordinarioService;
use App\Support\TurmaExameRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Gestão do Exame Extraordinário.
 *
 * Base legal: Decreto Executivo n.º 424/25 — Art.º 32.º.
 */
class ExtraordinarioController extends Controller
{
    use AuthorizesNotasTurma;

    public function __construct(private ExtraordinarioService $service)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $redirect = TurmaExameRedirect::paraLancamento($request, 'extraordinarios.lancar', [9, 12]);
        if ($redirect) {
            return $redirect;
        }

        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());

        $turmas = Turma::with(['classe', 'curso', 'anoLectivo'])
            ->where('activo', true)
            ->whereHas('classe', function ($q) {
                $q->where('codigo', 'like', '12%')
                  ->orWhere('codigo', 'like', '9%')
                  ->orWhere('nome', 'like', '12%')
                  ->orWhere('nome', 'like', '9%');
            })
            ->orderBy('nome')
            ->get();

        $config = $this->service->configuracoes();

        return view('extraordinarios.index', compact('turmas', 'anoLectivo', 'config'));
    }

    /**
     * IMPORTANTE: o ano lectivo usado é SEMPRE o da turma (porque o Extraordinário
     * recai sobre notas desse ano). Só se aceita override via ?ano_lectivo=... explicito.
     */
    public function lancar(Request $request, Turma $turma): View
    {
        $this->assertAcessoTurma($turma);

        abort_unless(
            in_array($turma->numeroClasse(), [9, 12], true),
            404,
            'Exame extraordinário aplica-se às turmas da 9.ª e 12.ª classes.',
        );

        $turma->load(['alunos', 'curso.disciplinas', 'classe', 'anoLectivo']);

        $anoLectivo = $request->input('ano_lectivo')
            ?: ($turma->anoLectivo?->nome ?? $turma->ano_lectivo)
            ?: Configuracao::getAnoLectivoActual();

        $elegibilidade = $this->service->calcularElegibilidade($turma, $anoLectivo);
        $config = $this->service->configuracoes();

        $disciplinas = $turma->curso?->disciplinas->keyBy('id') ?? collect();

        $extraordinariosExistentes = [];
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);
        $notas = Nota::whereIn('aluno_id', $turma->alunos->pluck('id'))
            ->where('tipo', Nota::TIPO_EXTRAORDINARIO)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('trimestre', 3)
            ->get();
        foreach ($notas as $n) {
            $extraordinariosExistentes[$n->aluno_id][$n->disciplina_id] = (float) $n->valor;
        }

        return view('extraordinarios.lancar', compact(
            'turma', 'anoLectivo', 'elegibilidade', 'config', 'disciplinas', 'extraordinariosExistentes'
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
            'extraordinarios' => ['nullable', 'array'],
            'extraordinarios.*' => ['nullable', 'array'],
            'extraordinarios.*.*' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ]);

        $payload = $validated['extraordinarios'] ?? [];

        $elegibilidade = $this->service->calcularElegibilidade($turma, $anoLectivo)
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
                    $this->service->gravarExtraordinario(
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

        $msg = "Notas extraordinárias guardadas: {$gravadas}.";
        if ($rejeitadas > 0) {
            $msg .= " {$rejeitadas} entrada(s) rejeitada(s) (aluno/disciplina sem elegibilidade).";
        }

        return redirect()
            ->route('extraordinarios.lancar', ['turma' => $turma, 'ano_lectivo' => $anoLectivo])
            ->with('success', $msg);
    }
}
