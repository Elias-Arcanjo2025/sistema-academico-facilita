<?php

namespace App\Models\Pedagogico;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PedQuinzena extends Model
{
    use LogsActivity, SoftDeletes;

    protected $table = 'ped_quinzenas';

    protected $fillable = [
        'planificacao_id',
        'numero',
        'data_inicial',
        'data_final',
        'tema',
        'subtemas',
        'competencias',
        'objetivos',
        'objetivos_geral',
        'objetivos_especificos',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'data_inicial' => 'date',
            'data_final' => 'date',
            'subtemas' => 'array',
            'competencias' => 'array',
            'objetivos' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function planificacao(): BelongsTo
    {
        return $this->belongsTo(PedPlanificacao::class, 'planificacao_id');
    }

    public function aulas(): HasMany
    {
        return $this->hasMany(PedAula::class, 'quinzena_id');
    }

    public function dias(): HasMany
    {
        return $this->hasMany(PedQuinzenaDia::class, 'quinzena_id')->orderBy('data')->orderBy('ordem');
    }

    public function temas(): HasMany
    {
        return $this->hasMany(PedQuinzenaTema::class, 'quinzena_id')->orderBy('ordem');
    }

    /**
     * @return array<int, string>
     */
    public function listaTemas(): array
    {
        if ($this->relationLoaded('temas') && $this->temas->isNotEmpty()) {
            return $this->temas->map(fn (PedQuinzenaTema $t) => $t->rotulo())->all();
        }

        if (! empty($this->subtemas) && is_array($this->subtemas)) {
            return collect($this->subtemas)->map(function ($item) {
                if (is_array($item)) {
                    return trim(($item['codigo'] ?? '').' '.($item['titulo'] ?? $item['nome'] ?? ''));
                }

                return (string) $item;
            })->filter()->values()->all();
        }

        return $this->tema ? [$this->tema] : [];
    }

    public function resumoTemas(): string
    {
        return implode('; ', $this->listaTemas());
    }
}
