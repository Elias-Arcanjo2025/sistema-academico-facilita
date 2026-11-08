<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presenca_aulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->cascadeOnDelete();
            $table->foreignId('professor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('turma_horario_id')->nullable()->constrained('turma_horarios')->nullOnDelete();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->cascadeOnDelete();
            $table->date('data');
            $table->time('hora_inicio');
            $table->time('hora_fim')->nullable();
            $table->unsignedTinyInteger('trimestre');
            $table->foreignId('registado_por')->constrained('users')->cascadeOnDelete();
            $table->string('observacao', 500)->nullable();
            $table->timestamps();

            $table->unique(
                ['turma_id', 'disciplina_id', 'data', 'hora_inicio'],
                'presenca_aulas_turma_disc_data_hora_unique'
            );
            $table->index(['ano_lectivo_id', 'trimestre', 'disciplina_id']);
            $table->index(['turma_id', 'data']);
        });

        Schema::create('presenca_registos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presenca_aula_id')->constrained('presenca_aulas')->cascadeOnDelete();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->string('estado', 32); // presente | falta_injustificada | falta_justificada | atraso
            $table->string('justificacao', 500)->nullable();
            $table->foreignId('justificado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('justificado_em')->nullable();
            $table->timestamps();

            $table->unique(['presenca_aula_id', 'aluno_id']);
            $table->index(['aluno_id', 'estado']);
        });

        Schema::create('ocorrencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->cascadeOnDelete();
            $table->date('data');
            $table->string('tipo', 32); // atraso | indisciplina | dano_material | agressao | outro
            $table->string('severidade', 16); // baixa | media | alta
            $table->text('descricao');
            $table->text('medida')->nullable();
            $table->string('estado', 16)->default('aberta'); // aberta | resolvida
            $table->foreignId('registado_por')->constrained('users')->cascadeOnDelete();
            $table->foreignId('resolvido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolvido_em')->nullable();
            $table->timestamps();

            $table->index(['turma_id', 'estado']);
            $table->index(['aluno_id', 'data']);
            $table->index(['ano_lectivo_id', 'severidade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocorrencias');
        Schema::dropIfExists('presenca_registos');
        Schema::dropIfExists('presenca_aulas');
    }
};
