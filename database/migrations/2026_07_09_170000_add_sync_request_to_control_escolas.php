<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('control_escolas', function (Blueprint $table) {
            $table->text('secret_encrypted')->nullable()->after('secret_hash');
            $table->timestamp('sync_requested_at')->nullable()->after('last_sync_at');
        });
    }

    public function down(): void
    {
        Schema::table('control_escolas', function (Blueprint $table) {
            $table->dropColumn(['secret_encrypted', 'sync_requested_at']);
        });
    }
};
