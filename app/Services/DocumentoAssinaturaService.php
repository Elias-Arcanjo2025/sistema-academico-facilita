<?php

namespace App\Services;

use App\Models\DocumentoAssinatura;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DocumentoAssinaturaService
{
    /**
     * @return array<string, string>
     */
    public function cargosPermitidos(User $user): array
    {
        $todos = DocumentoAssinatura::cargos();
        $permitidos = [];

        foreach ($todos as $cargo => $rotulo) {
            if ($user->hasRole($cargo) || ($cargo === 'secretaria' && $user->temAcessoSecretariaAlunos())) {
                $permitidos[$cargo] = $rotulo;
            }
        }

        if ($user->hasRole('admin_full')) {
            return $todos;
        }

        return $permitidos;
    }

    public function podeAssinar(User $user, string $cargo): bool
    {
        return array_key_exists($cargo, $this->cargosPermitidos($user));
    }

    public function assinar(
        User $user,
        string $documentoTipo,
        string $documentoChave,
        string $hashDocumento,
        string $cargo,
        ?string $ip = null,
        ?string $userAgent = null,
    ): DocumentoAssinatura {
        if (! $this->podeAssinar($user, $cargo)) {
            abort(403, 'Não pode assinar com este cargo.');
        }

        if (strlen($hashDocumento) < 32) {
            throw ValidationException::withMessages([
                'hash' => 'Hash do documento inválido.',
            ]);
        }

        return DocumentoAssinatura::updateOrCreate(
            [
                'documento_tipo' => $documentoTipo,
                'documento_chave' => $documentoChave,
                'user_id' => $user->id,
                'cargo' => $cargo,
            ],
            [
                'hash_documento' => $hashDocumento,
                'ip' => $ip,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 500) : null,
                'assinado_em' => now(),
            ]
        );
    }

    /**
     * @return Collection<int, DocumentoAssinatura>
     */
    public function listar(string $documentoTipo, string $documentoChave): Collection
    {
        return DocumentoAssinatura::query()
            ->with('user')
            ->where('documento_tipo', $documentoTipo)
            ->where('documento_chave', $documentoChave)
            ->orderBy('assinado_em')
            ->get();
    }

    public function hashBoletim(int $alunoId, ?string $anoLectivo, ?int $trimestre): string
    {
        return hash('sha256', implode('|', [
            'boletim',
            $alunoId,
            $anoLectivo ?? '',
            $trimestre ?? 'anual',
            now()->toDateString(),
        ]));
    }

    public function hashDocumentoAcademico(string $tipo, int $alunoId, ?string $anoLectivo): string
    {
        return hash('sha256', implode('|', [
            'doc_academico',
            $tipo,
            $alunoId,
            $anoLectivo ?? '',
            now()->toDateString(),
        ]));
    }
}
