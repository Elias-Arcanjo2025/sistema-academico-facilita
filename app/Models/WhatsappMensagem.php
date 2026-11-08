<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMensagem extends Model
{
    protected $table = 'whatsapp_mensagens';

    protected $fillable = [
        'direction',
        'telefone',
        'encarregado_id',
        'tipo',
        'template',
        'corpo',
        'provider_message_id',
        'status',
        'payload',
        'erro',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function encarregado(): BelongsTo
    {
        return $this->belongsTo(Encarregado::class);
    }
}
