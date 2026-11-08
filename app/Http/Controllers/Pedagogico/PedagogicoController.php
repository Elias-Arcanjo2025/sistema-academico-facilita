<?php

namespace App\Http\Controllers\Pedagogico;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PedagogicoController extends Controller
{
    use AuthorizesRequests;

    public function index(): View|RedirectResponse
    {
        $this->authorize('viewAny', \App\Models\Pedagogico\PedPlanificacao::class);

        if (auth()->user()->can('pedagogico.dashboard.ver')) {
            return redirect()->route('pedagogico.dashboard.index');
        }

        return view('pedagogico.index');
    }
}
