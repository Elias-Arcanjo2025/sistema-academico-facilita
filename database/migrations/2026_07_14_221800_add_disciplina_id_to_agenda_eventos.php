<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_eventos', function (Blueprint $table) {
            $table->foreignId('disciplina_id')
                ->nullable()
                ->after('curso_id')
                ->constrained('disciplinas')
                ->nullOnDelete();
            $table->index(['disciplina_id']);
        });
    }

    public function down(): void
    {
        Schema::table('agenda_eventos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disciplina_id');
        });
    }
};
