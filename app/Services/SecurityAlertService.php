<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;

/**
 * Detecção simples de padrões suspeitos para alertas de segurança.
 */
class SecurityAlertService
{
    /**
     * @return array<int, array{titulo:string, nivel:string, detalhe:string}>
     */
    public function alertasRecentes(): array
    {
        return Cache::remember('security_alertas.'.today()->format('Y-m-d'), 120, function () {
            $alertas = [];

            $falhasLogin = Activity::query()
                ->where('description', 'like', '%login falh%')
                ->where('created_at', '>=', now()->subHours(24))
                ->count();

            if ($falhasLogin >= 10) {
                $alertas[] = [
                    'titulo' => 'Tentativas de login falhadas',
                    'nivel' => 'warning',
                    'detalhe' => "{$falhasLogin} tentativas nas últimas 24h.",
                ];
            }

            $importacoes = Activity::query()
                ->where('description', 'Importação de notas via Excel')
                ->where('created_at', '>=', now()->subHours(6))
                ->count();

            if ($importacoes >= 5) {
                $alertas[] = [
                    'titulo' => 'Importações em massa',
                    'nivel' => 'info',
                    'detalhe' => "{$importacoes} importações Excel nas últimas 6h — verifique auditoria.",
                ];
            }

            $alteracoesNotas = Activity::query()
                ->where('created_at', '>=', now()->subHour())
                ->where(function ($q) {
                    $q->where('description', 'like', '%nota%')
                        ->orWhere('description', 'like', '%recuperação%')
                        ->orWhere('description', 'like', '%recurso%');
                })
                ->count();

            if ($alteracoesNotas >= 50) {
                $alertas[] = [
                    'titulo' => 'Volume elevado de alterações',
                    'nivel' => 'warning',
                    'detalhe' => "{$alteracoesNotas} eventos de notas na última hora.",
                ];
            }

            return $alertas;
        });
    }
}
