<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistenteConversa extends Model
{
    protected $table = 'assistente_conversas';

    protected $fillable = [
        'user_id',
        'titulo',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(AssistenteMensagem::class, 'conversa_id')->orderBy('created_at');
    }
}
