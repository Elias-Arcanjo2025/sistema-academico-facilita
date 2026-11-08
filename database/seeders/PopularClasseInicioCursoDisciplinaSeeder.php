<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PopularClasseInicioCursoDisciplinaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Popular campos classe_inicio e classe_fim na tabela pivot curso_disciplina.
     * Estratégia: Padrão default (10-12) para todas as associações.
     * Ajustes específicos podem ser feitos manualmente depois.
     */
    public function run(): void
    {
        // Contar associações existentes
        $total = DB::table('curso_disciplina')->count();

        if ($total === 0) {
            $this->command->info('Nenhuma associação curso-disciplina encontrada.');
            return;
        }

        $this->command->info("Populando {$total} associações curso-disciplina...");

        // Aplicar valores default para todas as associações
        // Todas as disciplinas começam na 10ª e vão até 12ª por padrão
        DB::table('curso_disciplina')->update([
            'classe_inicio' => 10,
            'classe_fim' => 12,
            'permite_pg' => true,
        ]);

        $this->command->info('✓ Valores populados com sucesso!');
        $this->command->info('  - classe_inicio: 10');
        $this->command->info('  - classe_fim: 12');
        $this->command->info('  - permite_pg: true');
        $this->command->newLine();
        $this->command->warn('NOTA: Ajuste manualmente disciplinas terminais específicas (ex: TIC terminal na 10ª)');
        $this->command->warn('      usando a interface de Associação ou diretamente no banco de dados.');
    }
}
