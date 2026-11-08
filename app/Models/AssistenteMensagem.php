<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistenteMensagem extends Model
{
    protected $table = 'assistente_mensagens';

    protected $fillable = [
        'conversa_id',
        'papel',
        'conteudo',
        'contexto_usado',
        'tokens_in',
        'tokens_out',
        'feedback',
        'feedback_at',
    ];

    protected function casts(): array
    {
        return [
            'contexto_usado' => 'array',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'feedback_at' => 'datetime',
        ];
    }

    public function conversa(): BelongsTo
    {
        return $this->belongsTo(AssistenteConversa::class, 'conversa_id');
    }
}
