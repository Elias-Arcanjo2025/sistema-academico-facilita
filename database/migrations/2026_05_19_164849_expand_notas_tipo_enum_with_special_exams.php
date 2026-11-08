<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expande o ENUM da coluna `notas.tipo` para incluir os exames de épocas
     * especiais previstos no Capítulo V do Decreto Executivo n.º 424/25:
     *
     *  - Melhoria        — Art.º 34.º (Exame de Melhoria de Nota)
     *  - Extraordinario  — Art.º 32.º (Exame Extraordinário)
     *  - Equivalencia    — Art.º 33.º (Exame de Equivalência)
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
                    'Melhoria', 'Extraordinario', 'Equivalencia'
                ) NOT NULL DEFAULT 'MAC'
            SQL);
        }
        // SQLite trata ENUM como TEXT — não precisa de alteração.
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::table('notas')
                ->whereIn('tipo', ['Melhoria', 'Extraordinario', 'Equivalencia'])
                ->delete();

            DB::statement(<<<'SQL'
                ALTER TABLE notas
                MODIFY COLUMN tipo ENUM(
                    'MAC', 'PP', 'PT', 'MT',
                    'MFT2', 'CF', 'PG',
                    'CA10', 'CA11', 'CA12', 'CFD',
                    'MF', 'Exame', 'Recurso', 'Final'
                ) NOT NULL DEFAULT 'MAC'
            SQL);
        }
    }
};
