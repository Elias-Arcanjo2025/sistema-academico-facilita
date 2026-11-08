<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacao_campanhas', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 160);
            $table->text('mensagem');
            $table->string('publico', 40);
            $table->foreignId('turma_id')->nullable()->constrained('turmas')->nullOnDelete();
            $table->unsignedInteger('enviados')->default(0);
            $table->json('destinatario_ids')->nullable();
            $table->foreignId('criado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['publico', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacao_campanhas');
    }
};
