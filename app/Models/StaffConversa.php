<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffConversa extends Model
{
    protected $table = 'staff_conversas';

    protected $fillable = [
        'user_low_id',
        'user_high_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function userLow(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_low_id');
    }

    public function userHigh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_high_id');
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(StaffMensagem::class, 'conversa_id');
    }

    public function outroParticipante(User $user): ?User
    {
        if ((int) $user->id === (int) $this->user_low_id) {
            return $this->relationLoaded('userHigh') ? $this->userHigh : $this->userHigh()->first();
        }

        if ((int) $user->id === (int) $this->user_high_id) {
            return $this->relationLoaded('userLow') ? $this->userLow : $this->userLow()->first();
        }

        return null;
    }

    public function incluiUser(User $user): bool
    {
        $id = (int) $user->id;

        return $id === (int) $this->user_low_id || $id === (int) $this->user_high_id;
    }
}
