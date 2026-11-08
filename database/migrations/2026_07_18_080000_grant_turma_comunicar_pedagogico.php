<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'turma.comunicar', 'guard_name' => 'web']);

        foreach ([
            'professor',
            'coordenador_curso',
            'coordenador_disciplina',
            'subdiretor_pedagogico',
            'director_turma',
        ] as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            if (! $role->hasPermissionTo('turma.comunicar')) {
                $role->givePermissionTo('turma.comunicar');
            }
        }
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['professor', 'coordenador_curso', 'coordenador_disciplina', 'subdiretor_pedagogico'] as $roleName) {
            $role = Role::findByName($roleName, 'web');
            if ($role && $role->hasPermissionTo('turma.comunicar')) {
                $role->revokePermissionTo('turma.comunicar');
            }
        }
    }
};
