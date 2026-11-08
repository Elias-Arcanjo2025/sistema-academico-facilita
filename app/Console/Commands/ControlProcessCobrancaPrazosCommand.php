<?php

namespace App\Console\Commands;

use App\Services\Control\ControlHubService;
use Illuminate\Console\Command;

class ControlProcessCobrancaPrazosCommand extends Command
{
    protected $signature = 'control:process-cobranca-prazos';

    protected $description = 'Aplica avisos, atrasos e bloqueios automáticos das cobranças do hub';

    public function handle(ControlHubService $hubService): int
    {
        if (config('control.mode') !== 'hub') {
            $this->warn('CONTROL_MODE não está em "hub". Nada a fazer.');

            return self::SUCCESS;
        }

        $stats = $hubService->processarPrazosCobrancas();

        $this->info(sprintf(
            'Prazos processados — avisos: %d, atrasos: %d, bloqueios: %d',
            $stats['avisos'],
            $stats['atrasos'],
            $stats['bloqueios']
        ));

        return self::SUCCESS;
    }
}
