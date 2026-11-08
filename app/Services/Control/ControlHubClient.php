<?php

namespace App\Services\Control;

use App\Models\Control\ControlLicenseState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ControlHubClient
{
    public function isConfigured(): bool
    {
        return config('control.mode') === 'school'
            && filled(config('control.hub_url'))
            && filled(config('control.installation_id'))
            && filled(config('control.secret'));
    }

    public function heartbeat(array $payload): array
    {
        return $this->post('heartbeat', $payload);
    }

    public function syncAlunos(array $payload): array
    {
        return $this->post('alunos/sync', $payload);
    }

    public function licenseStatus(): array
    {
        return $this->get('license');
    }

    private function post(string $path, array $payload): array
    {
        $response = $this->request()->post($this->url($path), $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Hub respondeu HTTP '.$response->status().': '.$response->body()
            );
        }

        return $response->json() ?? [];
    }

    private function get(string $path): array
    {
        $response = $this->request()->get($this->url($path));

        if (! $response->successful()) {
            throw new RuntimeException(
                'Hub respondeu HTTP '.$response->status().': '.$response->body()
            );
        }

        return $response->json() ?? [];
    }

    private function request()
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Controlo da escola não está configurado (CONTROL_*).');
        }

        $installationId = (string) config('control.installation_id');
        $secret = (string) config('control.secret');
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $installationId.'|'.$timestamp, $secret);

        return Http::timeout(20)
            ->acceptJson()
            ->withToken($secret)
            ->withHeaders([
                'X-Facilita-Installation' => $installationId,
                'X-Facilita-Timestamp' => $timestamp,
                'X-Facilita-Signature' => $signature,
            ]);
    }

    private function url(string $path): string
    {
        $prefix = trim((string) config('control.api_prefix'), '/');

        return rtrim((string) config('control.hub_url'), '/').'/'.$prefix.'/'.ltrim($path, '/');
    }
}
