<?php

use App\Http\Middleware\AuthenticateControlInstallation;
use App\Http\Middleware\CheckAccountStatus;
use App\Http\Middleware\CheckAnoLectivoExists;
use App\Http\Middleware\CheckControlLicense;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\CheckSessaoBloqueada;
use App\Http\Middleware\EnsureAnyPermission;
use App\Http\Middleware\EnsureSetupInicial;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\RegistoPublicoActivo;
use App\Http\Middleware\RestrictToControlHub;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix(trim((string) config('control.api_prefix', 'api/control/v1'), '/'))
                ->group(base_path('routes/control.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => CheckRole::class,
            'account.status' => CheckAccountStatus::class,
            'ano.lectivo.required' => CheckAnoLectivoExists::class,
            'password.force' => \App\Http\Middleware\ForcePasswordChange::class,
            'sessao.bloqueada' => CheckSessaoBloqueada::class,
            'registo.publico' => RegistoPublicoActivo::class,
            'control.installation' => AuthenticateControlInstallation::class,
            'control.license' => CheckControlLicense::class,
            'control.hub.only' => RestrictToControlHub::class,
            'control.hub.push' => \App\Http\Middleware\AuthenticateHubPush::class,
            'permission.any' => EnsureAnyPermission::class,
            'setup.inicial' => EnsureSetupInicial::class,
        ]);

        $middleware->append(ForceHttps::class);
        $middleware->append(SecurityHeaders::class);

        $middleware->appendToGroup('web', [
            CheckAccountStatus::class,
            \App\Http\Middleware\ForcePasswordChange::class,
            EnsureSetupInicial::class,
            CheckSessaoBloqueada::class,
            \App\Http\Middleware\RedirectPortalAccounts::class,
            CheckControlLicense::class,
            RestrictToControlHub::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/whatsapp',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $mensagemAmigavel = function (?string $mensagem): string {
            $mensagem = trim((string) $mensagem);
            if ($mensagem === '' || str_contains(strtolower($mensagem), 'this action is unauthorized')) {
                return 'Não tem permissão para esta acção. O sistema mostra apenas as áreas do seu cargo — use o menu lateral.';
            }

            return $mensagem;
        };

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($mensagemAmigavel) {
            $msg = $mensagemAmigavel($e->getMessage());
            if ($request->expectsJson()) {
                return response()->json(['message' => $msg], 403);
            }

            return response()->view('errors.403', [
                'exception' => new AccessDeniedHttpException($msg, $e),
            ], 403);
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) use ($mensagemAmigavel) {
            $msg = $mensagemAmigavel($e->getMessage());
            if ($request->expectsJson()) {
                return response()->json(['message' => $msg], 403);
            }

            return response()->view('errors.403', [
                'exception' => new AccessDeniedHttpException($msg, $e),
            ], 403);
        });

        $exceptions->render(function (HttpException $e, Request $request) use ($mensagemAmigavel) {
            if ($e->getStatusCode() !== 403) {
                return null;
            }

            $msg = $mensagemAmigavel($e->getMessage());
            if ($request->expectsJson()) {
                return response()->json(['message' => $msg], 403);
            }

            return response()->view('errors.403', [
                'exception' => new AccessDeniedHttpException($msg, $e),
            ], 403);
        });
    })->create();
