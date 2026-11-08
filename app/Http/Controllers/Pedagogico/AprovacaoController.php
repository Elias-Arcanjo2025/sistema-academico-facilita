<?php

namespace App\Http\Controllers\Pedagogico;

use App\Http\Controllers\Controller;
use App\Models\Pedagogico\PedPlanificacao;
use App\Models\Pedagogico\PedProva;
use App\Services\Pedagogico\PedagogicoAprovacaoService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AprovacaoController extends Controller
{
    public function __construct(
        private PedagogicoAprovacaoService $aprovacao,
    ) {
        $this->middleware('can:pedagogico.aprovar');
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $planificacoes = PedPlanificacao::query()
            ->with(['turma', 'disciplina', 'professor'])
            ->where('estado', PedPlanificacao::ESTADO_PENDENTE)
            ->latest('submetido_em')
            ->get()
            ->filter(fn (PedPlanificacao $p) => $this->aprovacao->podeAprovarPlanificacao($user, $p));

        $provas = PedProva::query()
            ->with(['turma', 'disciplina', 'planificacao.professor'])
            ->where('estado', PedProva::ESTADO_PENDENTE)
            ->latest('submetido_em')
            ->get()
            ->filter(fn (PedProva $p) => $this->aprovacao->podeAprovarProva($user, $p));

        return view('pedagogico.aprovacoes.index', [
            'planificacoes' => $planificacoes->values(),
            'provas' => $provas->values(),
        ]);
    }
}
