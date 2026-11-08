<?php

namespace App\Services\Control;

use App\Models\Control\ControlAluno;
use App\Models\Control\ControlCobranca;
use App\Models\Control\ControlEscola;
use App\Models\Control\ControlLicenseEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ControlHubService
{
    public function findEscolaByInstallation(string $installationId): ?ControlEscola
    {
        return ControlEscola::query()
            ->where('installation_id', $installationId)
            ->where('activo', true)
            ->first();
    }

    public function verifyCredentials(ControlEscola $escola, string $plainSecret, string $timestamp, string $signature): bool
    {
        if (! $escola->verifySecret($plainSecret)) {
            return false;
        }

        $expected = hash_hmac(
            'sha256',
            $escola->installation_id.'|'.$timestamp,
            $plainSecret
        );

        return hash_equals($expected, $signature);
    }

    public function processHeartbeat(ControlEscola $escola, array $payload): array
    {
        $metricas = $payload['metricas'] ?? [];
        $instituicao = $payload['instituicao'] ?? [];

        $escola->fill([
            'nome' => $instituicao['nome'] ?? $escola->nome,
            'app_url' => $payload['app_url'] ?? $escola->app_url,
            'contacto_email' => $instituicao['email'] ?? $escola->contacto_email,
            'contacto_telefone' => $instituicao['telefone'] ?? $escola->contacto_telefone,
            'ano_lectivo' => $payload['ano_lectivo'] ?? $escola->ano_lectivo,
            'app_version' => $payload['app_version'] ?? $escola->app_version,
            'total_alunos' => (int) ($metricas['alunos'] ?? $escola->total_alunos),
            'total_professores' => (int) ($metricas['professores'] ?? $escola->total_professores),
            'total_cursos' => (int) ($metricas['cursos'] ?? $escola->total_cursos),
            'total_turmas' => (int) ($metricas['turmas'] ?? $escola->total_turmas),
            'total_salas' => (int) ($metricas['salas'] ?? $escola->total_salas),
            'total_disciplinas' => (int) ($metricas['disciplinas'] ?? $escola->total_disciplinas),
            'total_utilizadores' => (int) ($metricas['utilizadores'] ?? $escola->total_utilizadores),
            'metricas' => $metricas,
            'saude' => $payload['saude'] ?? $escola->saude,
            'last_heartbeat_at' => now(),
            'sync_requested_at' => null,
        ]);
        $escola->save();

        $this->logEvent($escola, 'heartbeat', $escola->status, $escola->status, null, null, [
            'metricas' => $metricas,
        ]);

        return $this->licensePayload($escola);
    }

    public function syncAlunos(ControlEscola $escola, array $payload): array
    {
        $alunos = $payload['alunos'] ?? [];
        $anoLectivo = $payload['ano_lectivo'] ?? $escola->ano_lectivo;
        $seen = [];

        DB::transaction(function () use ($escola, $alunos, $anoLectivo, &$seen) {
            foreach ($alunos as $row) {
                if (empty($row['aluno_uuid']) || empty($row['nome'])) {
                    continue;
                }

                $uuid = (string) $row['aluno_uuid'];
                $seen[] = $uuid;

                ControlAluno::query()->updateOrCreate(
                    [
                        'control_escola_id' => $escola->id,
                        'aluno_uuid' => $uuid,
                    ],
                    [
                        'aluno_local_id' => $row['aluno_local_id'] ?? null,
                        'numero_processo' => $row['numero_processo'] ?? null,
                        'nome' => $row['nome'],
                        'genero' => $row['genero'] ?? null,
                        'ano_lectivo' => $row['ano_lectivo'] ?? $anoLectivo,
                        'turma_codigo' => $row['turma_codigo'] ?? null,
                        'turma_nome' => $row['turma_nome'] ?? null,
                        'curso_nome' => $row['curso_nome'] ?? null,
                        'classe_nome' => $row['classe_nome'] ?? null,
                        'situacao_academica' => $row['situacao_academica'] ?? null,
                        'activo' => (bool) ($row['activo'] ?? true),
                        'synced_at' => now(),
                    ]
                );
            }

            // Alunos do mesmo ano que não vieram no sync → marcar inactivos
            if ($anoLectivo && count($seen) > 0) {
                ControlAluno::query()
                    ->where('control_escola_id', $escola->id)
                    ->where('ano_lectivo', $anoLectivo)
                    ->whereNotIn('aluno_uuid', $seen)
                    ->update(['activo' => false, 'synced_at' => now()]);
            }
        });

        $activos = ControlAluno::query()
            ->where('control_escola_id', $escola->id)
            ->where('activo', true)
            ->when($anoLectivo, fn ($q) => $q->where('ano_lectivo', $anoLectivo))
            ->count();

        $escola->update([
            'total_alunos' => $activos,
            'ano_lectivo' => $anoLectivo ?? $escola->ano_lectivo,
            'last_sync_at' => now(),
        ]);

        return [
            'synced' => count($seen),
            'total_alunos_activos' => $activos,
            'license' => $this->licensePayload($escola->fresh()),
        ];
    }

    public function licensePayload(ControlEscola $escola): array
    {
        // Garante que aviso/atraso/bloqueio vencidos aplicam-se mesmo sem o cron
        $this->aplicarPrazosCobrancaEscola($escola);
        $escola->refresh();

        $cobranca = $escola->cobrancaAberta();
        $status = $escola->status;

        // Cobrança criada mas ainda antes de aviso_em → escola continua "activa" na API
        if ($cobranca && $cobranca->aviso_em && $cobranca->aviso_em->isFuture()
            && in_array($status, [ControlEscola::STATUS_EM_COBRANCA, ControlEscola::STATUS_ACTIVO], true)) {
            $status = ControlEscola::STATUS_ACTIVO;
        }

        $message = match ($status) {
            ControlEscola::STATUS_BLOQUEADO => $escola->bloqueio_motivo
                ?: 'Licença suspensa por falta de pagamento. Contacte o suporte Facilita.',
            ControlEscola::STATUS_EM_ATRASO => $cobranca?->aviso_escola
                ?: 'Pagamento em atraso. Regularize junto do Facilita para evitar o bloqueio do sistema.',
            ControlEscola::STATUS_EM_COBRANCA => $cobranca?->aviso_escola
                ?: 'Período de cobrança em curso. O Facilita entrará em contacto para liquidação anual.',
            default => null,
        };

        $warningLevel = match ($status) {
            ControlEscola::STATUS_BLOQUEADO => 'blocked',
            ControlEscola::STATUS_EM_ATRASO => 'danger',
            ControlEscola::STATUS_EM_COBRANCA => 'warning',
            default => null,
        };

        return [
            'status' => $status,
            'message' => $message,
            'warning_level' => $warningLevel,
            'installation_id' => $escola->installation_id,
            'blocked' => $status === ControlEscola::STATUS_BLOQUEADO,
            'checked_at' => now()->toIso8601String(),
            'sync_requested' => $escola->sync_requested_at !== null
                && $escola->sync_requested_at->gt(now()->subHours(6)),
            'cobranca' => ($cobranca && $status !== ControlEscola::STATUS_ACTIVO) ? [
                'referencia' => $cobranca->referencia,
                'ano_lectivo' => $cobranca->ano_lectivo,
                'total_alunos' => $cobranca->total_alunos,
                'valor_total' => (float) $cobranca->valor_total,
                'vencimento_em' => optional($cobranca->vencimento_em)?->toDateString(),
                'aviso_em' => optional($cobranca->aviso_em)?->toIso8601String(),
                'atraso_em' => optional($cobranca->atraso_em)?->toIso8601String(),
                'bloqueio_em' => optional($cobranca->bloqueio_em)?->toIso8601String(),
                'status' => $cobranca->status,
            ] : null,
        ];
    }

    /**
     * Cria cobrança anual com base nos alunos activos e coloca a escola em cobrança.
     */
    public function criarCobranca(
        ControlEscola $escola,
        array $data,
        ?User $actor = null,
    ): ControlCobranca {
        if ($escola->cobrancaAberta()) {
            throw ValidationException::withMessages([
                'cobranca' => 'Já existe uma cobrança aberta para esta escola. Feche-a ou marque como paga.',
            ]);
        }

        $anoLectivo = $data['ano_lectivo'] ?? $escola->ano_lectivo;
        $totalAlunos = isset($data['total_alunos'])
            ? (int) $data['total_alunos']
            : (int) $escola->alunos()->where('activo', true)
                ->when($anoLectivo, fn ($q) => $q->where('ano_lectivo', $anoLectivo))
                ->count();

        if ($totalAlunos <= 0) {
            $totalAlunos = (int) $escola->total_alunos;
        }

        $preco = isset($data['preco_unitario'])
            ? (float) $data['preco_unitario']
            : (float) $escola->preco_anual_aluno;

        $valorTotal = isset($data['valor_total'])
            ? (float) $data['valor_total']
            : round($totalAlunos * $preco, 2);

        $aviso = $data['aviso_escola'] ?? sprintf(
            'Cobrança anual Facilita (%s): %d alunos × %s = %s. Regularize para manter o sistema activo.',
            $anoLectivo ?: 'ano corrente',
            $totalAlunos,
            number_format($preco, 2, ',', '.'),
            number_format($valorTotal, 2, ',', '.')
        );

        $minAviso = array_key_exists('minutos_ate_aviso', $data)
            ? (int) $data['minutos_ate_aviso']
            : (int) ($escola->minutos_ate_aviso ?? 0);
        $minAtraso = array_key_exists('minutos_ate_atraso', $data)
            ? ($data['minutos_ate_atraso'] !== null && $data['minutos_ate_atraso'] !== ''
                ? (int) $data['minutos_ate_atraso']
                : null)
            : $escola->minutos_ate_atraso;
        $minBloqueio = array_key_exists('minutos_ate_bloqueio', $data)
            ? ($data['minutos_ate_bloqueio'] !== null && $data['minutos_ate_bloqueio'] !== ''
                ? (int) $data['minutos_ate_bloqueio']
                : null)
            : $escola->minutos_ate_bloqueio;

        $agora = now();
        $avisoEm = $agora->copy()->addMinutes(max(0, $minAviso));
        $atrasoEm = $minAtraso !== null ? $agora->copy()->addMinutes(max(0, (int) $minAtraso)) : null;
        $bloqueioEm = $minBloqueio !== null ? $agora->copy()->addMinutes(max(0, (int) $minBloqueio)) : null;

        // Garantir ordem: aviso ≤ atraso ≤ bloqueio
        if ($atrasoEm && $atrasoEm->lt($avisoEm)) {
            $atrasoEm = $avisoEm->copy();
        }
        if ($bloqueioEm && $atrasoEm && $bloqueioEm->lt($atrasoEm)) {
            $bloqueioEm = $atrasoEm->copy();
        } elseif ($bloqueioEm && ! $atrasoEm && $bloqueioEm->lt($avisoEm)) {
            $bloqueioEm = $avisoEm->copy();
        }

        $cobranca = ControlCobranca::query()->create([
            'control_escola_id' => $escola->id,
            'ano_lectivo' => $anoLectivo,
            'total_alunos' => $totalAlunos,
            'preco_unitario' => $preco,
            'valor_total' => $valorTotal,
            'status' => ControlCobranca::STATUS_ABERTA,
            'vencimento_em' => $data['vencimento_em'] ?? now()->addDays(30)->toDateString(),
            'minutos_ate_aviso' => max(0, $minAviso),
            'minutos_ate_atraso' => $minAtraso,
            'minutos_ate_bloqueio' => $minBloqueio,
            'aviso_em' => $avisoEm,
            'atraso_em' => $atrasoEm,
            'bloqueio_em' => $bloqueioEm,
            'notas' => $data['notas'] ?? null,
            'aviso_escola' => $aviso,
            'criado_por' => $actor?->id,
        ]);

        $anterior = $escola->status;

        // Aplica já os prazos vencidos (ex.: aviso/bloqueio a 0 min) sem depender do cron
        $cobranca->setRelation('escola', $escola);
        $this->aplicarPrazosCobranca($cobranca, $actor, imediato: true);

        $this->logEvent($escola->fresh(), 'cobranca_criada', $anterior, $escola->fresh()->status, $aviso, $actor, [
            'referencia' => $cobranca->referencia,
            'valor_total' => $valorTotal,
            'total_alunos' => $totalAlunos,
            'aviso_em' => $avisoEm->toIso8601String(),
            'atraso_em' => optional($atrasoEm)?->toIso8601String(),
            'bloqueio_em' => optional($bloqueioEm)?->toIso8601String(),
        ]);

        return $cobranca->fresh();
    }

    /**
     * Aplica transições automáticas de cobrança (aviso → atraso → bloqueio)
     * com base nos timestamps definidos no Hub.
     *
     * @return array{avisos: int, atrasos: int, bloqueios: int}
     */
    public function processarPrazosCobrancas(): array
    {
        $stats = ['avisos' => 0, 'atrasos' => 0, 'bloqueios' => 0];

        $cobrancas = ControlCobranca::query()
            ->with('escola')
            ->whereIn('status', [ControlCobranca::STATUS_ABERTA, ControlCobranca::STATUS_VENCIDA])
            ->whereHas('escola', fn ($q) => $q->where('activo', true)
                ->whereNotIn('status', [ControlEscola::STATUS_BLOQUEADO]))
            ->get();

        foreach ($cobrancas as $cobranca) {
            $partial = $this->aplicarPrazosCobranca($cobranca);
            foreach ($stats as $chave => $valor) {
                $stats[$chave] += $partial[$chave];
            }
        }

        return $stats;
    }

    /**
     * Aplica prazos vencidos das cobranças abertas de uma escola.
     *
     * @return array{avisos: int, atrasos: int, bloqueios: int}
     */
    public function aplicarPrazosCobrancaEscola(ControlEscola $escola, ?User $actor = null): array
    {
        $stats = ['avisos' => 0, 'atrasos' => 0, 'bloqueios' => 0];

        if (! $escola->activo || $escola->status === ControlEscola::STATUS_BLOQUEADO) {
            return $stats;
        }

        $cobrancas = $escola->cobrancas()
            ->whereIn('status', [ControlCobranca::STATUS_ABERTA, ControlCobranca::STATUS_VENCIDA])
            ->get();

        foreach ($cobrancas as $cobranca) {
            $cobranca->setRelation('escola', $escola);
            $partial = $this->aplicarPrazosCobranca($cobranca, $actor);
            foreach ($stats as $chave => $valor) {
                $stats[$chave] += $partial[$chave];
            }

            $escola->refresh();
            if ($escola->status === ControlEscola::STATUS_BLOQUEADO) {
                break;
            }
        }

        return $stats;
    }

    /**
     * Aplica aviso → atraso → bloqueio quando os timestamps já passaram.
     *
     * @return array{avisos: int, atrasos: int, bloqueios: int}
     */
    public function aplicarPrazosCobranca(
        ControlCobranca $cobranca,
        ?User $actor = null,
        bool $imediato = false,
    ): array {
        $stats = ['avisos' => 0, 'atrasos' => 0, 'bloqueios' => 0];
        $escola = $cobranca->escola;

        if (! $escola || ! $escola->activo || $escola->status === ControlEscola::STATUS_BLOQUEADO) {
            return $stats;
        }

        if (! in_array($cobranca->status, [ControlCobranca::STATUS_ABERTA, ControlCobranca::STATUS_VENCIDA], true)) {
            return $stats;
        }

        $agora = now();

        // 1) Aviso / em cobrança
        if ($cobranca->aviso_em && $cobranca->aviso_em->lte($agora)
            && $escola->status === ControlEscola::STATUS_ACTIVO) {
            $motivo = $imediato && $actor
                ? 'Cobrança '.$cobranca->referencia.' criada'
                : 'Aviso automático da cobrança '.$cobranca->referencia;

            $this->setStatus(
                $escola,
                ControlEscola::STATUS_EM_COBRANCA,
                $motivo,
                $actor
            );
            $stats['avisos']++;
            $escola->refresh();
        }

        // 2) Atraso
        if ($cobranca->atraso_em && $cobranca->atraso_em->lte($agora)
            && in_array($escola->status, [ControlEscola::STATUS_ACTIVO, ControlEscola::STATUS_EM_COBRANCA], true)) {
            $this->setStatus(
                $escola,
                ControlEscola::STATUS_EM_ATRASO,
                'Atraso automático da cobrança '.$cobranca->referencia,
                $actor
            );
            $stats['atrasos']++;
            $escola->refresh();
        }

        // 3) Bloqueio
        if ($cobranca->bloqueio_em && $cobranca->bloqueio_em->lte($agora)
            && $escola->status !== ControlEscola::STATUS_BLOQUEADO) {
            $this->setStatus(
                $escola,
                ControlEscola::STATUS_BLOQUEADO,
                'Bloqueio automático por falta de pagamento ('.$cobranca->referencia.')',
                $actor
            );
            $stats['bloqueios']++;
        }

        return $stats;
    }

    public function marcarCobrancaPaga(
        ControlCobranca $cobranca,
        array $data = [],
        ?User $actor = null,
    ): ControlCobranca {
        if ($cobranca->status === ControlCobranca::STATUS_PAGA) {
            throw ValidationException::withMessages([
                'cobranca' => 'Esta cobrança já está marcada como paga.',
            ]);
        }

        if ($cobranca->status === ControlCobranca::STATUS_CANCELADA) {
            throw ValidationException::withMessages([
                'cobranca' => 'Não é possível pagar uma cobrança cancelada.',
            ]);
        }

        $cobranca->fill([
            'status' => ControlCobranca::STATUS_PAGA,
            'pago_em' => now(),
            'pago_por' => $actor?->id,
            'metodo_pagamento' => $data['metodo_pagamento'] ?? null,
            'referencia_pagamento' => $data['referencia_pagamento'] ?? null,
            'notas' => $data['notas'] ?? $cobranca->notas,
        ]);
        $cobranca->save();

        $escola = $cobranca->escola;
        $this->setStatus(
            $escola,
            ControlEscola::STATUS_ACTIVO,
            'Pagamento da cobrança '.$cobranca->referencia.' confirmado',
            $actor
        );

        $this->logEvent($escola, 'cobranca_paga', ControlEscola::STATUS_EM_COBRANCA, ControlEscola::STATUS_ACTIVO, null, $actor, [
            'referencia' => $cobranca->referencia,
            'valor_total' => (float) $cobranca->valor_total,
            'metodo' => $data['metodo_pagamento'] ?? null,
        ]);

        return $cobranca->fresh();
    }

    public function cancelarCobranca(
        ControlCobranca $cobranca,
        ?string $motivo = null,
        ?User $actor = null,
    ): ControlCobranca {
        if ($cobranca->status === ControlCobranca::STATUS_PAGA) {
            throw ValidationException::withMessages([
                'cobranca' => 'Não é possível cancelar uma cobrança já paga.',
            ]);
        }

        $cobranca->update([
            'status' => ControlCobranca::STATUS_CANCELADA,
            'notas' => trim(($cobranca->notas ? $cobranca->notas."\n" : '').($motivo ?: 'Cancelada')),
        ]);

        $escola = $cobranca->escola;
        if (in_array($escola->status, [ControlEscola::STATUS_EM_COBRANCA, ControlEscola::STATUS_EM_ATRASO], true)
            && ! $escola->cobrancaAberta()) {
            $this->setStatus($escola, ControlEscola::STATUS_ACTIVO, 'Cobrança cancelada', $actor);
        }

        $this->logEvent($escola, 'cobranca_cancelada', $escola->status, $escola->status, $motivo, $actor, [
            'referencia' => $cobranca->referencia,
        ]);

        return $cobranca->fresh();
    }

    public function setStatus(
        ControlEscola $escola,
        string $status,
        ?string $motivo = null,
        ?User $actor = null,
    ): ControlEscola {
        $allowed = array_keys(ControlEscola::statuses());
        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'Estado inválido.']);
        }

        $anterior = $escola->status;
        $accao = match ($status) {
            ControlEscola::STATUS_BLOQUEADO => 'bloquear',
            ControlEscola::STATUS_ACTIVO => $anterior === ControlEscola::STATUS_BLOQUEADO ? 'desbloquear' : 'activar',
            ControlEscola::STATUS_EM_COBRANCA => 'em_cobranca',
            ControlEscola::STATUS_EM_ATRASO => 'em_atraso',
            default => 'actualizar',
        };

        $escola->status = $status;
        $escola->bloqueio_motivo = $status === ControlEscola::STATUS_BLOQUEADO ? $motivo : null;
        $escola->bloqueado_por = $status === ControlEscola::STATUS_BLOQUEADO ? $actor?->id : null;
        $escola->bloqueado_em = $status === ControlEscola::STATUS_BLOQUEADO ? now() : null;
        $escola->save();

        // Em atraso: marcar cobrança aberta como vencida
        if ($status === ControlEscola::STATUS_EM_ATRASO) {
            $escola->cobrancas()
                ->where('status', ControlCobranca::STATUS_ABERTA)
                ->update(['status' => ControlCobranca::STATUS_VENCIDA]);
        }

        $this->logEvent($escola, $accao, $anterior, $status, $motivo, $actor);

        return $escola->fresh();
    }

    public function createEscola(array $data, string $plainSecret, ?User $actor = null): ControlEscola
    {
        $escola = ControlEscola::query()->create([
            'installation_id' => $data['installation_id'] ?? null,
            'nome' => $data['nome'],
            'slug' => $data['slug'] ?? null,
            'secret_hash' => ControlEscola::hashSecret($plainSecret),
            'secret_encrypted' => $plainSecret,
            'app_url' => $data['app_url'] ?? null,
            'contacto_email' => $data['contacto_email'] ?? null,
            'contacto_telefone' => $data['contacto_telefone'] ?? null,
            'preco_anual_aluno' => $data['preco_anual_aluno'] ?? config('control.preco_anual_aluno_default', 0),
            'status' => ControlEscola::STATUS_ACTIVO,
            'notas_internas' => $data['notas_internas'] ?? null,
            'activo' => true,
        ]);

        $this->logEvent($escola, 'registo', null, $escola->status, 'Escola registada no hub', $actor, [
            'installation_id' => $escola->installation_id,
        ]);

        return $escola;
    }

    public function rotateSecret(ControlEscola $escola, string $plainSecret, ?User $actor = null): void
    {
        $escola->storePlainSecret($plainSecret);
        $this->logEvent($escola, 'rodar_secret', $escola->status, $escola->status, 'Secret regenerado', $actor);
    }

    /**
     * Marca pedido de sync e tenta contactar a escola para sincronizar já.
     *
     * @return array{ok: bool, message: string, pushed: bool, status: ?int, body: ?string}
     */
    public function pedirSyncEscola(ControlEscola $escola, ?User $actor = null): array
    {
        $escola->update(['sync_requested_at' => now()]);
        $this->logEvent($escola, 'sync_pedido', $escola->status, $escola->status, 'Sync pedido pelo hub', $actor);

        $push = $this->pushSyncToSchool($escola);

        if ($push['ok']) {
            return [
                'ok' => true,
                'pushed' => true,
                'message' => 'Sync pedido e executado na escola. Actualiza a página em alguns segundos.',
                'status' => $push['status'],
                'body' => $push['body'],
            ];
        }

        return [
            'ok' => true,
            'pushed' => false,
            'message' => 'Pedido registado. A escola sincroniza no próximo check de licença (~1 min) ou no cron. '
                .($push['message'] ? 'Push directo falhou: '.$push['message'] : 'URL da escola em falta ou secret antigo (regenera o secret).'),
            'status' => $push['status'],
            'body' => $push['body'],
        ];
    }

    /**
     * @return array{ok: bool, message: ?string, status: ?int, body: ?string}
     */
    public function pushSyncToSchool(ControlEscola $escola): array
    {
        $appUrl = rtrim((string) $escola->app_url, '/');
        $secret = $escola->plainSecret();

        if ($appUrl === '' || ! $secret) {
            return [
                'ok' => false,
                'message' => 'URL da escola ou secret cifrado em falta (regista de novo / regenera secret).',
                'status' => null,
                'body' => null,
            ];
        }

        $installationId = (string) $escola->installation_id;
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $installationId.'|'.$timestamp, $secret);
        $url = $appUrl.'/'.trim((string) config('control.api_prefix', 'api/control/v1'), '/').'/run-sync';

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(60)
                ->acceptJson()
                ->withToken($secret)
                ->withHeaders([
                    'X-Facilita-Installation' => $installationId,
                    'X-Facilita-Timestamp' => $timestamp,
                    'X-Facilita-Signature' => $signature,
                ])
                ->post($url, ['requested_at' => now()->toIso8601String()]);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'message' => 'HTTP '.$response->status(),
                    'status' => $response->status(),
                    'body' => \Illuminate\Support\Str::limit($response->body(), 300),
                ];
            }

            return [
                'ok' => true,
                'message' => null,
                'status' => $response->status(),
                'body' => \Illuminate\Support\Str::limit($response->body(), 300),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'status' => null,
                'body' => null,
            ];
        }
    }

    private function logEvent(
        ControlEscola $escola,
        string $accao,
        ?string $anterior,
        ?string $novo,
        ?string $motivo = null,
        ?User $actor = null,
        ?array $meta = null,
    ): void {
        // Evitar flood de heartbeats no log
        if ($accao === 'heartbeat') {
            return;
        }

        ControlLicenseEvent::query()->create([
            'control_escola_id' => $escola->id,
            'accao' => $accao,
            'status_anterior' => $anterior,
            'status_novo' => $novo,
            'motivo' => $motivo,
            'user_id' => $actor?->id,
            'meta' => $meta,
        ]);
    }
}
