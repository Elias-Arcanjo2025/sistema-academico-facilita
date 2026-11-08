<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoletimAssinatura extends Model
{
    protected $table = 'boletim_assinaturas';

    protected $fillable = [
        'aluno_id',
        'encarregado_id',
        'ano_lectivo',
        'trimestre',
        'hash_documento',
        'ip',
        'user_agent',
        'assinado_em',
    ];

    protected function casts(): array
    {
        return [
            'assinado_em' => 'datetime',
            'trimestre' => 'integer',
        ];
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function encarregado(): BelongsTo
    {
        return $this->belongsTo(Encarregado::class);
    }
}
