<?php

namespace App\Http\Controllers;

use App\Models\Sala;
use App\Models\Turno;
use App\Services\OcupacaoSalaService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OcupacaoController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected OcupacaoSalaService $ocupacaoService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Sala::class);

        $anoLectivoId = $this->ocupacaoService->resolverAnoLectivoId(
            $request->filled('ano_lectivo_id') ? (int) $request->ano_lectivo_id : null
        );
        $turnoId = $request->filled('turno_id') ? (int) $request->turno_id : null;

        return view('ocupacao.index', $this->ocupacaoService->dadosPorSala($anoLectivoId, $turnoId, $request));
    }

    public function porTurno(Request $request): View
    {
        $this->authorize('viewAny', Turno::class);

        $anoLectivoId = $this->ocupacaoService->resolverAnoLectivoId(
            $request->filled('ano_lectivo_id') ? (int) $request->ano_lectivo_id : null
        );

        return view('ocupacao.por-turno', $this->ocupacaoService->dadosPorTurno($anoLectivoId));
    }
}
