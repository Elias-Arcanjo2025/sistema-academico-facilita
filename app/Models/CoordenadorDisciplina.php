<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CoordenadorDisciplina extends Model
{
    protected $table = 'coordenador_disciplina';

    protected $fillable = [
        'user_id',
        'disciplina_id',
        'ano_lectivo',
        'ano_lectivo_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'ano_lectivo' => 'string',
            'activo' => 'boolean',
        ];
    }

    protected function anoLectivoId(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                if ($value && ! isset($this->attributes['ano_lectivo'])) {
                    $this->attributes['ano_lectivo'] = AnoLectivo::getNomeById($value);
                }

                return $value;
            }
        );
    }

    public function coordenador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'coordenador_disciplina_classe')
            ->withTimestamps();
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeParaDisciplinaClasse($query, int $disciplinaId, ?int $classeId = null, ?int $anoLectivoId = null)
    {
        return $query
            ->where('disciplina_id', $disciplinaId)
            ->where('activo', true)
            ->when($anoLectivoId, fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
            ->when($classeId, fn ($q) => $q->whereHas('classes', fn ($c) => $c->where('classes.id', $classeId)));
    }
}
