<?php

namespace App\Http\Controllers;

use App\Services\Assistente\AssistantAuditService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class AssistenteAuditoriaController extends Controller
{
    public function __construct(
        protected AssistantAuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Activity::class);

        $periodo = (int) $request->input('periodo', 30);
        if (! in_array($periodo, [7, 30, 90, 365], true)) {
            $periodo = 30;
        }

        $resumo = $this->audit->resumo($periodo);
        $porDia = $this->audit->porDia($periodo);
        $topUtilizadores = $this->audit->topUtilizadores($periodo);
        $porPerfil = $this->audit->porPerfil($periodo);
        $conversas = $this->audit->conversasRecentes($periodo);

        return view('assistente.auditoria', compact(
            'periodo',
            'resumo',
            'porDia',
            'topUtilizadores',
            'porPerfil',
            'conversas',
        ));
    }
}
