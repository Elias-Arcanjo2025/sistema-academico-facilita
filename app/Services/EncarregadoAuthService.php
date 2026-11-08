<?php

namespace App\Services;

use App\Models\Encarregado;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class EncarregadoAuthService
{
    public function ensureRoleExists(): void
    {
        Permission::firstOrCreate(['name' => 'portal.encarregado.ver', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'encarregado', 'guard_name' => 'web']);
        $role->givePermissionTo('portal.encarregado.ver');
    }

    public function criarOuActualizarConta(Encarregado $encarregado, ?string $password = null): User
    {
        $this->ensureRoleExists();

        $email = $encarregado->email ?: $this->emailSintetico($encarregado);
        $user = User::query()->where('encarregado_id', $encarregado->id)->first()
            ?? User::query()->where('email', $email)->first();

        $plain = $password ?: Str::password(10);

        if (! $user) {
            $user = User::query()->create([
                'name' => $encarregado->nome,
                'email' => $email,
                'password' => Hash::make($plain),
                'estado' => 'activo',
                'encarregado_id' => $encarregado->id,
                'must_change_password' => true,
                'email_verified_at' => now(),
            ]);
        } else {
            $user->fill([
                'name' => $encarregado->nome,
                'encarregado_id' => $encarregado->id,
                'estado' => 'activo',
            ]);
            if ($password) {
                $user->password = Hash::make($password);
                $user->must_change_password = true;
            }
            $user->save();
        }

        if (! $user->hasRole('encarregado')) {
            $user->assignRole('encarregado');
        }

        $user->setAttribute('password_plain_once', $plain);

        return $user;
    }

    private function emailSintetico(Encarregado $encarregado): string
    {
        $base = 'enc.'.$encarregado->id.'@facilita.local';
        if ($encarregado->telefoneNormalizado()) {
            $base = 'enc.'.$encarregado->telefoneNormalizado().'@facilita.local';
        }

        return $base;
    }
}
