<?php

namespace App\Models\Pedagogico;

use App\Models\Pedagogico\Concerns\HasGeracaoIA;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedMatrizProva extends Model
{
    use HasGeracaoIA, SoftDeletes;

    protected $table = 'ped_matrizes_prova';

    protected $fillable = [
        'prova_id',
        'linhas_json',
        'geracao_ia_id',
    ];

    protected function casts(): array
    {
        return ['linhas_json' => 'array'];
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
