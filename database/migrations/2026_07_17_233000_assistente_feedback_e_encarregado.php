<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistente_mensagens', function (Blueprint $table) {
            if (! Schema::hasColumn('assistente_mensagens', 'feedback')) {
                $table->string('feedback', 16)->nullable()->after('tokens_out');
            }
            if (! Schema::hasColumn('assistente_mensagens', 'feedback_at')) {
                $table->timestamp('feedback_at')->nullable()->after('feedback');
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'assistente.usar', 'guard_name' => 'web']);

        $encarregado = Role::query()->where('name', 'encarregado')->where('guard_name', 'web')->first();
        if ($encarregado && ! $encarregado->hasPermissionTo('assistente.usar')) {
            $encarregado->givePermissionTo('assistente.usar');
        }
    }

    public function down(): void
    {
        Schema::table('assistente_mensagens', function (Blueprint $table) {
            if (Schema::hasColumn('assistente_mensagens', 'feedback_at')) {
                $table->dropColumn('feedback_at');
            }
            if (Schema::hasColumn('assistente_mensagens', 'feedback')) {
                $table->dropColumn('feedback');
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $encarregado = Role::query()->where('name', 'encarregado')->where('guard_name', 'web')->first();
        if ($encarregado && $encarregado->hasPermissionTo('assistente.usar')) {
            $encarregado->revokePermissionTo('assistente.usar');
        }
    }
};
