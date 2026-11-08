<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suporta o Art.º 32.º do Decreto Executivo n.º 424/25 (Exame Extraordinário).
     *
     *  - atleta_alta_competicao: aluno atleta de alta competição / desporto de alto
     *    rendimento / selecções provinciais ou nacionais — elegível ao Extraordinário
     *    nos termos da alínea e) do n.º 2 do Art.º 32.
     *  - faltou_exame_normal: marcado por motivos justificados (alínea b do n.º 2);
     *    elegível ao Extraordinário para concluir o ciclo.
     */
    public function up(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->boolean('atleta_alta_competicao')
                ->default(false)
                ->after('necessidades_especificas')
                ->comment('Atleta de alta competição / selecção (Art.º 32, n.º 2-e do DE 424/25)');

            $table->boolean('faltou_exame_normal')
                ->default(false)
                ->after('atleta_alta_competicao')
                ->comment('Faltou ao exame nacional/recurso por motivo justificado (Art.º 32, n.º 2-b)');
        });
    }

    public function down(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->dropColumn(['atleta_alta_competicao', 'faltou_exame_normal']);
        });
    }
};
