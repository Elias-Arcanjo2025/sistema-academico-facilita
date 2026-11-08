<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ajustes_conselho', function (Blueprint $table) {
            $table->string('motivo_reducao', 50)->nullable()->after('justificacao');
        });
    }

    public function down(): void
    {
        Schema::table('ajustes_conselho', function (Blueprint $table) {
            $table->dropColumn('motivo_reducao');
        });
    }
};
