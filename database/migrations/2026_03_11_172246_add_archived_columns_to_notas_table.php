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
        Schema::table('notas', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('updated_at');
            $table->string('archived_reason', 500)->nullable()->after('archived_at');
            $table->foreignId('archived_by')->nullable()->after('archived_reason')->constrained('users')->nullOnDelete();

            // Índice para performance ao filtrar notas ativas/arquivadas
            $table->index('archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notas', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropForeign(['archived_by']);
            $table->dropColumn(['archived_at', 'archived_reason', 'archived_by']);
        });
    }
};
