<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        $rotasPermitidas = [
            'password.force.edit',
            'password.force.update',
            'logout',
        ];

        if ($request->routeIs(...$rotasPermitidas)) {
            return $next($request);
        }

        return redirect()->route('password.force.edit')
            ->with('warning', 'Por segurança, deve alterar a sua palavra-passe antes de continuar.');
    }
}
