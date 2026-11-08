<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite o mesmo número de processo em anos lectivos diferentes
 * (modelo de duplicação na transição de alunos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->dropUnique(['numero_processo']);
            $table->index('numero_processo');
        });
    }

    public function down(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->dropIndex(['numero_processo']);
            $table->unique('numero_processo');
        });
    }
};
