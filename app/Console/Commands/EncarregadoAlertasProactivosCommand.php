<?php

namespace App\Console\Commands;

use App\Services\Portal\EncarregadoNotificacaoProactivaService;
use Illuminate\Console\Command;

class EncarregadoAlertasProactivosCommand extends Command
{
    protected $signature = 'portal:encarregado-alertas-proactivos';

    protected $description = 'Dispara alertas proactivos para encarregados (faltas, notas, score, mensagens)';

    public function handle(EncarregadoNotificacaoProactivaService $alertas): int
    {
        $this->info('A verificar alertas proactivos dos encarregados...');
        $processados = $alertas->verificarTodosOsEncarregados();
        $this->info("Contas processadas: {$processados}");

        return self::SUCCESS;
    }
}
