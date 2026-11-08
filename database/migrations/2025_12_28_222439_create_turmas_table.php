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
        Schema::create('turmas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nome');
            $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade');
            $table->foreignId('sala_id')->nullable()->constrained('salas')->onDelete('set null');
            $table->foreignId('turno_id')->nullable()->constrained('turnos')->onDelete('set null');
            $table->year('ano_lectivo');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('curso_id');
            $table->index('ano_lectivo');
            $table->index('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turmas');
    }
};
