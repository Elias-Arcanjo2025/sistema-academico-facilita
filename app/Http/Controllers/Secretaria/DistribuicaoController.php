<?php

namespace App\Http\Controllers\Secretaria;

use App\Http\Controllers\Controller;
use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\MatriculaHistorico;
use App\Models\Turma;
use App\Services\MatriculaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DistribuicaoController extends Controller
{
    public function __construct(
        private MatriculaService $matriculaService,
    ) {
        $this->middleware('can:secretaria.matriculas.gerir');
    }

    public function create(Request $request): View
    {
        $anoId = Configuracao::getAnoLectivoActualId();
        $turmas = Turma::activo()
            ->where('ano_lectivo_id', $anoId)
            ->with('curso')
            ->orderBy('nome')
            ->get();

        $turmaOrigemId = $request->integer('turma_origem_id') ?: null;
        $alunos = collect();

        if ($turmaOrigemId) {
            $alunos = Aluno::query()
                ->where('turma_id', $turmaOrigemId)
                ->activo()
                ->orderBy('nome')
                ->get(['id', 'nome', 'numero_processo', 'numero_ordem']);
        }

        return view('secretaria.distribuicao.create', [
            'turmas' => $turmas,
            'turmaOrigemId' => $turmaOrigemId,
            'alunos' => $alunos,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'turma_origem_id' => ['required', 'exists:turmas,id'],
            'turma_destino_id' => ['required', 'exists:turmas,id', 'different:turma_origem_id'],
            'aluno_ids' => ['required', 'array', 'min:1'],
            'aluno_ids.*' => ['integer', 'exists:alunos,id'],
            'motivo' => ['required', 'string', 'max:500'],
            'confirmar_troca_curso' => ['nullable', 'boolean'],
        ]);

        $origem = Turma::with('curso')->findOrFail($data['turma_origem_id']);
        $destino = Turma::with('curso')->findOrFail($data['turma_destino_id']);

        if ((int) $origem->ano_lectivo_id !== (int) $destino->ano_lectivo_id) {
            return back()->with('error', 'As turmas devem ser do mesmo ano lectivo.');
        }

        $trocaCurso = (int) $origem->curso_id !== (int) $destino->curso_id;
        if ($trocaCurso && ! $request->user()->podeTransferirEntreCursos()) {
            return back()->with('error', 'Não tem permissão para distribuir alunos entre cursos diferentes.');
        }
        if ($trocaCurso && ! $request->boolean('confirmar_troca_curso')) {
            return back()->with('error', 'Confirme explicitamente a distribuição entre cursos diferentes.');
        }

        $movidos = 0;

        DB::transaction(function () use ($data, $origem, $destino, $trocaCurso, &$movidos) {
            $alunos = Aluno::query()
                ->whereIn('id', $data['aluno_ids'])
                ->where('turma_id', $origem->id)
                ->activo()
                ->get();

            foreach ($alunos as $aluno) {
                $aluno->update([
                    'turma_id' => $destino->id,
                    'numero_ordem' => null,
                ]);

                $tipo = $trocaCurso
                    ? MatriculaHistorico::TIPO_MUDANCA_CURSO
                    : MatriculaHistorico::TIPO_TRANSFERENCIA;

                $this->matriculaService->registarHistorico($aluno->fresh(), $tipo, [
                    'turma_id' => $destino->id,
                    'de_turma_id' => $origem->id,
                    'para_turma_id' => $destino->id,
                    'ano_lectivo_id' => $destino->ano_lectivo_id,
                    'motivo' => $data['motivo'],
                    'dados' => ['distribuicao_lote' => true],
                ]);

                $movidos++;
            }

            $this->matriculaService->recalcularNumerosOrdem($origem->id);
            $this->matriculaService->recalcularNumerosOrdem($destino->id);
        });

        return redirect()
            ->route('secretaria.distribuicao.create', ['turma_origem_id' => $destino->id])
            ->with('success', "{$movidos} aluno(s) distribuído(s) para {$destino->nome}.");
    }
}
