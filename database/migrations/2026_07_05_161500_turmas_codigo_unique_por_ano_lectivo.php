<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O código da turma deve ser único por ano lectivo (não globalmente),
     * para permitir transferência 1:1 entre anos.
     */
    public function up(): void
    {
        Schema::table('turmas', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->unique(['codigo', 'ano_lectivo_id']);
        });
    }

    public function down(): void
    {
        Schema::table('turmas', function (Blueprint $table) {
            $table->dropUnique(['codigo', 'ano_lectivo_id']);
            $table->unique('codigo');
        });
    }
};
