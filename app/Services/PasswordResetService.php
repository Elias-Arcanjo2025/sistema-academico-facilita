<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\User;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function __construct(
        protected AlunoAuthService $alunoAuth,
    ) {}

    /**
     * Repõe palavra-passe do aluno com senha temporária aleatória.
     * Devolve a senha em claro (mostrar uma vez ao staff).
     */
    public function reporAluno(Aluno $aluno): string
    {
        $user = $aluno->user;

        if (! $user) {
            $user = $this->alunoAuth->criarOuActualizarConta($aluno);
            $plain = $user->getAttribute('password_plain_once');
            if (is_string($plain) && $plain !== '') {
                return $plain;
            }
        }

        $temporaria = $this->alunoAuth->gerarPalavraPasseTemporaria();

        $user->update([
            'password' => $temporaria,
            'must_change_password' => true,
            'password_changed_at' => null,
            'estado' => $aluno->activo ? 'activo' : 'inactivo',
        ]);

        activity()
            ->performedOn($aluno)
            ->causedBy(auth()->user())
            ->log('Palavra-passe do aluno reposta (temporária aleatória)');

        return $temporaria;
    }

    /**
     * Repõe palavra-passe de utilizador staff (professor, etc.).
     */
    public function reporStaff(User $utilizador): string
    {
        if ($utilizador->hasRole('aluno')) {
            throw new \InvalidArgumentException('Use o reset de aluno para contas estudantis.');
        }

        $temporaria = Str::password(10, letters: true, numbers: true, symbols: false);

        $utilizador->update([
            'password' => $temporaria,
            'must_change_password' => true,
            'password_changed_at' => null,
        ]);

        activity()
            ->performedOn($utilizador)
            ->causedBy(auth()->user())
            ->log('Palavra-passe de utilizador reposta (temporária)');

        return $temporaria;
    }
}
