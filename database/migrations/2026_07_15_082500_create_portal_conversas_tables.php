<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_conversas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->foreignId('encarregado_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['aluno_id', 'encarregado_user_id'], 'portal_conversa_aluno_enc_unique');
        });

        Schema::create('portal_mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversa_id')->constrained('portal_conversas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('corpo');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversa_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_mensagens');
        Schema::dropIfExists('portal_conversas');
    }
};
