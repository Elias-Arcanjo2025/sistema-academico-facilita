<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PwaController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** @deprecated Use PwaController::manifestPortal — mantido para compatibilidade de rotas. */
class PortalPwaController extends Controller
{
    public function manifest(Request $request): JsonResponse
    {
        return app(PwaController::class)->manifestPortal($request);
    }
}
