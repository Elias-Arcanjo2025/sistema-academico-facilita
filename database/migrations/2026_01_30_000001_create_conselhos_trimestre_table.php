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
        Schema::create('conselhos_trimestre', function (Blueprint $table) {
            $table->id();

            // Identificação do conselho
            $table->foreignId('turma_id')->constrained('turmas')->onDelete('cascade');
            $table->integer('trimestre'); // 1, 2, or 3
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->onDelete('cascade');
            $table->string('ano_lectivo', 9); // "2025-2026"

            // Estado do conselho
            $table->enum('estado', ['pendente', 'em_curso', 'concluido'])->default('pendente');
            $table->dateTime('data_inicio')->nullable();
            $table->dateTime('data_conclusao')->nullable();

            // Quem iniciou e concluiu o conselho
            $table->foreignId('iniciado_por')->nullable()->constrained('users');
            $table->foreignId('concluido_por')->nullable()->constrained('users');

            // Observações sobre a reunião
            $table->text('observacoes')->nullable();

            $table->timestamps();

            // Unique constraint: um conselho por turma/trimestre/ano
            $table->unique(['turma_id', 'trimestre', 'ano_lectivo_id'], 'conselho_unico');

            // Indexes para performance
            $table->index(['turma_id', 'trimestre', 'ano_lectivo_id']);
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conselhos_trimestre');
    }
};
