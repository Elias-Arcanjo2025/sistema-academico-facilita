<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** @var list<string> */
    private array $cargos = [
        'professor',
        'director_turma',
        'coordenador_curso',
        'subdiretor_pedagogico',
        'directora_geral',
        'gestor_academico',
        'aluno',
        'admin_full',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'assistente.usar', 'guard_name' => 'web']);

        foreach ($this->cargos as $cargo) {
            $role = Role::query()->where('name', $cargo)->where('guard_name', 'web')->first();
            if ($role && ! $role->hasPermissionTo('assistente.usar')) {
                $role->givePermissionTo('assistente.usar');
            }
        }
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->cargos as $cargo) {
            $role = Role::query()->where('name', $cargo)->where('guard_name', 'web')->first();
            if ($role && $role->hasPermissionTo('assistente.usar')) {
                $role->revokePermissionTo('assistente.usar');
            }
        }
    }
};
