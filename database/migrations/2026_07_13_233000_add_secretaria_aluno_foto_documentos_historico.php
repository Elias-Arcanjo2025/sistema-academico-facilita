<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            if (! Schema::hasColumn('alunos', 'foto_path')) {
                $table->string('foto_path')->nullable()->after('nome');
            }
            if (! Schema::hasColumn('alunos', 'bi_numero')) {
                $table->string('bi_numero', 50)->nullable()->after('genero');
            }
        });

        Schema::create('aluno_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->string('tipo', 50)->default('outro');
            $table->string('titulo');
            $table->string('path');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('tamanho')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['aluno_id', 'tipo']);
        });

        Schema::create('matricula_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->foreignId('ano_lectivo_id')->nullable()->constrained('anos_lectivos')->nullOnDelete();
            $table->foreignId('turma_id')->nullable()->constrained('turmas')->nullOnDelete();
            $table->string('tipo', 40);
            $table->foreignId('de_turma_id')->nullable()->constrained('turmas')->nullOnDelete();
            $table->foreignId('para_turma_id')->nullable()->constrained('turmas')->nullOnDelete();
            $table->string('motivo', 500)->nullable();
            $table->json('dados')->nullable();
            $table->foreignId('registado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['aluno_id', 'tipo']);
            $table->index(['ano_lectivo_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matricula_historicos');
        Schema::dropIfExists('aluno_documentos');

        Schema::table('alunos', function (Blueprint $table) {
            if (Schema::hasColumn('alunos', 'bi_numero')) {
                $table->dropColumn('bi_numero');
            }
            if (Schema::hasColumn('alunos', 'foto_path')) {
                $table->dropColumn('foto_path');
            }
        });
    }
};
