<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('anos_lectivos', function (Blueprint $table) {
            // Alterar coluna estado de boolean/tinyint(1) para integer
            // Valores: 0 = anterior, 1 = actual, 2 = novo
            $table->integer('estado')->default(2)->change();
        });

        // Actualizar dados existentes
        // Anos com estado = 1 (true) permanecem como 1 (actual)
        // Anos com estado = 0 (false) permanecem como 0 (anterior)
        // Novos anos serão criados com estado = 2 (novo)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('anos_lectivos', function (Blueprint $table) {
            // Reverter para boolean
            $table->boolean('estado')->default(false)->change();
        });
    }
};
