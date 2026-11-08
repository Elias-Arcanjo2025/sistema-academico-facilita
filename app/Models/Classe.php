<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Classe extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'codigo',
        'nome',
        'ordem',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['codigo', 'nome', 'ordem', 'activo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Relação many-to-many com disciplinas
     */
    public function disciplinas(): BelongsToMany
    {
        return $this->belongsToMany(Disciplina::class, 'classe_disciplina')
            ->withTimestamps();
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeOrdenado($query)
    {
        return $query->orderBy('ordem');
    }

    /**
     * Classes activas e permitidas pelo perfil de ensino da instalação.
     */
    public function scopePermitidas($query)
    {
        $ordens = \App\Support\EnsinoPerfil::classesPermitidas();

        return $query->where('activo', true)
            ->whereIn('ordem', $ordens)
            ->orderBy('ordem');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public static function queryPermitidas()
    {
        return static::query()->permitidas();
    }
}
