<?php

namespace App\Models\Pedagogico;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PedGeracaoIA extends Model
{
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'ped_geracoes_ia';

    protected $fillable = [
        'tipo',
        'user_id',
        'provider',
        'model',
        'tokens_in',
        'tokens_out',
        'custo_estimado',
        'request_hash',
        'request_json',
        'response_json',
        'status',
        'error_message',
        'duracao_ms',
        'geravel_type',
        'geravel_id',
    ];

    protected function casts(): array
    {
        return [
            'request_json' => 'array',
            'response_json' => 'array',
            'custo_estimado' => 'decimal:6',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function geravel(): MorphTo
    {
        return $this->morphTo();
    }
}
