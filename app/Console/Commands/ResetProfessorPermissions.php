<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class ResetProfessorPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:reset-professor {--user-id= : Reset para utilizador específico}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reseta permissões do role professor para o padrão correto';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando reset de permissões do role Professor...');
        $this->newLine();

        // 1. Garantir que permissões existam
        $this->info('[1/5] Verificando permissões...');
        $permissions = ['notas.ver', 'notas.lancar', 'notas.editar', 'mini-pautas.ver'];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
            $this->line("  ✓ Permissão criada/verificada: {$perm}");
        }
        $this->newLine();

        // 2. Criar/actualizar role professor
        $this->info('[2/5] Sincronizando role professor...');
        $professor = Role::firstOrCreate(['name' => 'professor']);
        $professor->syncPermissions($permissions);
        $this->line("  ✓ Role 'professor' sincronizado com permissões corretas");
        $this->newLine();

        // 3. Se --user-id fornecido, actualizar utilizador específico
        if ($userId = $this->option('user-id')) {
            $this->info('[3/5] Actualizando utilizador específico...');
            $user = User::find($userId);

            if (!$user) {
                $this->error("  ✗ Utilizador ID {$userId} não encontrado!");
                return 1;
            }

            // Remover roles antigos e atribuir role professor
            $user->syncRoles(['professor']);

            $this->line("  ✓ Utilizador #{$userId} ({$user->email}) actualizado para role 'professor'");
            $this->newLine();
        } else {
            $this->info('[3/5] Actualizando todos os utilizadores com role professor...');
            $usuarios = User::role('professor')->get();

            if ($usuarios->isEmpty()) {
                $this->warn("  ⚠ Nenhum utilizador encontrado com role 'professor'");
            } else {
                $this->line("  Encontrados {$usuarios->count()} utilizadores");
                foreach ($usuarios as $user) {
                    $user->syncPermissions([]); // Limpar permissões diretas (usar apenas as do role)
                    $this->line("    ✓ {$user->email}");
                }
            }
            $this->newLine();
        }

        // 4. Limpar cache de permissões
        $this->info('[4/5] Limpando cache de permissões...');
        \Artisan::call('permission:cache-reset');
        $this->line('  ✓ Cache de permissões limpo');
        $this->newLine();

        // 5. Verificar resultado
        $this->info('[5/5] Verificando resultado...');
        $professorRole = Role::where('name', 'professor')->with('permissions')->first();
        $permsAtual = $professorRole->permissions->pluck('name')->toArray();

        if (count(array_diff($permissions, $permsAtual)) === 0 && count($permsAtual) === 4) {
            $this->line('  ✓ Permissões corretas atribuídas');
        } else {
            $this->error('  ✗ Erro: Permissões não correspondem ao esperado');
            $this->line('    Esperado: ' . implode(', ', $permissions));
            $this->line('    Actual: ' . implode(', ', $permsAtual));
        }
        $this->newLine();

        // Resumo final
        $this->info('========================================');
        $this->info('Reset concluído com sucesso!');
        $this->info('========================================');
        $this->line('');
        $this->line('Permissões do Professor:');
        foreach ($permissions as $perm) {
            $this->line("  • {$perm}");
        }
        $this->line('');
        $this->info('Próximos passos:');
        $this->line('1. Fazer logout do utilizador professor');
        $this->line('2. Limpar cookies do navegador');
        $this->line('3. Fazer login novamente');
        $this->line('4. Verificar se menus estão corretos');

        return 0;
    }
}
