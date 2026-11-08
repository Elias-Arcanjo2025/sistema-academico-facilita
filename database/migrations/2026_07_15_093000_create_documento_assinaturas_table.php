<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_assinaturas', function (Blueprint $table) {
            $table->id();
            $table->string('documento_tipo', 64);
            $table->string('documento_chave', 191);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('cargo', 64);
            $table->string('hash_documento', 64);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('assinado_em');
            $table->timestamps();

            $table->unique(
                ['documento_tipo', 'documento_chave', 'user_id', 'cargo'],
                'doc_assinatura_unica'
            );
            $table->index(['documento_tipo', 'documento_chave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_assinaturas');
    }
};
