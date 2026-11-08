<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->foreignId('encarregado_id')->constrained('encarregados')->cascadeOnDelete();
            $table->foreignId('solicitado_por')->constrained('users')->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->string('estado', 20)->default('pendente');
            $table->text('observacao')->nullable();
            $table->text('nota_secretaria')->nullable();
            $table->foreignId('atendido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('atendido_em')->nullable();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
            $table->index(['encarregado_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_pedidos');
    }
};
