<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exame de Recuperação contínua (trimestral) — Art.º 30.º DE 424/25.
     * Aplica-se às classes de transição (10.ª e 11.ª) durante cada trimestre.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE notas
                MODIFY COLUMN tipo ENUM(
                    'MAC', 'PP', 'PT', 'MT',
                    'MFT2', 'CF', 'PG',
                    'CA10', 'CA11', 'CA12', 'CFD',
                    'MF', 'Exame', 'Recurso', 'Final',
                    'Melhoria', 'Extraordinario', 'Equivalencia',
                    'Recuperacao'
                ) NOT NULL DEFAULT 'MAC'
            SQL);
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::table('notas')->where('tipo', 'Recuperacao')->delete();

            DB::statement(<<<'SQL'
                ALTER TABLE notas
                MODIFY COLUMN tipo ENUM(
                    'MAC', 'PP', 'PT', 'MT',
                    'MFT2', 'CF', 'PG',
                    'CA10', 'CA11', 'CA12', 'CFD',
                    'MF', 'Exame', 'Recurso', 'Final',
                    'Melhoria', 'Extraordinario', 'Equivalencia'
                ) NOT NULL DEFAULT 'MAC'
            SQL);
        }
    }
};
