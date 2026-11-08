<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sala extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo',
        'nome',
        'capacidade',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'capacidade' => 'integer',
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
