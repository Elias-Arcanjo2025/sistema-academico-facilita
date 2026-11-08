<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Turma;
use App\Services\Conformidade\ConformidadePautaService;
use App\Services\Conformidade\SimuladorConselhoService;
use App\Services\ConfiguracaoService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PautaCertificadaController extends Controller
{
    public function __construct(
        private readonly ConformidadePautaService $conformidade,
        private readonly SimuladorConselhoService $simulador,
        private readonly ConfiguracaoService $configuracao,
    ) {
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            if (! $user || ! $user->temVisaoInstitucional()) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $anoId = \App\Models\Configuracao::getAnoLectivoActualId();
        $ano = $anoId ? \App\Models\Configuracao::getAnoLectivoActual() : null;

        $turmas = $anoId
            ? Turma::query()
                ->with(['curso', 'classe'])
                ->where('ano_lectivo_id', $anoId)
                ->where('activo', true)
                ->orderBy('nome')
                ->get()
            : collect();

        return view('conformidade.index', [
            'turmas' => $turmas,
            'trimestre' => (int) $this->configuracao->getTrimestreActual(),
            'anoLectivo' => $ano,
            'semAnoLectivo' => ! $anoId,
        ]);
    }

    public function analisar(Request $request, Turma $turma): View
    {
        $data = $request->validate([
            'trimestre' => ['required', 'integer', 'min:1', 'max:3'],
        ]);

        $turma->loadMissing(['curso', 'classe']);

        $analise = $this->conformidade->analisarTurma($turma, (int) $data['trimestre']);
        $relatorio = $this->conformidade->guardar($turma, $analise, $request->user());

        $disciplinas = \App\Models\Disciplina::query()
            ->paraTurma($turma)
            ->orderBy('nome')
            ->get(['id', 'nome', 'codigo']);

        $alunos = $turma->alunos()
            ->where('activo', true)
            ->orderBy('numero_ordem')
            ->orderBy('nome')
            ->get(['id', 'nome', 'numero_processo', 'numero_ordem']);

        return view('conformidade.show', [
            'turma' => $turma,
            'analise' => $analise,
            'relatorio' => $relatorio,
            'disciplinas' => $disciplinas,
            'alunos' => $alunos,
        ]);
    }

    public function simular(Request $request, Turma $turma): View
    {
        $request->merge([
            'aluno_id' => $request->filled('aluno_id') ? $request->input('aluno_id') : null,
        ]);

        $data = $request->validate([
            'trimestre' => ['required', 'integer', 'min:1', 'max:3'],
            'aluno_id' => ['nullable', 'integer', 'exists:alunos,id'],
            'disciplina_id' => ['required', 'integer', 'exists:disciplinas,id'],
            'tipo' => ['required', 'string', 'in:MAC,PP,PT,PG,mac,pp,pt,pg'],
            'delta' => ['required', 'numeric', 'min:-5', 'max:5'],
        ]);

        $ajuste = [
            'disciplina_id' => (int) $data['disciplina_id'],
            'tipo' => strtoupper($data['tipo']),
            'delta' => (float) $data['delta'],
        ];

        if (! empty($data['aluno_id'])) {
            $ajuste['aluno_id'] = (int) $data['aluno_id'];
        }

        $resultado = $this->simulador->simularTurma(
            $turma,
            (int) $data['trimestre'],
            [$ajuste],
            isset($data['aluno_id']) ? (int) $data['aluno_id'] : null,
        );

        return view('conformidade.simulador', [
            'turma' => $turma,
            'resultado' => $resultado,
            'input' => $data,
        ]);
    }

    public function exportarInspeccao(Request $request, Turma $turma): StreamedResponse
    {
        $data = $request->validate([
            'trimestre' => ['required', 'integer', 'min:1', 'max:3'],
        ]);

        $analise = $this->conformidade->analisarTurma($turma, (int) $data['trimestre']);
        $this->conformidade->guardar($turma, $analise, $request->user());

        $filename = 'pauta-certificada-'.$turma->codigo.'-T'.$data['trimestre'].'-'.now()->format('Ymd_His').'.json';

        return response()->streamDownload(function () use ($analise) {
            echo json_encode($analise, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
}
