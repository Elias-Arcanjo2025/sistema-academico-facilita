<?php

namespace App\Support;

use Illuminate\Notifications\DatabaseNotification;

/**
 * Resolve o destino de uma notificação database (url + fallbacks para registos antigos).
 */
final class NotificationDestination
{
    public static function url(DatabaseNotification $notification, string $fallback, string $contexto = 'staff'): string
    {
        $data = $notification->data ?? [];

        if (! empty($data['url']) && is_string($data['url'])) {
            return self::sanitize($data['url'], $fallback);
        }

        if ($contexto === 'staff' && ! empty($data['turma_id'])) {
            return route('turmas.show', $data['turma_id']);
        }

        return match ($data['tipo'] ?? $data['tipo_evento'] ?? null) {
            'conta_aprovada', 'conta_rejeitada' => route('login'),
            'nota_publicada' => route('portal.aluno.notas'),
            'encarregado_nota_publicada' => ! empty($data['aluno_id'])
                ? route('portal.encarregado.filho', $data['aluno_id'])
                : route('portal.encarregado.dashboard'),
            'documento_pedido' => route('portal.encarregado.documentos.index'),
            'prova_agendada' => route('portal.aluno.calendario', ['tab' => 'provas']),
            'agenda_evento' => route('portal.aluno.calendario', ['tab' => 'agenda']),
            'mensagem_portal' => $contexto === 'staff'
                ? route('mensagens-encarregados.index')
                : route('portal.encarregado.mensagens.index'),
            'mensagem_interna' => route('mensagens-encarregados.index'),
            default => $fallback,
        };
    }

    private static function sanitize(string $url, string $fallback): string
    {
        if (preg_match('#^(javascript|data):#i', $url)) {
            return $fallback;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);
        $relative = $path.($query ? '?'.$query : '');
        $urlHost = parse_url($url, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        // Links internos → path relativo (funciona em facilita.test e facilita2.test)
        if (self::pareceUrlInterna($path)) {
            if ($urlHost === null || $urlHost === $appHost || self::hostsLocaisCompativeis($urlHost, $appHost)) {
                return $relative;
            }
        }

        if ($urlHost === null || $urlHost === $appHost) {
            return $url;
        }

        return $fallback;
    }

    private static function pareceUrlInterna(string $path): bool
    {
        return str_starts_with($path, '/app/')
            || str_starts_with($path, '/portal/')
            || $path === '/dashboard'
            || str_starts_with($path, '/dashboard/')
            || $path === '/login'
            || $path === '/logout';
    }

    private static function hostsLocaisCompativeis(?string $a, ?string $b): bool
    {
        $norm = static fn (?string $h): string => strtolower((string) preg_replace('/:\d+$/', '', (string) $h));
        $a = $norm($a);
        $b = $norm($b);
        if ($a === '' || $b === '') {
            return false;
        }

        $local = static fn (string $h): bool => str_ends_with($h, '.test')
            || str_ends_with($h, '.local')
            || $h === 'localhost'
            || $h === '127.0.0.1';

        return $local($a) && $local($b);
    }
}
