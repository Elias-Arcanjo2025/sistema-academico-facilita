<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coordenador_disciplina_classe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coordenador_disciplina_id')
                ->constrained('coordenador_disciplina')
                ->cascadeOnDelete();
            $table->foreignId('classe_id')
                ->constrained('classes')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['coordenador_disciplina_id', 'classe_id'],
                'coord_disc_classe_unique'
            );
        });

        $classeIds = DB::table('classes')->where('activo', true)->pluck('id');
        if ($classeIds->isEmpty()) {
            return;
        }

        $now = now();
        $rows = [];
        foreach (DB::table('coordenador_disciplina')->pluck('id') as $coordId) {
            foreach ($classeIds as $classeId) {
                $rows[] = [
                    'coordenador_disciplina_id' => $coordId,
                    'classe_id' => $classeId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('coordenador_disciplina_classe')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coordenador_disciplina_classe');
    }
};
