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
        Schema::table('notas', function (Blueprint $table) {
            // Adicionar campo para distinguir notas pré e pós-conselho
            $table->enum('estado_conselho', ['pre_conselho', 'pos_conselho'])
                  ->default('pre_conselho')
                  ->after('observacoes');

            // Adicionar referência para nota original (quando for pós-conselho)
            $table->foreignId('nota_origem_id')
                  ->nullable()
                  ->constrained('notas')
                  ->onDelete('set null')
                  ->after('estado_conselho');

            // Indexes para performance
            $table->index('estado_conselho');
            $table->index('nota_origem_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notas', function (Blueprint $table) {
            $table->dropForeign(['nota_origem_id']);
            $table->dropIndex(['estado_conselho']);
            $table->dropIndex(['nota_origem_id']);
            $table->dropColumn(['estado_conselho', 'nota_origem_id']);
        });
    }
};
