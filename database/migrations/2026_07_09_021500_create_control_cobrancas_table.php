<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_cobrancas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('control_escola_id')->constrained('control_escolas')->cascadeOnDelete();
            $table->string('referencia', 40)->unique();
            $table->string('ano_lectivo')->nullable();
            $table->unsignedInteger('total_alunos')->default(0);
            $table->decimal('preco_unitario', 12, 2)->default(0);
            $table->decimal('valor_total', 14, 2)->default(0);
            $table->string('status', 32)->default('aberta'); // aberta|paga|cancelada|vencida
            $table->date('vencimento_em')->nullable();
            $table->timestamp('pago_em')->nullable();
            $table->string('metodo_pagamento')->nullable();
            $table->string('referencia_pagamento')->nullable();
            $table->text('notas')->nullable();
            $table->string('aviso_escola')->nullable(); // mensagem mostrada na escola
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pago_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['control_escola_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_cobrancas');
    }
};
