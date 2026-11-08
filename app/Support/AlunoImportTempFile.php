<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Gestão segura de ficheiros temporários na importação de alunos.
 * O path nunca é exposto ao cliente — apenas um token opaco na sessão.
 */
class AlunoImportTempFile
{
    public const SESSION_PREFIX = 'alunos_import.';

    public static function tempDir(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'facilita-imports';

        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        return $dir;
    }

    public static function storeFromUpload(UploadedFile $file, int $userId): string
    {
        $extension = $file->getClientOriginalExtension() ?: 'xlsx';
        $filename = sprintf('import-temp-%d-%d-%s.%s', $userId, now()->timestamp, Str::random(8), $extension);
        $dest = self::tempDir().DIRECTORY_SEPARATOR.$filename;

        if (! copy($file->getRealPath(), $dest)) {
            throw new \RuntimeException('Falha ao guardar ficheiro temporário.');
        }

        return $dest;
    }

    /**
     * Regista o ficheiro na sessão e devolve token opaco para o formulário.
     */
    public static function registerInSession(Request $request, string $path, int $userId, ?int $turmaId): string
    {
        $validated = self::validateOwnedPath($path, $userId);
        $token = Str::random(64);

        $request->session()->put(self::SESSION_PREFIX.$token, [
            'path' => $validated,
            'user_id' => $userId,
            'turma_id' => $turmaId,
            'expires_at' => now()->addHours(2)->timestamp,
        ]);

        return $token;
    }

    /**
     * Resolve path a partir do token de sessão (rejeita paths arbitrários do cliente).
     */
    public static function resolveFromSession(Request $request, string $token, int $userId): string
    {
        $key = self::SESSION_PREFIX.$token;
        $data = $request->session()->get($key);

        if (! is_array($data) || (int) ($data['user_id'] ?? 0) !== $userId) {
            abort(422, 'Sessão de importação inválida ou expirada.');
        }

        if (($data['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget($key);
            abort(422, 'A sessão de importação expirou. Faça o upload novamente.');
        }

        return self::validateOwnedPath((string) $data['path'], $userId);
    }

    public static function forgetSession(Request $request, string $token): void
    {
        $request->session()->forget(self::SESSION_PREFIX.$token);
    }

    public static function deleteFile(?string $path, int $userId): void
    {
        if ($path === null || $path === '') {
            return;
        }

        try {
            $validated = self::validateOwnedPath($path, $userId);
            if (is_file($validated)) {
                unlink($validated);
            }
        } catch (\Throwable) {
            // Ignorar — ficheiro já removido ou inválido
        }
    }

    public static function validateOwnedPath(string $path, int $userId): string
    {
        $tempDir = realpath(self::tempDir());
        if ($tempDir === false) {
            abort(422, 'Diretório temporário indisponível.');
        }

        $resolved = realpath($path);
        if ($resolved === false || ! str_starts_with($resolved, $tempDir.DIRECTORY_SEPARATOR)) {
            abort(422, 'Ficheiro temporário inválido.');
        }

        $basename = basename($resolved);
        if (! preg_match('/^import-temp-'.$userId.'-\d+-[a-z0-9]+\.(xlsx|xls|csv)$/i', $basename)) {
            abort(422, 'Ficheiro temporário inválido.');
        }

        return $resolved;
    }
}
