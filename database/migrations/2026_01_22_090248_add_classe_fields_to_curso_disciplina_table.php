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
        Schema::table('curso_disciplina', function (Blueprint $table) {
            $table->integer('classe_inicio')->nullable()->after('disciplina_id')
                ->comment('Classe em que a disciplina inicia neste curso (10, 11 ou 12)');
            $table->integer('classe_fim')->nullable()->after('classe_inicio')
                ->comment('Classe em que a disciplina termina neste curso (10, 11 ou 12)');
            $table->boolean('permite_pg')->default(true)->after('classe_fim')
                ->comment('Se permite Prova Global');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curso_disciplina', function (Blueprint $table) {
            $table->dropColumn(['classe_inicio', 'classe_fim', 'permite_pg']);
        });
    }
};
