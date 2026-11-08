<?php

namespace App\Support;

use App\Models\Classe;
use App\Models\Configuracao;
use Illuminate\Support\Facades\DB;

/**
 * Perfis de ensino da instalação (sem multi-tenant).
 * Controla quais classes ficam activas / seleccionáveis.
 *
 * Prioridade: valor em Configurações (BD) → ENSINO_PERFIS no .env → ii_ciclo.
 */
class EnsinoPerfil
{
    public const PRIMARIO = 'primario';

    public const I_CICLO = 'i_ciclo';

    public const II_CICLO = 'ii_ciclo';

    public const CONFIG_CHAVE = 'ensino_perfis';

    /**
     * @return list<string>
     */
    public static function perfisActivos(): array
    {
        $validos = array_keys(config('ensino.mapa', []));
        $raw = self::valorConfigurado();

        $perfis = array_values(array_intersect(
            array_values(array_filter(array_map('trim', explode(',', $raw)))),
            $validos
        ));

        return $perfis !== [] ? $perfis : [self::II_CICLO];
    }

    /**
     * Fonte efectiva: "bd" se gravado em Configurações, senão "env".
     */
    public static function fonte(): string
    {
        $bd = self::valorDaBaseDados();

        return ($bd !== null && $bd !== '') ? 'bd' : 'env';
    }

    public static function valorConfigurado(): string
    {
        $bd = self::valorDaBaseDados();
        if ($bd !== null && $bd !== '') {
            return $bd;
        }

        return implode(',', config('ensino.perfis', [self::II_CICLO]));
    }

    private static function valorDaBaseDados(): ?string
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('configuracoes')) {
                return null;
            }

            $bd = Configuracao::get(self::CONFIG_CHAVE);
            if ($bd === null) {
                return null;
            }

            $bd = trim((string) $bd);

            return $bd === '' ? null : $bd;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Grava perfis na BD e sincroniza classes activas.
     *
     * @param  list<string>  $perfis
     * @return array{activadas:int, desactivadas:int, permitidas:list<int>}
     */
    public static function gravarESincronizar(array $perfis): array
    {
        $validos = array_keys(config('ensino.mapa', []));
        $limpos = array_values(array_intersect(
            array_values(array_unique(array_filter(array_map('trim', $perfis)))),
            $validos
        ));

        if ($limpos === []) {
            $limpos = [self::II_CICLO];
        }

        Configuracao::set(
            self::CONFIG_CHAVE,
            implode(',', $limpos),
            'Perfis de ensino da instalação (primario, i_ciclo, ii_ciclo)'
        );

        return self::sincronizarClasses();
    }

    /**
     * Números de classe (ordem/código) permitidos nesta instalação.
     *
     * @return list<int>
     */
    public static function classesPermitidas(): array
    {
        $mapa = config('ensino.mapa', []);
        $ordens = [];

        foreach (self::perfisActivos() as $perfil) {
            foreach ($mapa[$perfil] ?? [] as $ordem) {
                $ordens[] = (int) $ordem;
            }
        }

        $ordens = array_values(array_unique($ordens));
        sort($ordens);

        return $ordens;
    }

    public static function permiteClasse(int $ordemOuCodigo): bool
    {
        return in_array($ordemOuCodigo, self::classesPermitidas(), true);
    }

    public static function permiteClasseId(int $classeId): bool
    {
        $ordem = Classe::query()->where('id', $classeId)->value('ordem');

        return $ordem !== null && self::permiteClasse((int) $ordem);
    }

    /**
     * @return array<string, string>
     */
    public static function rotulosActivos(): array
    {
        $rotulos = config('ensino.rotulos', []);
        $out = [];
        foreach (self::perfisActivos() as $perfil) {
            $out[$perfil] = $rotulos[$perfil] ?? $perfil;
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public static function todosRotulos(): array
    {
        return config('ensino.rotulos', []);
    }

    /**
     * Activa/desactiva linhas em `classes` conforme perfis activos.
     *
     * @return array{activadas:int, desactivadas:int, permitidas:list<int>}
     */
    public static function sincronizarClasses(): array
    {
        $permitidas = self::classesPermitidas();

        $activadas = DB::table('classes')
            ->whereIn('ordem', $permitidas)
            ->update(['activo' => true, 'updated_at' => now()]);

        $desactivadas = DB::table('classes')
            ->whereNotIn('ordem', $permitidas)
            ->update(['activo' => false, 'updated_at' => now()]);

        return [
            'activadas' => (int) $activadas,
            'desactivadas' => (int) $desactivadas,
            'permitidas' => $permitidas,
        ];
    }
}
