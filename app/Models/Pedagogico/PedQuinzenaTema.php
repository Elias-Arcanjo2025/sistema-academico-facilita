<?php

namespace App\Models\Pedagogico;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedQuinzenaTema extends Model
{
    protected $table = 'ped_quinzena_temas';

    protected $fillable = [
        'quinzena_id',
        'codigo',
        'titulo',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
        ];
    }

    public function quinzena(): BelongsTo
    {
        return $this->belongsTo(PedQuinzena::class, 'quinzena_id');
    }

    public function rotulo(): string
    {
        return trim(($this->codigo ? $this->codigo.' ' : '').$this->titulo);
    }
}
