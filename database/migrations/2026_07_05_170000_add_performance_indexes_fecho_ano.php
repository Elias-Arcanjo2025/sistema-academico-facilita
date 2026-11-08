<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas', function (Blueprint $table) {
            $table->index(['aluno_id', 'ano_lectivo_id', 'tipo'], 'notas_aluno_ano_tipo_idx');
            $table->index(['turma_id', 'disciplina_id', 'trimestre', 'ano_lectivo_id'], 'notas_turma_disc_tri_ano_idx');
        });

        Schema::table('turmas', function (Blueprint $table) {
            $table->index(['ano_lectivo_id', 'activo'], 'turmas_ano_activo_idx');
        });

        Schema::table('professor_disciplina_turma', function (Blueprint $table) {
            $table->index(['user_id', 'ano_lectivo_id', 'activo'], 'pdt_user_ano_activo_idx');
        });

        Schema::table('alunos', function (Blueprint $table) {
            $table->index(['turma_id', 'activo'], 'alunos_turma_activo_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notas', function (Blueprint $table) {
            $table->dropIndex('notas_aluno_ano_tipo_idx');
            $table->dropIndex('notas_turma_disc_tri_ano_idx');
        });

        Schema::table('turmas', function (Blueprint $table) {
            $table->dropIndex('turmas_ano_activo_idx');
        });

        Schema::table('professor_disciplina_turma', function (Blueprint $table) {
            $table->dropIndex('pdt_user_ano_activo_idx');
        });

        Schema::table('alunos', function (Blueprint $table) {
            $table->dropIndex('alunos_turma_activo_idx');
        });
    }
};
