<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orientacoes_metodologicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('ano_lectivo_id')->nullable()->constrained('anos_lectivos')->nullOnDelete();
            $table->unsignedTinyInteger('trimestre')->nullable();
            $table->string('titulo', 200);
            $table->text('conteudo');
            $table->string('estado', 20)->default('rascunho');
            $table->foreignId('criado_por')->constrained('users')->cascadeOnDelete();
            $table->foreignId('publicado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('publicado_em')->nullable();
            $table->timestamps();

            $table->index(['disciplina_id', 'classe_id', 'estado']);
            $table->index(['estado', 'publicado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orientacoes_metodologicas');
    }
};
