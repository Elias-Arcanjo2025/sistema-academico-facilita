<?php

namespace App\Services\WhatsApp;

use App\Models\Encarregado;
use App\Models\WhatsappMensagem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function enabled(): bool
    {
        return (bool) config('whatsapp.enabled', false);
    }

    public function isMeta(): bool
    {
        return $this->enabled() && config('whatsapp.provider') === 'meta';
    }

    /**
     * @return array{ok:bool, provider:string, missing:list<string>, message:string, phone?:mixed}
     */
    public function diagnostico(): array
    {
        $provider = (string) config('whatsapp.provider', 'log');
        $missing = [];

        if (! $this->enabled()) {
            return [
                'ok' => false,
                'provider' => $provider,
                'missing' => ['WHATSAPP_ENABLED'],
                'message' => 'WhatsApp desligado (WHATSAPP_ENABLED=false).',
            ];
        }

        if ($provider === 'log') {
            return [
                'ok' => true,
                'provider' => 'log',
                'missing' => [],
                'message' => 'Modo log activo — mensagens não saem para o telemóvel.',
            ];
        }

        if (! config('whatsapp.phone_number_id')) {
            $missing[] = 'WHATSAPP_PHONE_NUMBER_ID';
        }
        if (! config('whatsapp.access_token')) {
            $missing[] = 'WHATSAPP_ACCESS_TOKEN';
        }

        if ($missing !== []) {
            return [
                'ok' => false,
                'provider' => $provider,
                'missing' => $missing,
                'message' => 'Faltam credenciais Meta: '.implode(', ', $missing),
            ];
        }

        return [
            'ok' => true,
            'provider' => 'meta',
            'missing' => [],
            'message' => 'Credenciais Meta presentes. Pode enviar mensagens reais.',
        ];
    }

    /**
     * Testa a ligação à Graph API (não envia mensagem).
     *
     * @return array{ok:bool, message:string, data?:array<string, mixed>}
     */
    public function testarLigacao(): array
    {
        $diag = $this->diagnostico();
        if (! $diag['ok'] || $diag['provider'] !== 'meta') {
            return ['ok' => false, 'message' => $diag['message']];
        }

        try {
            $base = rtrim((string) config('whatsapp.api_base'), '/');
            $version = trim((string) config('whatsapp.api_version'), '/');
            $phoneId = (string) config('whatsapp.phone_number_id');

            $response = Http::withToken((string) config('whatsapp.access_token'))
                ->timeout(20)
                ->get("{$base}/{$version}/{$phoneId}", [
                    'fields' => 'display_phone_number,verified_name,quality_rating',
                ]);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'message' => 'Meta rejeitou as credenciais: '.$this->resumoErroMeta($response->json(), $response->body()),
                    'data' => $response->json() ?? [],
                ];
            }

            $data = $response->json() ?? [];

            return [
                'ok' => true,
                'message' => 'Ligação OK — '.($data['verified_name'] ?? 'número').' ('.($data['display_phone_number'] ?? $phoneId).')',
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Falha de rede ao contactar a Meta: '.$e->getMessage()];
        }
    }

    public function sendText(string $telefone, string $corpo, ?Encarregado $encarregado = null): WhatsappMensagem
    {
        $to = $this->normalizePhone($telefone);
        $msg = WhatsappMensagem::query()->create([
            'direction' => 'out',
            'telefone' => $to,
            'encarregado_id' => $encarregado?->id,
            'tipo' => 'text',
            'corpo' => $corpo,
            'status' => 'queued',
        ]);

        if (! $this->enabled() || config('whatsapp.provider') === 'log') {
            $msg->update(['status' => 'sent', 'provider_message_id' => 'log-'.uniqid()]);
            Log::info('WhatsApp [log]', ['to' => $to, 'body' => $corpo]);

            return $msg->fresh();
        }

        $diag = $this->diagnostico();
        if (! $diag['ok']) {
            $msg->update(['status' => 'failed', 'erro' => $diag['message']]);

            return $msg->fresh();
        }

        try {
            $response = Http::withToken((string) config('whatsapp.access_token'))
                ->timeout(30)
                ->post($this->messagesUrl(), [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => ['body' => $corpo],
                ]);

            if ($response->successful()) {
                $msg->update([
                    'status' => 'sent',
                    'provider_message_id' => data_get($response->json(), 'messages.0.id'),
                    'payload' => $response->json(),
                ]);
            } else {
                $msg->update([
                    'status' => 'failed',
                    'erro' => $this->resumoErroMeta($response->json(), $response->body()),
                    'payload' => $response->json(),
                ]);
            }
        } catch (\Throwable $e) {
            $msg->update(['status' => 'failed', 'erro' => $e->getMessage()]);
        }

        return $msg->fresh();
    }

    public function sendTemplate(
        string $telefone,
        string $templateKey,
        array $params = [],
        ?Encarregado $encarregado = null,
    ): WhatsappMensagem {
        $templateName = (string) config("whatsapp.templates.{$templateKey}", '');
        $to = $this->normalizePhone($telefone);

        $msg = WhatsappMensagem::query()->create([
            'direction' => 'out',
            'telefone' => $to,
            'encarregado_id' => $encarregado?->id,
            'tipo' => 'template',
            'template' => $templateKey,
            'corpo' => $templateName ?: $templateKey,
            'status' => 'queued',
            'payload' => ['params' => $params],
        ]);

        $textoFallback = $this->renderFallback($templateKey, $params);

        // Desligado ou modo log — só grava
        if (! $this->enabled() || config('whatsapp.provider') === 'log') {
            $msg->update(['corpo' => $textoFallback, 'status' => 'sent', 'provider_message_id' => 'log-'.uniqid()]);
            Log::info('WhatsApp template [log]', ['to' => $to, 'template' => $templateKey, 'body' => $textoFallback]);

            return $msg->fresh();
        }

        // Meta sem nome de template aprovado → envia o texto de fallback (janela 24h)
        if ($templateName === '') {
            $msg->delete();

            return $this->sendText($telefone, $textoFallback, $encarregado);
        }

        $diag = $this->diagnostico();
        if (! $diag['ok']) {
            $msg->update(['status' => 'failed', 'erro' => $diag['message'], 'corpo' => $this->renderFallback($templateKey, $params)]);

            return $msg->fresh();
        }

        $components = [];
        if ($params !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => (string) $p], array_values($params)),
            ];
        }

        try {
            $response = Http::withToken((string) config('whatsapp.access_token'))
                ->timeout(30)
                ->post($this->messagesUrl(), [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => ['code' => (string) config('whatsapp.template_language', 'pt_PT')],
                        'components' => $components,
                    ],
                ]);

            $msg->update([
                'status' => $response->successful() ? 'sent' : 'failed',
                'provider_message_id' => data_get($response->json(), 'messages.0.id'),
                'payload' => $response->json(),
                'corpo' => $this->renderFallback($templateKey, $params),
                'erro' => $response->successful() ? null : $this->resumoErroMeta($response->json(), $response->body()),
            ]);
        } catch (\Throwable $e) {
            $msg->update(['status' => 'failed', 'erro' => $e->getMessage()]);
        }

        return $msg->fresh();
    }

    public function notifyEncarregado(Encarregado $encarregado, string $templateKey, array $params = []): ?WhatsappMensagem
    {
        if (! $encarregado->whatsapp_opt_in || ! $encarregado->telefone) {
            return null;
        }

        return $this->sendTemplate($encarregado->telefone, $templateKey, $params, $encarregado);
    }

    public function normalizePhone(string $telefone): string
    {
        $digits = preg_replace('/\D+/', '', $telefone) ?: '';
        if (str_starts_with($digits, '0')) {
            $digits = '244'.substr($digits, 1);
        }
        if (! str_starts_with($digits, '244') && strlen($digits) === 9) {
            $digits = '244'.$digits;
        }

        return $digits;
    }

    private function messagesUrl(): string
    {
        $base = rtrim((string) config('whatsapp.api_base'), '/');
        $version = trim((string) config('whatsapp.api_version'), '/');
        $phoneId = (string) config('whatsapp.phone_number_id');

        return "{$base}/{$version}/{$phoneId}/messages";
    }

    private function renderFallback(string $key, array $params): string
    {
        $nome = $params['aluno'] ?? $params[0] ?? 'o seu educando';

        $disciplina = $params['disciplina'] ?? $params[1] ?? 'aula';
        $data = $params['data'] ?? $params[2] ?? now()->format('d/m/Y');

        return match ($key) {
            'mac_em_falta' => "Facilita: MAC em falta para {$nome}. Acompanhe no portal do encarregado.",
            'prova_amanha' => "Facilita: {$nome} tem prova amanhã. Consulte o calendário no portal.",
            'boletim_disponivel' => sprintf(
                'Facilita: Boletim de %s disponível (%s). Pode consultar e assinar no portal.',
                $nome,
                $params['periodo'] ?? $params[1] ?? 'trimestre actual'
            ),
            'cobranca_facilita' => 'Facilita: Aviso de cobrança/licença. Contacte a direcção da escola.',
            'falta_registada' => "Facilita: Falta registada para {$nome} em {$disciplina} ({$data}). Consulte a assiduidade no portal.",
            'ocorrencia_escolar' => sprintf(
                'Facilita: %s — %s (%s). %s Detalhe no portal do encarregado.',
                $params['tipo'] ?? 'Ocorrência',
                $nome,
                $params['severidade'] ?? '—',
                ($params['resumo'] ?? '') !== '' ? ($params['resumo'].' ') : ''
            ),
            'resumo_semanal' => sprintf(
                'Facilita — Resumo da semana (%s) sobre %s: %s. Abra o portal do encarregado para o detalhe.',
                $params['periodo'] ?? $params[1] ?? 'esta semana',
                $params['aluno'] ?? $params[0] ?? 'os seus educandos',
                $params['resumo'] ?? $params[2] ?? 'consulte o portal'
            ),
            default => 'Facilita: '.$key.' — '.implode(' ', $params),
        };
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function resumoErroMeta(?array $json, string $body): string
    {
        $msg = data_get($json, 'error.message')
            ?? data_get($json, 'error.error_user_msg')
            ?? trim($body);

        $code = data_get($json, 'error.code');
        $sub = data_get($json, 'error.error_subcode');

        if ($code) {
            $msg = "[#{$code}".($sub ? "/{$sub}" : '').'] '.$msg;
        }

        // Dicas comuns da Cloud API
        if (str_contains(mb_strtolower($msg), 'not in allowed list')
            || str_contains(mb_strtolower($msg), 'recipient phone number not in')) {
            $msg .= ' — Em modo teste, adicione este número em Meta → WhatsApp → API Setup → To.';
        }
        if (str_contains(mb_strtolower($msg), '24 hour')
            || (int) $code === 131047) {
            $msg .= ' — Fora da janela de 24h: o destinatário tem de lhe escrever primeiro, ou use um template aprovado.';
        }

        return \Illuminate\Support\Str::limit($msg, 500);
    }
}
