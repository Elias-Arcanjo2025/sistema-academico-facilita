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
        Schema::table('notas', function (Blueprint $table) {
            // Dropar constraint unique antigo
            $table->dropUnique('nota_unica');

            // Criar novo constraint unique incluindo estado_conselho
            // Isso permite que existam 2 versões da mesma nota (pré e pós-conselho)
            $table->unique(
                ['aluno_id', 'disciplina_id', 'turma_id', 'tipo', 'ano_lectivo', 'trimestre', 'estado_conselho'],
                'nota_unica_com_estado'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notas', function (Blueprint $table) {
            // Dropar novo constraint
            $table->dropUnique('nota_unica_com_estado');

            // Restaurar constraint antigo
            $table->unique(
                ['aluno_id', 'disciplina_id', 'turma_id', 'tipo', 'ano_lectivo', 'trimestre'],
                'nota_unica'
            );
        });
    }
};
