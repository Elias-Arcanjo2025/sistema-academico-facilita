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
        Schema::table('turmas', function (Blueprint $table) {
            // Tornar ano_lectivo nullable (ano_lectivo_id já existe)
            $table->year('ano_lectivo')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turmas', function (Blueprint $table) {
            // Tornar ano_lectivo NOT NULL novamente
            $table->year('ano_lectivo')->nullable(false)->change();
        });
    }
};
