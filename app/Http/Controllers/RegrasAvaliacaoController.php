<?php

namespace App\Http\Controllers;

use App\Models\Configuracao;
use App\Services\ConfiguracaoService;
use App\Services\RecuperacaoService;
use App\Services\RecursoService;
use App\Support\PautaResultadoTransicao;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegrasAvaliacaoController extends Controller
{
    public function __construct(
        protected ConfiguracaoService $configuracao,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $notaMinima = $this->configuracao->getNotaMinimaAprovacao();
        $recursoCfg = app(RecursoService::class)->configuracoes();

        $viewData = [
            'notaMinima' => $notaMinima,
            'recursoCfg' => $recursoCfg,
            'anoLectivo' => Configuracao::getAnoLectivoActual(),
            'trimestre' => $this->configuracao->getTrimestreActual(),
            'transicao' => [
                'max_negativas' => PautaResultadoTransicao::MAX_NEGATIVAS_TRANSITA,
                'max_negativas_continuidade' => PautaResultadoTransicao::MAX_NEGATIVAS_CONTINUIDADE,
            ],
        ];

        if ($request->user()->hasRole('aluno')) {
            return view('portal.aluno.regras-avaliacao', $viewData);
        }

        return view('regras-avaliacao.index', $viewData);
    }
}
