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
        Schema::create('alunos', function (Blueprint $table) {
            $table->id();
            $table->string('numero_processo', 20)->unique();
            $table->string('nome');
            $table->date('data_nascimento')->nullable();
            $table->enum('genero', ['M', 'F'])->nullable();
            $table->foreignId('turma_id')->constrained('turmas')->onDelete('cascade');
            $table->integer('numero_ordem')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('turma_id');
            $table->index('activo');
            $table->unique(['turma_id', 'numero_ordem']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alunos');
    }
};
