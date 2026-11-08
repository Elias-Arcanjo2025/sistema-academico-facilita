<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Marca todas as notas existentes como 'pre_conselho' (estado padrão)
     */
    public function up(): void
    {
        // Atualizar todas as notas existentes para pre_conselho
        // (novas notas já virão com este valor por default)
        DB::table('notas')
            ->whereNull('estado_conselho')
            ->orWhere('estado_conselho', '')
            ->update(['estado_conselho' => 'pre_conselho']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Não há necessidade de reverter, pois 'pre_conselho' é o estado padrão
        // e não remove dados
    }
};
