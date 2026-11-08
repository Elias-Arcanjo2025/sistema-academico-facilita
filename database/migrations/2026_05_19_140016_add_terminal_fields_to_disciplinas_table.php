<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca disciplinas como "terminais" numa determinada classe.
     *
     * Uma disciplina terminal é aquela cuja avaliação se encerra numa classe
     * específica (ex.: T.I.C. é terminal na 10ª, Desenho Técnico pode ser terminal
     * na 11ª, etc.). A CFD destas disciplinas precisa ser arrastada para a pauta
     * do 3.º trimestre da classe seguinte.
     */
    public function up(): void
    {
        Schema::table('disciplinas', function (Blueprint $table) {
            // True se esta disciplina é "terminal" em alguma classe
            $table->boolean('terminal')->default(false)->after('tipo');

            // Em QUAL classe a disciplina é terminal (10ª, 11ª, 12ª, ...)
            $table->unsignedBigInteger('classe_terminal_id')->nullable()->after('terminal');

            $table->foreign('classe_terminal_id')
                ->references('id')->on('classes')
                ->nullOnDelete();

            $table->index(['terminal', 'classe_terminal_id']);
        });
    }

    public function down(): void
    {
        Schema::table('disciplinas', function (Blueprint $table) {
            $table->dropForeign(['classe_terminal_id']);
            $table->dropIndex(['terminal', 'classe_terminal_id']);
            $table->dropColumn(['terminal', 'classe_terminal_id']);
        });
    }
};
