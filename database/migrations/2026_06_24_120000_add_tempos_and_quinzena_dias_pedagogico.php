<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ped_quinzena_dias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quinzena_id')->constrained('ped_quinzenas')->cascadeOnDelete();
            $table->date('data');
            $table->string('tema');
            $table->unsignedTinyInteger('tempos')->default(1);
            $table->time('hora_inicio')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->unique(['quinzena_id', 'data']);
            $table->index(['quinzena_id', 'ordem']);
        });

        Schema::table('ped_aulas', function (Blueprint $table) {
            $table->unsignedTinyInteger('tempos')->default(1)->after('hora_fim');
            $table->string('tema_planeado')->nullable()->after('tempos');
        });

        Schema::table('ped_planos_aula', function (Blueprint $table) {
            $table->unsignedTinyInteger('tempos')->default(1)->after('aula_id');
            $table->timestamp('ministrado_em')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('ped_planos_aula', function (Blueprint $table) {
            $table->dropColumn(['tempos', 'ministrado_em']);
        });

        Schema::table('ped_aulas', function (Blueprint $table) {
            $table->dropColumn(['tempos', 'tema_planeado']);
        });

        Schema::dropIfExists('ped_quinzena_dias');
    }
};
