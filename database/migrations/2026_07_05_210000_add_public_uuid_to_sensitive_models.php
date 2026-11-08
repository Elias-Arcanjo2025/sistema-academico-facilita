<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['alunos', 'users', 'turmas'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->uuid('public_uuid')->nullable()->unique()->after('id');
            });
        }

        foreach (['alunos', 'users', 'turmas'] as $table) {
            DB::table($table)->whereNull('public_uuid')->orderBy('id')->chunkById(100, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update(['public_uuid' => (string) Str::uuid()]);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['alunos', 'users', 'turmas'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('public_uuid');
            });
        }
    }
};
