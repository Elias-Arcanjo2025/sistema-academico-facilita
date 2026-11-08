<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Desativar classes 7ª, 8ª e 9ª (sistema só trabalha com 10ª a 13ª)
        DB::table('classes')
            ->whereIn('codigo', ['7', '8', '9'])
            ->update(['activo' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reativar classes 7ª, 8ª e 9ª
        DB::table('classes')
            ->whereIn('codigo', ['7', '8', '9'])
            ->update(['activo' => true]);
    }
};
