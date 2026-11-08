<?php

namespace Database\Seeders;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
        ]);

        // Alinha classes activas ao ENSINO_PERFIS do .env
        $resultado = \App\Support\EnsinoPerfil::sincronizarClasses();
        $this->command?->info(
            'Ensino: perfis ['.implode(', ', \App\Support\EnsinoPerfil::perfisActivos()).']'
            .' · classes '.implode(', ', $resultado['permitidas'])
        );
    }
}
