<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disciplinas', function (Blueprint $table) {
            $table->string('nivel_dificuldade', 20)->default('medio')->after('tipo');
        });

        DB::table('disciplinas')->where('tipo', 'geral')->update(['nivel_dificuldade' => 'facil']);
        DB::table('disciplinas')->whereIn('tipo', ['cientifica', 'tecnica'])->update(['nivel_dificuldade' => 'dificil']);
        DB::table('disciplinas')->where('tipo', 'projeto_estagio')->update(['nivel_dificuldade' => 'medio']);
    }

    public function down(): void
    {
        Schema::table('disciplinas', function (Blueprint $table) {
            $table->dropColumn('nivel_dificuldade');
        });
    }
};
