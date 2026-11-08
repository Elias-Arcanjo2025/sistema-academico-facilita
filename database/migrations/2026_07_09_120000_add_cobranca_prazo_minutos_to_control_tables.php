<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('control_escolas', function (Blueprint $table) {
            $table->unsignedInteger('minutos_ate_aviso')->nullable()->after('preco_anual_aluno');
            $table->unsignedInteger('minutos_ate_atraso')->nullable()->after('minutos_ate_aviso');
            $table->unsignedInteger('minutos_ate_bloqueio')->nullable()->after('minutos_ate_atraso');
        });

        Schema::table('control_cobrancas', function (Blueprint $table) {
            $table->unsignedInteger('minutos_ate_aviso')->nullable()->after('vencimento_em');
            $table->unsignedInteger('minutos_ate_atraso')->nullable()->after('minutos_ate_aviso');
            $table->unsignedInteger('minutos_ate_bloqueio')->nullable()->after('minutos_ate_atraso');
            $table->timestamp('aviso_em')->nullable()->after('minutos_ate_bloqueio');
            $table->timestamp('atraso_em')->nullable()->after('aviso_em');
            $table->timestamp('bloqueio_em')->nullable()->after('atraso_em');
        });
    }

    public function down(): void
    {
        Schema::table('control_cobrancas', function (Blueprint $table) {
            $table->dropColumn([
                'minutos_ate_aviso',
                'minutos_ate_atraso',
                'minutos_ate_bloqueio',
                'aviso_em',
                'atraso_em',
                'bloqueio_em',
            ]);
        });

        Schema::table('control_escolas', function (Blueprint $table) {
            $table->dropColumn([
                'minutos_ate_aviso',
                'minutos_ate_atraso',
                'minutos_ate_bloqueio',
            ]);
        });
    }
};
