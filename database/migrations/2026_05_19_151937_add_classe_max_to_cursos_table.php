<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona o campo classe_max (n.º da última classe leccionada pelo curso).
     * Usado para derivar a "terminalidade" de disciplinas:
     *   uma disciplina é terminal num curso se curso_disciplina.classe_fim < curso.classe_max.
     */
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->unsignedTinyInteger('classe_max')->nullable()->after('descricao')
                ->comment('N.º da última classe leccionada pelo curso (ex.: 12 para técnico, 9 para básico)');
        });

        // Preencher com valor derivado das turmas existentes: MAX(classes.ordem)
        $cursos = DB::table('cursos')->get(['id']);
        foreach ($cursos as $curso) {
            $max = DB::table('turmas')
                ->join('classes', 'classes.id', '=', 'turmas.classe_id')
                ->where('turmas.curso_id', $curso->id)
                ->max('classes.ordem');

            DB::table('cursos')
                ->where('id', $curso->id)
                ->update(['classe_max' => $max ?: 12]);
        }
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn('classe_max');
        });
    }
};
