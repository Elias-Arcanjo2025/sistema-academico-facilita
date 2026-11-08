<?php

namespace App\Console\Commands;

use App\Services\AlunoAuthService;
use Illuminate\Console\Command;

class SyncAlunoAccounts extends Command
{
    protected $signature = 'alunos:sync-contas {--ano= : ID do ano lectivo}';

    protected $description = 'Cria ou actualiza contas de login dos alunos (n.º processo + senha inicial)';

    public function handle(AlunoAuthService $service): int
    {
        $anoId = $this->option('ano') ? (int) $this->option('ano') : null;

        $this->info('A sincronizar contas de alunos...');

        $resultado = $service->sincronizarTodos($anoId);

        $this->table(
            ['Criados', 'Actualizados', 'Erros'],
            [[$resultado['criados'], $resultado['actualizados'], $resultado['erros']]]
        );

        return self::SUCCESS;
    }
}
