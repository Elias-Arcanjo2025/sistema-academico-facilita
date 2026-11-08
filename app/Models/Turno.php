<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turno extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo',
        'nome',
        'hora_inicio',
        'hora_fim',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'hora_inicio' => 'datetime:H:i',
            'hora_fim' => 'datetime:H:i',
            'activo' => 'boolean',
        ];
    }

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }
}
