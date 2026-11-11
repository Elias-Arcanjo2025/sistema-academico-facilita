<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aluno_presencas', function (Blueprint $table) {
            $table->string('estado', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('aluno_presencas')->whereNull('estado')->exists()) {
            throw new RuntimeException('Não é possível tornar o estado obrigatório enquanto existirem presenças sem estado.');
        }

        Schema::table('aluno_presencas', function (Blueprint $table) {
            $table->string('estado', 20)->nullable(false)->change();
        });
    }
};
