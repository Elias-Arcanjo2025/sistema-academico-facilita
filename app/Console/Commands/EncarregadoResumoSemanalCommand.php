<?php

namespace App\Console\Commands;

use App\Services\Portal\EncarregadoResumoSemanalService;
use Illuminate\Console\Command;

class EncarregadoResumoSemanalCommand extends Command
{
    protected $signature = 'portal:encarregado-resumo-semanal';

    protected $description = 'Envia o resumo semanal (digest) aos encarregados no portal';

    public function handle(EncarregadoResumoSemanalService $resumo): int
    {
        $this->info('A gerar digests semanais para encarregados...');
        $enviados = $resumo->notificarTodosOsEncarregados();
        $this->info("Notificações enviadas: {$enviados}");

        return self::SUCCESS;
    }
}
