<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Adicionar campo classe_id (nullable inicialmente para popular dados)
        Schema::table('turmas', function (Blueprint $table) {
            $table->unsignedBigInteger('classe_id')->nullable()->after('curso_id');
            $table->foreign('classe_id')->references('id')->on('classes')->onDelete('restrict');
        });

        // Popular classe_id das turmas existentes extraindo do nome
        $this->popularClasseIdTurmas();

        // Tornar classe_id obrigatório após popular dados
        Schema::table('turmas', function (Blueprint $table) {
            $table->unsignedBigInteger('classe_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turmas', function (Blueprint $table) {
            $table->dropForeign(['classe_id']);
            $table->dropColumn('classe_id');
        });
    }

    /**
     * Popular classe_id das turmas existentes
     */
    private function popularClasseIdTurmas(): void
    {
        $turmas = DB::table('turmas')->get();

        foreach ($turmas as $turma) {
            // Extrair número da classe do nome usando regex
            // Padrão esperado: "10ª Classe - Turma A"
            if (preg_match('/^(\d+)ª/', $turma->nome, $matches)) {
                $numeroClasse = $matches[1];

                // Buscar classe pelo código (10, 11, 12 ou 13)
                $classe = DB::table('classes')
                    ->where('codigo', $numeroClasse)
                    ->where('activo', true)
                    ->first();

                if ($classe) {
                    DB::table('turmas')
                        ->where('id', $turma->id)
                        ->update(['classe_id' => $classe->id]);
                }
            }
        }
    }
};
