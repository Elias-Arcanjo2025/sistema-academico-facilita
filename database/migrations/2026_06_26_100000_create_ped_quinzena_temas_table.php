<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ped_quinzena_temas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quinzena_id')->constrained('ped_quinzenas')->cascadeOnDelete();
            $table->string('codigo', 30)->nullable();
            $table->string('titulo');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->index(['quinzena_id', 'ordem']);
        });

        $quinzenas = DB::table('ped_quinzenas')->whereNotNull('tema')->where('tema', '!=', '')->get();
        foreach ($quinzenas as $q) {
            DB::table('ped_quinzena_temas')->insert([
                'quinzena_id' => $q->id,
                'codigo' => null,
                'titulo' => $q->tema,
                'ordem' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ped_quinzena_temas');
    }
};
