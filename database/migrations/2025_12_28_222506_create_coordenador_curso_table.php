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
        Schema::create('coordenador_curso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade');
            $table->year('ano_lectivo');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'curso_id', 'ano_lectivo'], 'cc_unique');
            $table->index('user_id');
            $table->index('curso_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coordenador_curso');
    }
};
