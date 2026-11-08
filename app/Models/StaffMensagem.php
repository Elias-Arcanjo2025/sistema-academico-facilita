<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffMensagem extends Model
{
    protected $table = 'staff_mensagens';

    protected $fillable = [
        'conversa_id',
        'user_id',
        'corpo',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function conversa(): BelongsTo
    {
        return $this->belongsTo(StaffConversa::class, 'conversa_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
