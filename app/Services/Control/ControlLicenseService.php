<?php

namespace App\Services\Control;

use App\Models\Control\ControlLicenseState;
use App\Models\User;
use App\Support\CargosInstitucionais;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ControlLicenseService
{
    public const CACHE_KEY = 'facilita.control.license';

    public function __construct(
        private readonly ControlHubClient $hubClient,
        private readonly ControlMetricsCollector $metricsCollector,
    ) {}

    public function mode(): string
    {
        return (string) config('control.mode', 'off');
    }

    public function isSchoolMode(): bool
    {
        return $this->mode() === 'school';
    }

    public function isHubMode(): bool
    {
        return $this->mode() === 'hub';
    }

    public function isEnabled(): bool
    {
        return in_array($this->mode(), ['school', 'hub'], true);
    }

    public function bypassEnabled(): bool
    {
        return (bool) config('control.bypass', false);
    }

    public function currentState(): ControlLicenseState
    {
        return ControlLicenseState::current();
    }

    /**
     * Indica se a instalação deve bloquear o acesso dos utilizadores.
     */
    public function shouldBlockAccess(): bool
    {
        if (! $this->isSchoolMode() || $this->bypassEnabled()) {
            return false;
        }

        $state = $this->cachedState();

        if (($state['status'] ?? null) === ControlLicenseState::STATUS_BLOQUEADO) {
            return true;
        }

        return $this->isGracePeriodExpired($state);
    }

    /**
     * Bloqueio por falta de contacto com o hub (não é bloqueio administrativo).
     *
     * @param  array{status?: string, checked_at?: ?string}|null  $state
     */
    public function isGracePeriodExpired(?array $state = null): bool
    {
        $state ??= $this->cachedState();

        if (($state['status'] ?? null) === ControlLicenseState::STATUS_BLOQUEADO) {
            return false;
        }

        $checkedAt = $state['checked_at'] ?? null;
        if (! $checkedAt || ! $this->hubClient->isConfigured()) {
            return false;
        }

        $graceHours = max(1, (int) config('control.grace_hours', 72));

        return \Carbon\Carbon::parse($checkedAt)->lt(now()->subHours($graceHours));
    }

    public function blockMessage(): string
    {
        $state = $this->cachedState();

        if (($state['status'] ?? null) === ControlLicenseState::STATUS_BLOQUEADO) {
            return $state['message']
                ?? 'A licença desta instalação está suspensa. Contacte o suporte Facilita.';
        }

        if ($this->isGracePeriodExpired($state)) {
            return 'Não foi possível validar a licença junto do Controlo Facilita há mais de '
                .(int) config('control.grace_hours', 72)
                .' horas. Verifique a ligação ao hub ou peça «Sincronizar agora» no painel central.';
        }

        return $state['message']
            ?? 'A licença desta instalação está suspensa. Contacte o suporte Facilita.';
    }

    /**
     * Força consulta ao hub (ignora throttle) — útil na página de bloqueio / grace period.
     */
    public function forceRefreshFromHub(): ControlLicenseState
    {
        Cache::forget('facilita.control.license_check_lock');
        $this->bustCache();

        return $this->refreshLicenseOnly();
    }

    /**
     * Aviso não bloqueante (em cobrança / em atraso) para banner na UI.
     * Visível apenas a coordenador de curso e cargos superiores.
     *
     * @return array{level: string, message: string, cobranca: ?array}|null
     */
    public function warningBanner(?User $user = null): ?array
    {
        if (! $this->isSchoolMode() || $this->bypassEnabled() || $this->shouldBlockAccess()) {
            return null;
        }

        $user ??= auth()->user();
        if (! $user || ! $user->hasAnyRole(CargosInstitucionais::veAvisoCobrancaFacilita())) {
            return null;
        }

        $state = $this->cachedState();
        $status = $state['status'] ?? null;

        if (! in_array($status, [
            ControlLicenseState::STATUS_EM_COBRANCA,
            ControlLicenseState::STATUS_EM_ATRASO,
        ], true)) {
            return null;
        }

        $level = $status === ControlLicenseState::STATUS_EM_ATRASO ? 'danger' : 'warning';

        return [
            'level' => $level,
            'message' => $state['message']
                ?? ($status === ControlLicenseState::STATUS_EM_ATRASO
                    ? 'Pagamento em atraso. Regularize para evitar o bloqueio do sistema.'
                    : 'Período de cobrança Facilita em curso.'),
            'cobranca' => $state['cobranca'] ?? null,
        ];
    }

    public function refreshFromHub(bool $withSync = true): ControlLicenseState
    {
        if (! $this->isSchoolMode() || ! $this->hubClient->isConfigured()) {
            return $this->currentState();
        }

        try {
            $payload = $this->metricsCollector->collect();
            $response = $this->hubClient->heartbeat($payload);

            if ($withSync) {
                $alunos = $this->metricsCollector->collectAlunos($payload['ano_lectivo'] ?? null);
                $this->hubClient->syncAlunos([
                    'installation_id' => $payload['installation_id'],
                    'ano_lectivo' => $payload['ano_lectivo'] ?? null,
                    'alunos' => $alunos,
                    'reported_at' => now()->toIso8601String(),
                ]);
            }

            return $this->applyHubResponse($response);
        } catch (\Throwable $e) {
            Log::warning('Controlo: falha ao contactar hub', [
                'error' => $e->getMessage(),
            ]);

            // Não actualiza checked_at — o grace period usa o último contacto bem-sucedido
            return $this->currentState();
        }
    }

    /**
     * Consulta leve ao hub (GET /license) para aplicar bloqueio/cobrança
     * sem esperar o sync completo de métricas/alunos.
     */
    public function refreshLicenseOnly(): ControlLicenseState
    {
        if (! $this->isSchoolMode() || ! $this->hubClient->isConfigured()) {
            return $this->currentState();
        }

        try {
            return $this->applyHubResponse($this->hubClient->licenseStatus());
        } catch (\Throwable $e) {
            Log::warning('Controlo: falha ao consultar licença no hub', [
                'error' => $e->getMessage(),
            ]);

            return $this->currentState();
        }
    }

    /**
     * Actualiza a licença a partir do hub no máximo a cada N minutos
     * (pedidos web). Assim um bloqueio no hub aplica-se depressa na escola.
     */
    public function maybeRefreshLicenseFromHub(): ControlLicenseState
    {
        if (! $this->isSchoolMode() || ! $this->hubClient->isConfigured() || $this->bypassEnabled()) {
            return $this->currentState();
        }

        $minutes = max(1, (int) config('control.license_check_minutes', 1));
        $lockKey = 'facilita.control.license_check_lock';

        if (! Cache::add($lockKey, 1, now()->addMinutes($minutes))) {
            return $this->currentState();
        }

        $state = $this->refreshLicenseOnly();
        $payload = $state->payload ?? [];

        if (! empty($payload['sync_requested'])) {
            return $this->refreshFromHub(withSync: true);
        }

        return $state;
    }

    public function applyHubResponse(array $response): ControlLicenseState
    {
        $status = (string) ($response['status'] ?? ControlLicenseState::STATUS_DESCONHECIDO);
        $allowed = [
            ControlLicenseState::STATUS_ACTIVO,
            ControlLicenseState::STATUS_EM_COBRANCA,
            ControlLicenseState::STATUS_EM_ATRASO,
            ControlLicenseState::STATUS_BLOQUEADO,
        ];

        if (! in_array($status, $allowed, true)) {
            $status = ControlLicenseState::STATUS_DESCONHECIDO;
        }

        $state = $this->currentState();
        $state->fill([
            'status' => $status,
            'message' => $response['message'] ?? $state->message,
            'checked_at' => now(),
            'hub_reported_at' => now(),
            'blocked_at' => $status === ControlLicenseState::STATUS_BLOQUEADO ? ($state->blocked_at ?? now()) : null,
            'payload' => $response,
        ]);
        $state->save();

        $this->bustCache();

        return $state->fresh();
    }

    /**
     * @return array{status: string, message: ?string, checked_at: ?string, blocked_at: ?string, warning_level: ?string, cobranca: ?array}
     */
    public function cachedState(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, function () {
            $state = $this->currentState();
            $payload = $state->payload ?? [];

            return [
                'status' => $state->status,
                'message' => $state->message,
                'checked_at' => optional($state->checked_at)?->toIso8601String(),
                'blocked_at' => optional($state->blocked_at)?->toIso8601String(),
                'warning_level' => $payload['warning_level'] ?? null,
                'cobranca' => $payload['cobranca'] ?? null,
            ];
        });
    }

    public function bustCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
