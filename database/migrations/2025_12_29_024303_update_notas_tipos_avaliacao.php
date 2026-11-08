<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Actualiza os tipos de avaliação:
     * - MAC: Média de Avaliação Contínua
     * - PP: Prova dos Professores
     * - PT: Prova Trimestral
     * - MT: Média Trimestral (calculada)
     * - MF: Média Final (média dos 3 trimestres)
     * - Exame: Exame Final
     * - Recurso: Exame de Recurso
     * - Final: Nota Final do Ano
     */
    public function up(): void
    {
        // Para MySQL, precisamos modificar o enum directamente
        // SQLite não suporta MODIFY COLUMN, mas trata ENUM como TEXT então não é necessário
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE notas MODIFY COLUMN tipo ENUM('MAC', 'PP', 'PT', 'MT', 'MF', 'Exame', 'Recurso', 'Final') DEFAULT 'MAC'");
        }

        // Migrar dados existentes (se houver)
        // P1 -> MAC (era a primeira prova, agora é MAC)
        // P2 -> PP (era a segunda prova, agora é PP)
        DB::table('notas')->where('tipo', 'P1')->update(['tipo' => 'MAC']);
        DB::table('notas')->where('tipo', 'P2')->update(['tipo' => 'PP']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter para o enum anterior (apenas MySQL)
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE notas MODIFY COLUMN tipo ENUM('P1', 'P2', 'MT', 'Exame', 'Recurso', 'Final') DEFAULT 'P1'");
        }

        // Reverter dados
        DB::table('notas')->where('tipo', 'MAC')->update(['tipo' => 'P1']);
        DB::table('notas')->where('tipo', 'PP')->update(['tipo' => 'P2']);
        // PT e MF não existiam antes, serão perdidos na reversão
    }
};
