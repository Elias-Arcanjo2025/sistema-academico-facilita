<?php

namespace App\Models\Control;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ControlLicenseEvent extends Model
{
    protected $table = 'control_license_events';

    protected $fillable = [
        'control_escola_id',
        'accao',
        'status_anterior',
        'status_novo',
        'motivo',
        'user_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(ControlEscola::class, 'control_escola_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
