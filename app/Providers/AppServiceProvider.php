<?php

namespace App\Providers;

use App\Events\NotaAlterada;
use App\Listeners\RecalcularMediasAposAlteracaoNota;
use App\Models\ConselhoTrimestre;
use App\Models\Configuracao;
use App\Models\Aluno;
use App\Models\PautaArquivo;
use App\Models\Turma;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\UserPolicy;
use App\Policies\ConselhoPolicy;
use App\Policies\PautaArquivoPolicy;
use App\Policies\Pedagogico\PedPlanificacaoPolicy;
use App\Policies\CoordenadorDisciplinaPolicy;
use App\Models\Pedagogico\PedPlanificacao;
use App\Models\CoordenadorDisciplina;
use App\Services\AI\AIServiceInterface;
use App\Services\AI\FallbackAIService;
use App\Services\AI\GeminiService;
use App\Services\AI\OpenRouterService;
use App\Services\OCR\CompositeOCRService;
use App\Services\OCR\OCRServiceInterface;
use App\Services\ConfiguracaoService;
use App\Support\TemaInstitucional;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AIServiceInterface::class, function ($app) {
            if (config('ai.fallback_enabled', true)) {
                return $app->make(FallbackAIService::class);
            }

            $driver = config('ai.primary_driver', config('pedagogico.ai_driver', 'gemini'));

            return match ($driver) {
                'openrouter' => $app->make(OpenRouterService::class),
                'gemini' => $app->make(GeminiService::class),
                default => $app->make(GeminiService::class),
            };
        });
        $this->app->bind(OCRServiceInterface::class, CompositeOCRService::class);
    }

    public function boot(): void
    {
        Route::bind('turma', function (string $value) {
            if (ctype_digit($value)) {
                return Turma::whereKey((int) $value)->firstOrFail();
            }

            return Turma::where('public_uuid', $value)->firstOrFail();
        });

        // Aceita ID numérico (links legados) ou public_uuid — Aluno usa HasPublicUuid.
        Route::bind('aluno', function (string $value) {
            if (ctype_digit($value)) {
                return Aluno::whereKey((int) $value)->firstOrFail();
            }

            return Aluno::where('public_uuid', $value)->firstOrFail();
        });

        Route::bind('escola', function (string $value) {
            if (request()->routeIs('control.hub.*')) {
                return \App\Models\Control\ControlEscola::query()->whereKey($value)->firstOrFail();
            }

            abort(404);
        });

        Route::bind('cobranca', function (string $value) {
            if (request()->routeIs('control.hub.*')) {
                return \App\Models\Control\ControlCobranca::query()->whereKey($value)->firstOrFail();
            }

            abort(404);
        });

        // Configurar paginação Bootstrap 5
        Paginator::useBootstrapFive();

        // Configurar rate limiters para exportações
        $this->configureRateLimiters();

        // Admin Full tem acesso total a todas as funcionalidades
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('admin_full')) {
                return true;
            }
        });

        // Registar Policies
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(PautaArquivo::class, PautaArquivoPolicy::class);
        Gate::policy(ConselhoTrimestre::class, ConselhoPolicy::class);
        Gate::policy(PedPlanificacao::class, PedPlanificacaoPolicy::class);
        Gate::policy(CoordenadorDisciplina::class, CoordenadorDisciplinaPolicy::class);

        Gate::define('backup.viewAny', [\App\Policies\BackupPolicy::class, 'viewAny']);
        Gate::define('backup.create', [\App\Policies\BackupPolicy::class, 'create']);
        Gate::define('backup.delete', [\App\Policies\BackupPolicy::class, 'delete']);
        Gate::define('queue.viewAny', [\App\Policies\QueuePolicy::class, 'viewAny']);
        Gate::define('queue.delete', [\App\Policies\QueuePolicy::class, 'delete']);
        Gate::define('queue.create', [\App\Policies\BackupPolicy::class, 'create']);

        // Registar eventos
        Event::listen(
            NotaAlterada::class,
            RecalcularMediasAposAlteracaoNota::class
        );

        Event::listen(
            \Illuminate\Queue\Events\JobFailed::class,
            \App\Listeners\NotificarAdminsJobFalhado::class
        );

        View::composer(['layouts.app', 'layouts.portal-aluno', 'layouts.portal-encarregado'], function ($view) {
            if (! auth()->check()) {
                return;
            }

            $configuracaoService = app(ConfiguracaoService::class);
            $licenseWarning = null;

            // Banner de cobrança: só no layout staff e só coordenador+ (nunca portal)
            if (config('control.mode') === 'school' && $view->name() === 'layouts.app') {
                $licenseWarning = app(\App\Services\Control\ControlLicenseService::class)
                    ->warningBanner(auth()->user());
            }

            $view->with([
                'bloqueioInactividadeActivo' => $configuracaoService->bloqueioInactividadeActivo(),
                'bloqueioInactividadeMinutos' => $configuracaoService->bloqueioInactividadeMinutos(),
                'controlLicenseWarning' => $licenseWarning,
            ]);
        });

        View::composer([
            'portal.aluno.partials.topbar',
            'portal.aluno.partials.notificacoes-offcanvas',
        ], function ($view) {
            $user = auth()->user();

            if (! $user || ! $user->hasRole('aluno')) {
                return;
            }

            $view->with([
                'notificacoesNaoLidas' => $user->unreadNotifications()->count(),
                'notificacoesRecentes' => $user->notifications()->latest()->limit(15)->get(),
            ]);
        });

        View::composer(['layouts.portal-encarregado'], function ($view) {
            $user = auth()->user();

            if (! $user || ! $user->hasRole('encarregado')) {
                return;
            }

            $view->with([
                'notificacoesNaoLidas' => $user->unreadNotifications()->count(),
            ]);
        });

        View::composer([
            'layouts.app',
            'layouts.partials.staff-notificacoes-offcanvas',
            'notificacoes.index',
        ], function ($view) {
            $user = auth()->user();

            if (! $user || $user->hasRole('aluno') || $user->hasRole('encarregado')) {
                return;
            }

            $view->with([
                'notificacoesNaoLidas' => $user->unreadNotifications()->count(),
                'notificacoesRecentes' => $user->notifications()->latest()->limit(15)->get(),
            ]);
        });

        View::composer([
            'layouts.app',
            'layouts.guest',
            'welcome',
            'layouts.portal-aluno',
            'layouts.portal-encarregado',
        ], function ($view) {
            $configuracaoService = app(ConfiguracaoService::class);
            $anoLectivo = Configuracao::getAnoLectivoActual();
            $paginaPublica = $configuracaoService->getPaginaPublica($anoLectivo);
            $payload = [
                'paginaPublica' => $paginaPublica,
                'temaInstituicao' => TemaInstitucional::fromPaginaPublica($paginaPublica),
                'instituicao' => $configuracaoService->getInstituicao(),
            ];

            if ($view->name() === 'welcome') {
                $payload['portalAlunoActivo'] = $configuracaoService->get('portal_aluno_notas_activo', '1') === '1';
                $payload['registoPublicoActivo'] = $configuracaoService->registoPublicoActivo();
                $payload['mostrarRegistoPublico'] = $configuracaoService->paginaPublicaMostrarRegisto();
            }

            $view->with($payload);
        });
    }

    /**
     * Configurar rate limiters personalizados
     */
    protected function configureRateLimiters(): void
    {
        // Rate limiter para exportações individuais (PDFs, Excel)
        // Limite: 20 requisições por minuto por usuário
        RateLimiter::for('exports', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Muitas requisições de exportação. Por favor, aguarde um momento.'
                    ], 429);
                });
        });

        // Rate limiter mais restritivo para operações em lote
        // Limite: 5 requisições por minuto por usuário
        RateLimiter::for('exports-batch', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Limite de exportações em lote atingido. Aguarde antes de gerar novos boletins.'
                    ], 429);
                });
        });

        RateLimiter::for('ai-generation', function (Request $request) {
            $max = config('pedagogico.rate_limit.max_attempts', 20);
            $decay = config('pedagogico.rate_limit.decay_minutes', 60);

            return Limit::perMinutes($decay, $max)->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Limite de gerações IA atingido. Tente novamente mais tarde.',
                    ], 429);
                });
        });

        RateLimiter::for('assistant-chat', function (Request $request) {
            $max = config('assistente.rate_limit.max_attempts', 40);
            $decay = config('assistente.rate_limit.decay_minutes', 60);

            return Limit::perMinutes($decay, $max)->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Limite de mensagens do assistente atingido. Aguarde um momento.',
                    ], 429);
                });
        });
    }
}
