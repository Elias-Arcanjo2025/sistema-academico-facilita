<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ForcePasswordChangeController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.force-password-change', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $processo = $user->aluno?->numero_processo;

        $validated = $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::defaults(),
                function (string $attribute, mixed $value, \Closure $fail) use ($processo) {
                    if ($processo && (string) $value === (string) $processo) {
                        $fail('A nova palavra-passe não pode ser igual ao número de processo.');
                    }
                },
            ],
        ]);

        $user->update([
            'password' => $validated['password'],
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        activity()
            ->causedBy($user)
            ->log('Palavra-passe alterada no primeiro acesso');

        $redirect = $user->rotaInicio();

        return redirect($redirect)
            ->with('success', 'Palavra-passe alterada com sucesso. Bem-vindo ao Facilita!');
    }
}
