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
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nome', 100);
            $table->integer('ordem')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Criar tabela pivot para disciplinas-classes
        Schema::create('classe_disciplina', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('disciplina_id')->constrained('disciplinas')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['classe_id', 'disciplina_id']);
        });

        // Inserir classes padrao (sistema angolano)
        DB::table('classes')->insert([
            ['codigo' => '7', 'nome' => '7ª Classe', 'ordem' => 7, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '8', 'nome' => '8ª Classe', 'ordem' => 8, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '9', 'nome' => '9ª Classe', 'ordem' => 9, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '10', 'nome' => '10ª Classe', 'ordem' => 10, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '11', 'nome' => '11ª Classe', 'ordem' => 11, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '12', 'nome' => '12ª Classe', 'ordem' => 12, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '13', 'nome' => '13ª Classe', 'ordem' => 13, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classe_disciplina');
        Schema::dropIfExists('classes');
    }
};
