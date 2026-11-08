<?php

namespace App\Models\Pedagogico;

use App\Models\Pedagogico\Concerns\HasGeracaoIA;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedChaveCorrecao extends Model
{
    use HasGeracaoIA, SoftDeletes;

    protected $table = 'ped_chaves_correcao';

    protected $fillable = [
        'prova_id',
        'respostas_json',
        'criterios_json',
        'pontuacao_total',
        'geracao_ia_id',
    ];

    protected function casts(): array
    {
        return [
            'respostas_json' => 'array',
            'criterios_json' => 'array',
            'pontuacao_total' => 'decimal:2',
        ];
    }

    public function prova(): BelongsTo
    {
        return $this->belongsTo(PedProva::class, 'prova_id');
    }

    public function geracaoIA(): BelongsTo
    {
        return $this->belongsTo(PedGeracaoIA::class, 'geracao_ia_id');
    }
}
