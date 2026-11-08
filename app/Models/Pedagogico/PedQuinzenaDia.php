<?php

namespace App\Models\Pedagogico;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedQuinzenaDia extends Model
{
    protected $table = 'ped_quinzena_dias';

    protected $fillable = [
        'quinzena_id',
        'data',
        'tema',
        'objetivos_geral',
        'objetivos_especificos',
        'tempos',
        'hora_inicio',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'tempos' => 'integer',
            'ordem' => 'integer',
        ];
    }

    public function quinzena(): BelongsTo
    {
        return $this->belongsTo(PedQuinzena::class, 'quinzena_id');
    }

    public function duracaoMinutos(): int
    {
        return (int) $this->tempos * (int) config('pedagogico.minutos_por_tempo', 45);
    }
}
