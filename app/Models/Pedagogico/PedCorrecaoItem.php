<?php

namespace App\Models\Pedagogico;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedCorrecaoItem extends Model
{
    protected $table = 'ped_correcao_itens';

    protected $fillable = [
        'correcao_id',
        'questao_ref',
        'resposta_extraida',
        'resposta_esperada',
        'confianca',
        'sugestao_pontos',
        'pontos_confirmados',
    ];

    protected function casts(): array
    {
        return [
            'sugestao_pontos' => 'decimal:2',
            'pontos_confirmados' => 'decimal:2',
        ];
    }

    public function correcao(): BelongsTo
    {
        return $this->belongsTo(PedCorrecao::class, 'correcao_id');
    }
}
