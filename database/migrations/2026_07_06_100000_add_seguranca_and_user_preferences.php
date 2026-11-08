<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('password_changed_at');
            }
            if (! Schema::hasColumn('users', 'preferencias')) {
                $table->json('preferencias')->nullable()->after('last_login_at');
            }
        });

        $now = now();

        if (! DB::table('configuracoes')->where('chave', 'bloqueio_inactividade_activo')->exists()) {
            DB::table('configuracoes')->insert([
                'chave' => 'bloqueio_inactividade_activo',
                'valor' => '1',
                'tipo' => 'boolean',
                'descricao' => 'Activar bloqueio de sessão por inactividade',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('configuracoes')->where('chave', 'bloqueio_inactividade_minutos')->exists()) {
            DB::table('configuracoes')->insert([
                'chave' => 'bloqueio_inactividade_minutos',
                'valor' => '30',
                'tipo' => 'integer',
                'descricao' => 'Minutos de inactividade antes de bloquear a sessão',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'preferencias')) {
                $table->dropColumn('preferencias');
            }
            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->dropColumn('last_login_at');
            }
        });

        DB::table('configuracoes')->whereIn('chave', [
            'bloqueio_inactividade_activo',
            'bloqueio_inactividade_minutos',
        ])->delete();
    }
};
