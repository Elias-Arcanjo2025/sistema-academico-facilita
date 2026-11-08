<?php

namespace App\Models;

use App\Services\RegimePauta\RegimeAdministracao;
use App\Services\RegimePauta\RegimePautaInterface;
use App\Services\RegimePauta\RegimePolitecnico;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Curso extends Model
{
    use HasFactory, LogsActivity;

    public const REGIME_POLITECNICO = 'politecnico';
    public const REGIME_ADMINISTRACAO = 'administracao';

    protected $fillable = [
        'codigo',
        'nome',
        'descricao',
        'classe_max',
        'regime_calculo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'classe_max' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['codigo', 'nome', 'classe_max', 'regime_calculo', 'activo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Devolve a instância do regime de cálculo associado a este curso.
     *
     * O regime decide:
     *  - Que colunas aparecem na pauta (T1/T2/T3) por classe.
     *  - Que tipos de Nota são usados.
     *  - As fórmulas (CF, MFT2 se aplicável, CA, etc.).
     *  - Se o resultado final é baseado em CFD ou CA.
     */
    public function regime(): RegimePautaInterface
    {
        return match ($this->regime_calculo) {
            self::REGIME_ADMINISTRACAO => app(RegimeAdministracao::class),
            default => app(RegimePolitecnico::class),
        };
    }

    /**
     * Lista legível de regimes disponíveis para apresentação na UI.
     */
    public static function regimesDisponiveis(): array
    {
        return [
            self::REGIME_POLITECNICO => 'Politécnico (Informática, Técnico) — com MFT2 e carry-overs',
            self::REGIME_ADMINISTRACAO => 'Administração (Contabilidade, Gestão) — trimestres independentes',
        ];
    }

    public function disciplinas(): BelongsToMany
    {
        return $this->belongsToMany(Disciplina::class, 'curso_disciplina')
            ->withPivot('classe_inicio', 'classe_fim', 'permite_pg', 'blindada')
            ->withTimestamps();
    }

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }

    public function coordenadores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'coordenador_curso', 'curso_id', 'user_id')
            ->withPivot(['ano_lectivo', 'activo'])
            ->withTimestamps();
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }
}
