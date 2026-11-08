<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissoes = ['portal.aluno.ver', 'assistente.usar'];

        foreach ($permissoes as $nome) {
            Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);
        }

        $aluno = Role::firstOrCreate(['name' => 'aluno', 'guard_name' => 'web']);
        $aluno->givePermissionTo($permissoes);
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $aluno = Role::query()->where('name', 'aluno')->where('guard_name', 'web')->first();
        if ($aluno) {
            $aluno->revokePermissionTo('assistente.usar');
        }
    }
};
