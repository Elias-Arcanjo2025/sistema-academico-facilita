<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PortalConversa extends Model
{
    protected $table = 'portal_conversas';

    protected $fillable = [
        'aluno_id',
        'encarregado_user_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function encarregadoUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encarregado_user_id');
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(PortalMensagem::class, 'conversa_id');
    }
}
