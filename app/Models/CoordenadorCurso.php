<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoordenadorCurso extends Model
{
    use HasFactory;

    protected $table = 'coordenador_curso';

    protected $fillable = [
        'user_id',
        'curso_id',
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

    /**
     * Mutator: Sincronizar ano_lectivo quando ano_lectivo_id é definido
     */
    protected function anoLectivoId(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                // Quando ID é definido, preencher string automaticamente
                if ($value && !isset($this->attributes['ano_lectivo'])) {
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

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeAnoLectivo($query, $ano)
    {
        return $query->where('ano_lectivo', $ano);
    }
}
