<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aluno_id')->constrained('alunos')->onDelete('cascade');
            $table->foreignId('disciplina_id')->constrained('disciplinas')->onDelete('cascade');
            $table->foreignId('turma_id')->constrained('turmas')->onDelete('cascade');
            $table->foreignId('professor_id')->constrained('users')->onDelete('cascade');
            $table->enum('tipo', ['P1', 'P2', 'MT', 'Exame', 'Recurso', 'Final'])->default('P1');
            $table->decimal('valor', 4, 2)->nullable();
            $table->year('ano_lectivo');
            $table->integer('trimestre')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['aluno_id', 'disciplina_id', 'ano_lectivo']);
            $table->index('professor_id');
            $table->unique(['aluno_id', 'disciplina_id', 'turma_id', 'tipo', 'ano_lectivo', 'trimestre'], 'nota_unica');
        });

        // Adicionar constraint CHECK para valor entre 0 e 20 (apenas MySQL/PostgreSQL)
        // SQLite não suporta ALTER TABLE ADD CONSTRAINT para CHECK constraints
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'sqlite') {
            DB::statement('ALTER TABLE notas ADD CONSTRAINT chk_nota_valor CHECK (valor IS NULL OR (valor >= 0 AND valor <= 20))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notas');
    }
};
