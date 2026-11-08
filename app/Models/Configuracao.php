<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Configuracao extends Model
{
    use HasFactory;

    protected $table = 'configuracoes';

    protected $fillable = [
        'chave',
        'valor',
        'descricao',
    ];

    protected const CACHE_TTL = 3600;

    protected const CACHE_RESOLVED_TTL = 600;

    /** @var array<string, mixed> */
    protected static array $memo = [];

    protected static ?string $anoMemo = null;

    protected static bool $anoMemoSet = false;

    protected static ?int $anoIdMemo = null;

    protected static bool $anoIdMemoSet = false;

    protected static ?int $trimestreMemo = null;

    protected static bool $trimestreMemoSet = false;

    public static function get(string $chave, $default = null)
    {
        if (array_key_exists($chave, static::$memo)) {
            $valor = static::$memo[$chave];

            return $valor !== null ? $valor : $default;
        }

        $valor = Cache::remember(
            static::cacheKey($chave),
            self::CACHE_TTL,
            fn () => static::query()->where('chave', $chave)->value('valor')
        );

        static::$memo[$chave] = $valor;

        return $valor !== null ? $valor : $default;
    }

    public static function set(string $chave, $valor, ?string $descricao = null): static
    {
        $row = static::updateOrCreate(
            ['chave' => $chave],
            [
                'valor' => $valor,
                'descricao' => $descricao,
            ]
        );

        Cache::forget(static::cacheKey($chave));
        unset(static::$memo[$chave]);

        if (in_array($chave, ['ano_lectivo_actual', 'trimestre_actual'], true)
            || str_starts_with($chave, 'trimestre_')
            || str_starts_with($chave, 'lancamento_t')) {
            static::forgetResolvedCaches();
        }

        return $row;
    }

    /**
     * Obtém o ano lectivo actual no formato 2025-2026
     */
    public static function getAnoLectivoActual(): ?string
    {
        if (static::$anoMemoSet) {
            return static::$anoMemo;
        }

        static::$anoMemo = Cache::remember('config_resolved_ano_lectivo', self::CACHE_RESOLVED_TTL, function () {
            $anoLectivo = AnoLectivo::getActual();
            if ($anoLectivo) {
                return $anoLectivo->nome;
            }

            $valor = static::query()->where('chave', 'ano_lectivo_actual')->value('valor');
            if ($valor) {
                return $valor;
            }

            $mes = (int) date('m');
            $ano = (int) date('Y');

            if ($mes >= 9) {
                return $ano.'-'.($ano + 1);
            }

            return ($ano - 1).'-'.$ano;
        });

        static::$anoMemoSet = true;

        return static::$anoMemo;
    }

    /**
     * Obtém o ID do ano lectivo actual
     */
    public static function getAnoLectivoActualId(): ?int
    {
        if (static::$anoIdMemoSet) {
            return static::$anoIdMemo;
        }

        static::$anoIdMemo = Cache::remember('config_resolved_ano_lectivo_id', self::CACHE_RESOLVED_TTL, function () {
            $anoLectivoNome = static::getAnoLectivoActual();
            if (! $anoLectivoNome) {
                return null;
            }

            return AnoLectivo::getIdByNome($anoLectivoNome);
        });

        static::$anoIdMemoSet = true;

        return static::$anoIdMemo;
    }

    /**
     * Define o ano lectivo actual
     */
    public static function setAnoLectivoActual(string $anoLectivo): void
    {
        static::set('ano_lectivo_actual', $anoLectivo, 'Ano lectivo actual do sistema');
    }

    /**
     * Gera lista de anos lectivos para selects (5 anos para trás e 2 para frente)
     */
    public static function getAnosLectivosDisponiveis(): array
    {
        $anoActual = (int) date('Y');
        $anos = [];

        for ($ano = $anoActual - 5; $ano <= $anoActual + 2; $ano++) {
            $anoLectivo = $ano.'-'.($ano + 1);
            $anos[$anoLectivo] = $anoLectivo;
        }

        return $anos;
    }

    public static function getTrimestreActual(): ?int
    {
        if (static::$trimestreMemoSet) {
            return static::$trimestreMemo;
        }

        $valor = static::get('trimestre_actual');
        if ($valor) {
            static::$trimestreMemo = (int) $valor;
            static::$trimestreMemoSet = true;

            return static::$trimestreMemo;
        }

        $mes = (int) date('m');

        if ($mes >= 9 && $mes <= 11) {
            static::$trimestreMemo = 1;
        } elseif ($mes >= 1 && $mes <= 3) {
            static::$trimestreMemo = 2;
        } elseif ($mes >= 4 && $mes <= 6) {
            static::$trimestreMemo = 3;
        } else {
            static::$trimestreMemo = 1;
        }

        static::$trimestreMemoSet = true;

        return static::$trimestreMemo;
    }

    /**
     * Verifica se um trimestre está aberto para lançamento de notas.
     *
     * Regras (todas têm de se verificar):
     *  1. O trimestre tem de ser o trimestre actual configurado no sistema.
     *  2. O trimestre não pode estar marcado manualmente como "fechado".
     *  3. Se estiver configurado um período de lançamento (início + fim),
     *     a data actual tem de estar dentro desse intervalo.
     */
    public static function trimestreAbertoParaLancamento(int $trimestre): bool
    {
        $trimestreActual = static::getTrimestreActual();

        if ($trimestre !== $trimestreActual) {
            return false;
        }

        $trimestreFechado = static::get("trimestre_{$trimestre}_fechado");
        if ($trimestreFechado === 'true' || $trimestreFechado === '1') {
            return false;
        }

        $dataInicio = static::get("lancamento_t{$trimestre}_inicio");
        $dataFim = static::get("lancamento_t{$trimestre}_fim");

        if (! $dataInicio && ! $dataFim) {
            return true;
        }

        $hoje = now()->format('Y-m-d');

        if ($dataInicio && ! $dataFim) {
            return $hoje >= $dataInicio;
        }

        if (! $dataInicio && $dataFim) {
            return $hoje <= $dataFim;
        }

        return $hoje >= $dataInicio && $hoje <= $dataFim;
    }

    public static function fecharTrimestre(int $trimestre): void
    {
        static::set("trimestre_{$trimestre}_fechado", 'true', "Trimestre {$trimestre} fechado para lançamento");
    }

    public static function abrirTrimestre(int $trimestre): void
    {
        static::set("trimestre_{$trimestre}_fechado", 'false', "Trimestre {$trimestre} aberto para lançamento");
    }

    public static function isAnoLectivoActual(string $anoLectivo): bool
    {
        return $anoLectivo === static::getAnoLectivoActual();
    }

    public static function podeEditarAnoLectivo(?string $anoLectivo = null, $user = null): bool
    {
        if ($anoLectivo === null) {
            $anoLectivo = static::getAnoLectivoActual();
        }

        if ($user === null) {
            $user = auth()->user();
        }

        if (! $user) {
            return false;
        }

        if ($user->hasRole('admin_full')) {
            return true;
        }

        return static::isAnoLectivoActual($anoLectivo);
    }

    public static function isModoConsulta(?string $anoLectivo = null, $user = null): bool
    {
        return ! static::podeEditarAnoLectivo($anoLectivo, $user);
    }

    public static function isModoConsultaById(?int $anoLectivoId = null, $user = null): bool
    {
        if ($anoLectivoId === null) {
            return false;
        }

        $anoActualId = static::getAnoLectivoActualId();

        if ($anoLectivoId === $anoActualId) {
            return false;
        }

        $user = $user ?? auth()->user();
        if ($user && $user->hasRole('admin_full')) {
            return false;
        }

        return true;
    }

    public static function podeEditarAnoLectivoById(?int $anoLectivoId = null, $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('admin_full')) {
            return true;
        }

        if ($anoLectivoId === null) {
            return true;
        }

        $anoActualId = static::getAnoLectivoActualId();

        return $anoLectivoId === $anoActualId;
    }

    public static function flushCache(): void
    {
        $chaves = static::query()->pluck('chave');
        foreach ($chaves as $chave) {
            Cache::forget(static::cacheKey($chave));
        }

        static::forgetResolvedCaches();
        static::$memo = [];
    }

    protected static function forgetResolvedCaches(): void
    {
        Cache::forget('config_resolved_ano_lectivo');
        Cache::forget('config_resolved_ano_lectivo_id');
        static::$anoMemo = null;
        static::$anoMemoSet = false;
        static::$anoIdMemo = null;
        static::$anoIdMemoSet = false;
        static::$trimestreMemo = null;
        static::$trimestreMemoSet = false;
    }

    protected static function cacheKey(string $chave): string
    {
        return 'config_'.$chave;
    }
}
