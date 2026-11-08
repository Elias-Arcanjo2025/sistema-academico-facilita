<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\WhatsAppBotService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('whatsapp.verify_token')
            && filled(config('whatsapp.verify_token'))) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request, WhatsAppBotService $bot): Response
    {
        if (! $this->assinaturaValida($request)) {
            Log::warning('WhatsApp webhook: assinatura inválida ou ausente');

            return response('Forbidden', 403);
        }

        $payload = $request->all();
        Log::debug('WhatsApp webhook', ['keys' => array_keys($payload)]);

        $entries = data_get($payload, 'entry', []);
        foreach ($entries as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                $value = $change['value'] ?? [];
                foreach (data_get($value, 'messages', []) as $message) {
                    $from = (string) ($message['from'] ?? '');
                    $type = (string) ($message['type'] ?? 'text');
                    $texto = (string) data_get($message, 'text.body', '');
                    $mediaPath = null;

                    if ($type === 'image' && $id = data_get($message, 'image.id')) {
                        $mediaPath = $this->downloadMedia((string) $id);
                        $texto = (string) data_get($message, 'image.caption', $texto);
                    }

                    if ($from !== '') {
                        $bot->handleInbound($from, $texto ?: 'imagem', $mediaPath);
                    }
                }
            }
        }

        // Testes manuais só em ambiente local com utilizador autenticado
        if (app()->environment('local')
            && $request->user()
            && $request->filled('from')
            && $request->filled('text')) {
            $bot->handleInbound((string) $request->input('from'), (string) $request->input('text'));
        }

        return response('OK', 200);
    }

    private function assinaturaValida(Request $request): bool
    {
        $secret = (string) config('whatsapp.app_secret', '');

        // Sem secret configurado: só permite em local (dev) ou provider log
        if ($secret === '') {
            return app()->environment('local')
                || config('whatsapp.provider') === 'log'
                || ! config('whatsapp.enabled');
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');
        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $header);
    }

    private function downloadMedia(string $mediaId): ?string
    {
        if (! config('whatsapp.enabled') || config('whatsapp.provider') === 'log') {
            return null;
        }

        try {
            $meta = Http::withToken((string) config('whatsapp.access_token'))
                ->get(rtrim((string) config('whatsapp.api_base'), '/').'/'.trim((string) config('whatsapp.api_version'), '/').'/'.$mediaId)
                ->json();

            $url = $meta['url'] ?? null;
            if (! $url) {
                return null;
            }

            $bin = Http::withToken((string) config('whatsapp.access_token'))->get($url)->body();
            $path = 'whatsapp/'.now()->format('Ymd').'/'.Str::uuid().'.jpg';
            Storage::disk('local')->put($path, $bin);

            return $path;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp media download failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
