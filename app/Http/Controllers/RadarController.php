<?php

namespace App\Http\Controllers;

use App\Services\Radar\RadarPedagogicoService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RadarController extends Controller
{
    public function __construct(
        private readonly RadarPedagogicoService $radar,
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
        $dados = $this->radar->gerar($request->user());

        return view('radar.index', compact('dados'));
    }
}
