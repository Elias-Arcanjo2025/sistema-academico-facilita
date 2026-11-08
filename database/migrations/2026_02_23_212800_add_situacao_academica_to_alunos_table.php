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
        Schema::table('alunos', function (Blueprint $table) {
            $table->enum('situacao_academica', [
                'matriculado',
                'aprovado',
                'reprovado',
                'desistente',
                'transferido'
            ])->default('matriculado')->after('activo');

            $table->index('situacao_academica');
        });

        // Atualizar alunos existentes para 'matriculado'
        \DB::table('alunos')->update(['situacao_academica' => 'matriculado']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->dropIndex(['situacao_academica']);
            $table->dropColumn('situacao_academica');
        });
    }
};
