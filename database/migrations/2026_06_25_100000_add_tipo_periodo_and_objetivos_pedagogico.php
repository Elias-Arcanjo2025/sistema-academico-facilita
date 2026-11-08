<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ped_planificacoes', function (Blueprint $table) {
            $table->string('tipo_periodo', 20)->default('quinzenal')->after('estado');
        });

        Schema::table('ped_quinzenas', function (Blueprint $table) {
            $table->text('objetivos_geral')->nullable()->after('objetivos');
            $table->text('objetivos_especificos')->nullable()->after('objetivos_geral');
        });

        Schema::table('ped_quinzena_dias', function (Blueprint $table) {
            $table->text('objetivos_geral')->nullable()->after('tema');
            $table->text('objetivos_especificos')->nullable()->after('objetivos_geral');
        });

        Schema::table('ped_aulas', function (Blueprint $table) {
            $table->text('objetivos_geral')->nullable()->after('tema_planeado');
            $table->text('objetivos_especificos')->nullable()->after('objetivos_geral');
        });
    }

    public function down(): void
    {
        Schema::table('ped_aulas', function (Blueprint $table) {
            $table->dropColumn(['objetivos_geral', 'objetivos_especificos']);
        });

        Schema::table('ped_quinzena_dias', function (Blueprint $table) {
            $table->dropColumn(['objetivos_geral', 'objetivos_especificos']);
        });

        Schema::table('ped_quinzenas', function (Blueprint $table) {
            $table->dropColumn(['objetivos_geral', 'objetivos_especificos']);
        });

        Schema::table('ped_planificacoes', function (Blueprint $table) {
            $table->dropColumn('tipo_periodo');
        });
    }
};
