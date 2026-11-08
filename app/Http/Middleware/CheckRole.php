<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        foreach ($roles as $role) {
            if ($request->user()->hasRole($role)) {
                return $next($request);
            }
        }

        activity()
            ->causedBy($request->user())
            ->withProperties([
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
                'roles_requeridos' => $roles,
            ])
            ->log('Tentativa de acesso não autorizado');

        abort(403, 'O seu cargo não permite aceder a esta área. No menu lateral encontra apenas as funcionalidades disponíveis para si.');
    }
}
