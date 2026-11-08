<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conteudos_minimos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->nullOnDelete();
            $table->foreignId('ano_lectivo_id')->nullable()->constrained('anos_lectivos')->nullOnDelete();
            $table->unsignedTinyInteger('trimestre')->nullable();
            $table->string('titulo', 200);
            $table->string('codigo_oficial', 64)->nullable();
            $table->text('descricao')->nullable();
            $table->string('estado', 20)->default('rascunho');
            $table->unsignedInteger('versao')->default(1);
            $table->foreignId('publicado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('publicado_em')->nullable();
            $table->foreignId('criado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['disciplina_id', 'classe_id', 'estado']);
            $table->index(['estado', 'versao']);
        });

        Schema::create('conteudo_minimo_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conteudo_minimo_id')->constrained('conteudos_minimos')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('conteudo_minimo_itens')->cascadeOnDelete();
            $table->string('codigo', 64)->nullable();
            $table->string('titulo', 255);
            $table->text('descricao')->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->boolean('obrigatorio')->default(true);
            $table->unsignedSmallInteger('carga_horaria_sugerida')->nullable();
            $table->unsignedTinyInteger('trimestre_sugerido')->nullable();
            $table->timestamps();

            $table->index(['conteudo_minimo_id', 'ordem']);
            $table->index(['parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conteudo_minimo_itens');
        Schema::dropIfExists('conteudos_minimos');
    }
};
