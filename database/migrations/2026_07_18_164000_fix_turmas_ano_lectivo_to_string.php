<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A migration 2026_02_22 voltou turmas.ano_lectivo para YEAR,
 * incompatível com o formato YYYY-YYYY usado no resto do sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE turmas MODIFY ano_lectivo VARCHAR(9) NULL');
        } else {
            Schema::table('turmas', function (Blueprint $table) {
                $table->string('ano_lectivo', 9)->nullable()->change();
            });
        }

        // Preencher a partir de ano_lectivo_id quando possível
        if ($driver === 'sqlite') {
            DB::statement('
                UPDATE turmas
                SET ano_lectivo = (
                    SELECT a.nome FROM anos_lectivos a WHERE a.id = turmas.ano_lectivo_id
                )
                WHERE ano_lectivo_id IS NOT NULL
                  AND (ano_lectivo IS NULL OR ano_lectivo NOT LIKE "%-%")
            ');
        } else {
            DB::statement('
                UPDATE turmas t
                INNER JOIN anos_lectivos a ON a.id = t.ano_lectivo_id
                SET t.ano_lectivo = a.nome
                WHERE t.ano_lectivo_id IS NOT NULL
                  AND (t.ano_lectivo IS NULL OR t.ano_lectivo NOT LIKE "%-%")
            ');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE turmas MODIFY ano_lectivo YEAR NULL');
        } else {
            Schema::table('turmas', function (Blueprint $table) {
                $table->year('ano_lectivo')->nullable()->change();
            });
        }
    }
};
