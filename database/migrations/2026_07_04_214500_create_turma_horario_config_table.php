<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turma_horario_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->cascadeOnDelete();
            $table->boolean('intervalo_activo')->default(true);
            $table->unsignedTinyInteger('intervalo_apos_periodo')->default(3);
            $table->unsignedSmallInteger('intervalo_duracao_minutos')->default(25);
            $table->string('intervalo_texto')->nullable();
            $table->timestamps();

            $table->unique(['turma_id', 'ano_lectivo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turma_horario_config');
    }
};
