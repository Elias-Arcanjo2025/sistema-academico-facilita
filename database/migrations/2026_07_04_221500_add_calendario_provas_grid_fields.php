<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendario_provas', function (Blueprint $table) {
            $table->unsignedTinyInteger('slot')->default(1)->after('data_prova');
            $table->string('supervisor_nome', 120)->nullable()->after('observacoes');
        });

        Schema::create('turma_calendario_prova_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->cascadeOnDelete();
            $table->time('hora_1a_inicio')->default('08:30');
            $table->time('hora_1a_fim')->default('10:20');
            $table->time('hora_2a_inicio')->default('10:30');
            $table->time('hora_2a_fim')->default('12:20');
            $table->timestamps();

            $table->unique(['turma_id', 'ano_lectivo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turma_calendario_prova_config');

        Schema::table('calendario_provas', function (Blueprint $table) {
            $table->dropColumn(['slot', 'supervisor_nome']);
        });
    }
};
