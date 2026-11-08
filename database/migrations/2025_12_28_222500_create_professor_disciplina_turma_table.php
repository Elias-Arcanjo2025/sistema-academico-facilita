<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('professor_disciplina_turma', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('disciplina_id')->constrained('disciplinas')->onDelete('cascade');
            $table->foreignId('turma_id')->constrained('turmas')->onDelete('cascade');
            $table->year('ano_lectivo');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'disciplina_id', 'turma_id', 'ano_lectivo'], 'pdt_unique');
            $table->index('user_id');
            $table->index('disciplina_id');
            $table->index('turma_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professor_disciplina_turma');
    }
};
