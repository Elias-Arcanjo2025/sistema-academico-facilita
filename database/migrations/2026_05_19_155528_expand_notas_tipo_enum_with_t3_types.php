<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expande o ENUM da coluna `notas.tipo` para incluir todos os tipos usados pelo
     * modelo App\Models\Nota — em especial os do 3.º trimestre (MFT2, CF, PG,
     * CA10, CA11, CA12, CFD), que estavam definidos no modelo mas não na BD.
     *
     * Resolve o erro: "Data truncated for column 'tipo'" ao tentar guardar CFDs.
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
                    'MF', 'Exame', 'Recurso', 'Final'
                ) NOT NULL DEFAULT 'MAC'
            SQL);
        }
        // SQLite trata ENUM como TEXT — não precisa de alteração.
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // Antes de reverter, garantir que não há registos com os tipos novos
            // (senão a operação falha por truncamento de dados).
            DB::table('notas')
                ->whereIn('tipo', ['MFT2', 'CF', 'PG', 'CA10', 'CA11', 'CA12', 'CFD'])
                ->delete();

            DB::statement("ALTER TABLE notas MODIFY COLUMN tipo ENUM('MAC', 'PP', 'PT', 'MT', 'MF', 'Exame', 'Recurso', 'Final') NOT NULL DEFAULT 'MAC'");
        }
    }
};
