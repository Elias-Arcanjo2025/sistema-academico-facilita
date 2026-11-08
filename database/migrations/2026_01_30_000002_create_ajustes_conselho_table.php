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
        Schema::create('ajustes_conselho', function (Blueprint $table) {
            $table->id();

            // Link para o conselho
            $table->foreignId('conselho_id')->constrained('conselhos_trimestre')->onDelete('cascade');

            // Links para as notas (pré e pós-conselho)
            $table->foreignId('nota_pre_id')->constrained('notas')->onDelete('cascade');
            $table->foreignId('nota_pos_id')->constrained('notas')->onDelete('cascade');

            // Auditoria do ajuste
            $table->decimal('valor_anterior', 4, 2); // Valor pré-conselho
            $table->decimal('valor_novo', 4, 2);     // Valor pós-conselho
            $table->text('justificacao');            // Justificação obrigatória
            $table->foreignId('ajustado_por')->constrained('users'); // Quem fez o ajuste

            $table->timestamps();

            // Indexes
            $table->index('conselho_id');
            $table->index('nota_pre_id');
            $table->index('nota_pos_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ajustes_conselho');
    }
};
