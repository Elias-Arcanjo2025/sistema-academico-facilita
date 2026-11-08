<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** @var list<string> */
    private array $permissoes = [
        'pedagogico.ver',
        'pedagogico.planificacao.criar',
        'pedagogico.planificacao.editar',
        'pedagogico.ia.gerar',
        'pedagogico.correcao.usar',
        'pedagogico.dashboard.ver',
        'pedagogico.config',
    ];

    /** @var array<string, list<string>> */
    private array $porCargo = [
        'professor' => [
            'pedagogico.ver',
            'pedagogico.planificacao.criar',
            'pedagogico.planificacao.editar',
            'pedagogico.ia.gerar',
            'pedagogico.correcao.usar',
        ],
        'director_turma' => [
            'pedagogico.ver',
            'pedagogico.planificacao.criar',
            'pedagogico.planificacao.editar',
            'pedagogico.ia.gerar',
            'pedagogico.correcao.usar',
            'pedagogico.dashboard.ver',
        ],
        'coordenador_curso' => [
            'pedagogico.ver',
            'pedagogico.planificacao.criar',
            'pedagogico.planificacao.editar',
            'pedagogico.ia.gerar',
            'pedagogico.correcao.usar',
            'pedagogico.dashboard.ver',
        ],
        'subdirector_pedagogico' => [
            'pedagogico.ver',
            'pedagogico.dashboard.ver',
        ],
        'directora_geral' => [
            'pedagogico.ver',
            'pedagogico.planificacao.criar',
            'pedagogico.planificacao.editar',
            'pedagogico.ia.gerar',
            'pedagogico.correcao.usar',
            'pedagogico.dashboard.ver',
        ],
        'gestor_academico' => [
            'pedagogico.ver',
            'pedagogico.planificacao.criar',
            'pedagogico.planificacao.editar',
            'pedagogico.ia.gerar',
            'pedagogico.correcao.usar',
            'pedagogico.dashboard.ver',
        ],
        'admin_full' => [
            'pedagogico.ver',
            'pedagogico.planificacao.criar',
            'pedagogico.planificacao.editar',
            'pedagogico.ia.gerar',
            'pedagogico.correcao.usar',
            'pedagogico.dashboard.ver',
            'pedagogico.config',
        ],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissoes as $nome) {
            Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);
        }

        foreach ($this->porCargo as $cargo => $permissoes) {
            $role = Role::query()->where('name', $cargo)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($permissoes);
            }
        }
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->porCargo as $cargo => $permissoes) {
            $role = Role::query()->where('name', $cargo)->where('guard_name', 'web')->first();
            if ($role) {
                $role->revokePermissionTo($permissoes);
            }
        }

        Permission::query()
            ->whereIn('name', $this->permissoes)
            ->where('guard_name', 'web')
            ->delete();
    }
};
