<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PautaAprovacao extends Model
{
    public const STATUS_APROVADA = 'aprovada';

    public const STATUS_REABERTA = 'reaberta';

    protected $table = 'pauta_aprovacoes';

    protected $fillable = [
        'turma_id',
        'ano_lectivo_id',
        'trimestre',
        'status',
        'aprovado_por',
        'aprovado_em',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'trimestre' => 'integer',
            'aprovado_em' => 'datetime',
        ];
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }

    public function aprovador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovado_por');
    }

    public function estaAprovada(): bool
    {
        return $this->status === self::STATUS_APROVADA;
    }

    public static function para(int $turmaId, int $anoLectivoId, int $trimestre): ?self
    {
        return self::query()
            ->where('turma_id', $turmaId)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('trimestre', $trimestre)
            ->first();
    }
}
