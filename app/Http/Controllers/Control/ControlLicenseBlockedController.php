<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Services\Control\ControlLicenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ControlLicenseBlockedController extends Controller
{
    public function __invoke(Request $request, ControlLicenseService $licenseService): View|RedirectResponse
    {
        // A página de bloqueio faz bypass do middleware — revalidar aqui
        // para não ficar preso quando o hub já reactivou a escola.
        if ($licenseService->isSchoolMode() && ! $licenseService->bypassEnabled()) {
            try {
                $licenseService->forceRefreshFromHub();
            } catch (\Throwable) {
                // Mantém o ecrã de bloqueio se o hub estiver inacessível
            }
        }

        if (! $licenseService->shouldBlockAccess()) {
            if (auth()->check()) {
                return redirect()->intended(route('dashboard'));
            }

            return redirect()->route('login');
        }

        return view('control.license-blocked', [
            'message' => $licenseService->blockMessage(),
            'state' => $licenseService->currentState(),
            'gracePeriod' => $licenseService->isGracePeriodExpired(),
        ]);
    }
}
