<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 200);
            $table->text('descricao')->nullable();
            $table->string('tipo', 32)->default('outro');
            $table->dateTime('inicia_em');
            $table->dateTime('termina_em')->nullable();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->cascadeOnDelete();
            $table->foreignId('turma_id')->nullable()->constrained('turmas')->nullOnDelete();
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->nullOnDelete();
            $table->string('visibilidade', 16)->default('escola');
            $table->foreignId('criado_por')->constrained('users')->cascadeOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['ano_lectivo_id', 'inicia_em']);
            $table->index(['turma_id']);
            $table->index(['visibilidade', 'activo']);
        });

        Schema::create('materiais_academicos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 200);
            $table->text('descricao')->nullable();
            $table->string('ficheiro_path', 500);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('tamanho')->default(0);
            $table->foreignId('disciplina_id')->nullable()->constrained('disciplinas')->nullOnDelete();
            $table->foreignId('turma_id')->nullable()->constrained('turmas')->nullOnDelete();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->cascadeOnDelete();
            $table->boolean('publicado')->default(true);
            $table->foreignId('criado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ano_lectivo_id', 'publicado']);
            $table->index(['disciplina_id']);
            $table->index(['turma_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materiais_academicos');
        Schema::dropIfExists('agenda_eventos');
    }
};
