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
        Schema::table('disciplinas', function (Blueprint $table) {
            // Tipo de disciplina: geral, cientifica, tecnica, projeto_estagio
            $table->enum('tipo', ['geral', 'cientifica', 'tecnica', 'projeto_estagio'])
                  ->default('geral')
                  ->after('descricao');

            // Ordem para apresentação (quanto menor, mais no início)
            $table->unsignedInteger('ordem_apresentacao')
                  ->default(1)
                  ->after('tipo')
                  ->comment('Ordem de apresentação dentro do tipo');

            $table->index('tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disciplinas', function (Blueprint $table) {
            $table->dropIndex(['tipo']);
            $table->dropColumn(['tipo', 'ordem_apresentacao']);
        });
    }
};
