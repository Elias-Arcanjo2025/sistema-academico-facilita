<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Cria tabela pivot para relação many-to-many entre cursos e disciplinas.
     * Migra dados existentes da coluna curso_id.
     */
    public function up(): void
    {
        // Criar tabela pivot
        Schema::create('curso_disciplina', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade');
            $table->foreignId('disciplina_id')->constrained('disciplinas')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['curso_id', 'disciplina_id']);
        });

        // Migrar dados existentes (se houver disciplinas com curso_id)
        $disciplinas = DB::table('disciplinas')->whereNotNull('curso_id')->get();
        foreach ($disciplinas as $disciplina) {
            DB::table('curso_disciplina')->insert([
                'curso_id' => $disciplina->curso_id,
                'disciplina_id' => $disciplina->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curso_disciplina');
    }
};
