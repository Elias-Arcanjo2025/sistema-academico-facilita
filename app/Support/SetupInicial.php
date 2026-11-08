<?php

namespace App\Support;

use App\Models\Configuracao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Assistente de primeira configuração da instalação.
 */
class SetupInicial
{
    public const CONFIG_CHAVE = 'setup_inicial_concluido';

    public static function estaConcluido(): bool
    {
        try {
            if (! Schema::hasTable('configuracoes')) {
                return true;
            }

            return (string) Configuracao::get(self::CONFIG_CHAVE, '') === '1';
        } catch (\Throwable) {
            return true;
        }
    }

    public static function estaPendente(): bool
    {
        if (config('control.mode') === 'hub') {
            return false;
        }

        return ! self::estaConcluido();
    }

    public static function marcarConcluido(): void
    {
        Configuracao::set(
            self::CONFIG_CHAVE,
            '1',
            'Assistente de primeira configuração concluído'
        );
    }

    /**
     * Instalações já em uso não devem ver o assistente.
     */
    public static function marcarConcluidoSeInstalacaoExistente(): bool
    {
        if (self::estaConcluido()) {
            return false;
        }

        $emUso = false;

        try {
            if (Schema::hasTable('turmas') && DB::table('turmas')->exists()) {
                $emUso = true;
            }

            $perfisBd = Configuracao::get(EnsinoPerfil::CONFIG_CHAVE);
            if ($perfisBd !== null && trim((string) $perfisBd) !== '') {
                $emUso = true;
            }
        } catch (\Throwable) {
            return false;
        }

        if (! $emUso) {
            return false;
        }

        self::marcarConcluido();

        return true;
    }
}
