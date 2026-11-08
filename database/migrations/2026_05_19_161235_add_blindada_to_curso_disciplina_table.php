<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona campo `blindada` à pivot curso_disciplina.
     *
     * Disciplinas "blindadas" são as que, se um aluno tiver TODAS elas com nota
     * negativa simultaneamente, perde o direito ao Exame de Recurso.
     *
     * Regra MED (Art.º 31.º, n.º 7): para a 12.ª, as blindadas são tipicamente
     * Língua Portuguesa, Matemática e uma de formação específica do curso.
     * Em cursos técnicos cada escola pode marcar as suas próprias.
     */
    public function up(): void
    {
        Schema::table('curso_disciplina', function (Blueprint $table) {
            $table->boolean('blindada')->default(false)->after('permite_pg')
                ->comment('Se TRUE, integra o conjunto de disciplinas blindadas (regra de recurso)');
        });

        // Default: marcar Língua Portuguesa e Matemática como blindadas em todos os cursos.
        DB::table('curso_disciplina')
            ->whereIn('disciplina_id', function ($q) {
                $q->select('id')
                  ->from('disciplinas')
                  ->where(function ($q2) {
                      $q2->where('codigo', 'LIKE', 'LP%')
                         ->orWhere('codigo', 'LIKE', 'PORT%')
                         ->orWhere('codigo', 'LIKE', 'MAT%')
                         ->orWhere('nome', 'LIKE', '%Portuguesa%')
                         ->orWhere('nome', 'LIKE', '%Matemática%')
                         ->orWhere('nome', 'LIKE', '%Matematica%');
                  });
            })
            ->update(['blindada' => true]);
    }

    public function down(): void
    {
        Schema::table('curso_disciplina', function (Blueprint $table) {
            $table->dropColumn('blindada');
        });
    }
};
