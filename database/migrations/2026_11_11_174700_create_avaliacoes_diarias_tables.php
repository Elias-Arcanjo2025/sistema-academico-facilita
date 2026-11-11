<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aluno_presencas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->cascadeOnDelete();
            $table->foreignId('professor_id')->constrained('users')->cascadeOnDelete();
            $table->date('data');
            $table->string('estado', 20)->nullable();
            $table->string('observacao', 500)->nullable();
            $table->timestamps();

            $table->unique(
                ['aluno_id', 'turma_id', 'disciplina_id', 'data'],
                'aluno_presencas_aluno_turma_disc_data_unique'
            );
            $table->index(['turma_id', 'disciplina_id', 'data']);
        });

        Schema::create('aluno_avaliacoes_diarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->cascadeOnDelete();
            $table->foreignId('professor_id')->constrained('users')->cascadeOnDelete();
            $table->date('data');
            $table->decimal('participacao', 4, 2)->nullable();
            $table->decimal('empenho', 4, 2)->nullable();
            $table->decimal('comportamento', 4, 2)->nullable();
            $table->decimal('realizacao_actividades', 4, 2)->nullable();
            $table->decimal('compreensao', 4, 2)->nullable();
            $table->text('observacao_geral')->nullable();
            $table->timestamps();

            $table->unique(
                ['aluno_id', 'turma_id', 'disciplina_id', 'data'],
                'aluno_avaliacoes_aluno_turma_disc_data_unique'
            );
            $table->index(['turma_id', 'disciplina_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aluno_avaliacoes_diarias');
        Schema::dropIfExists('aluno_presencas');
    }
};
