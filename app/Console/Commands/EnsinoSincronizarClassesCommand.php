<?php

namespace App\Console\Commands;

use App\Support\EnsinoPerfil;
use Illuminate\Console\Command;

class EnsinoSincronizarClassesCommand extends Command
{
    protected $signature = 'ensino:sincronizar-classes';

    protected $description = 'Activa/desactiva classes conforme ENSINO_PERFIS no .env';

    public function handle(): int
    {
        $resultado = EnsinoPerfil::sincronizarClasses();

        $this->info('Perfis activos: '.implode(', ', EnsinoPerfil::perfisActivos()));
        $this->info('Classes permitidas: '.implode(', ', $resultado['permitidas']));
        $this->line("Actualizadas (activas): {$resultado['activadas']}");
        $this->line("Actualizadas (inactivas): {$resultado['desactivadas']}");

        return self::SUCCESS;
    }
}
