<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistente_conversas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('titulo')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('assistente_mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversa_id')->constrained('assistente_conversas')->cascadeOnDelete();
            $table->string('papel', 16);
            $table->text('conteudo');
            $table->json('contexto_usado')->nullable();
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->timestamps();

            $table->index(['conversa_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistente_mensagens');
        Schema::dropIfExists('assistente_conversas');
    }
};
