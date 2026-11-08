<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turma_horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->cascadeOnDelete();
            $table->foreignId('professor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sala_id')->nullable()->constrained('salas')->nullOnDelete();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->timestamps();

            $table->unique(
                ['turma_id', 'dia_semana', 'hora_inicio', 'ano_lectivo_id'],
                'turma_horario_slot_unique'
            );
            $table->index(
                ['professor_id', 'dia_semana', 'hora_inicio', 'ano_lectivo_id'],
                'professor_horario_slot_idx'
            );
        });

        Schema::create('calendario_provas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->cascadeOnDelete();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->cascadeOnDelete();
            $table->date('data_prova');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fim')->nullable();
            $table->string('tipo', 50)->default('normal');
            $table->string('periodo', 30)->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['turma_id', 'ano_lectivo_id', 'data_prova']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendario_provas');
        Schema::dropIfExists('turma_horarios');
    }
};
