<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = trim($this->string('email'));
        $user = $this->resolveUser($login);

        if (! $user || ! Hash::check($this->password, $user->password)) {
            RateLimiter::hit($this->throttleKey());

            activity()
                ->withProperties(['login' => $login, 'ip' => $this->ip()])
                ->log('Tentativa de login falhada');

            throw ValidationException::withMessages([
                'email' => 'As credenciais fornecidas estão incorrectas.',
            ]);
        }

        Auth::login($user, $this->boolean('remember'));

        if ($user->estado === 'pendente') {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'A sua conta ainda não foi aprovada. Por favor, aguarde a aprovação por um administrador.',
            ]);
        }

        if ($user->estado === 'rejeitado') {
            Auth::logout();
            $motivo = $user->motivo_rejeicao ? ' Motivo: ' . $user->motivo_rejeicao : '';
            throw ValidationException::withMessages([
                'email' => 'A sua conta foi rejeitada.' . $motivo,
            ]);
        }

        if ($user->estado === 'inactivo') {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'A sua conta está inactiva. Contacte um administrador.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        activity()
            ->causedBy($user)
            ->withProperties(['ip' => $this->ip()])
            ->log('Login efectuado com sucesso');
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        activity()
            ->withProperties(['email' => $this->email, 'ip' => $this->ip()])
            ->log('Conta bloqueada por excesso de tentativas');

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => 'Demasiadas tentativas de login. Por favor, tente novamente em ' . ceil($seconds / 60) . ' minuto(s).',
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    protected function resolveUser(string $login): ?User
    {
        $user = User::where('email', $login)->first();

        if ($user) {
            return $user;
        }

        return User::whereHas('aluno', fn ($q) => $q->where('numero_processo', $login))->first();
    }
}
