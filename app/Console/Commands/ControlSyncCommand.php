<?php

namespace App\Console\Commands;

use App\Services\Control\ControlLicenseService;
use Illuminate\Console\Command;

class ControlSyncCommand extends Command
{
    protected $signature = 'control:sync {--heartbeat-only : Enviar só métricas, sem sync de alunos}';

    protected $description = 'Sincroniza métricas e alunos com o hub de controlo Facilita';

    public function handle(ControlLicenseService $licenseService): int
    {
        if (! $licenseService->isSchoolMode()) {
            $this->warn('CONTROL_MODE não está em "school". Nada a fazer.');

            return self::SUCCESS;
        }

        $this->info('A contactar o hub de controlo...');

        $state = $licenseService->refreshFromHub(withSync: ! $this->option('heartbeat-only'));

        $this->line('Estado da licença: '.$state->status);
        if ($state->message) {
            $this->line('Mensagem: '.$state->message);
        }

        return self::SUCCESS;
    }
}
