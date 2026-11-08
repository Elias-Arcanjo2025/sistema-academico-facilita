<?php

namespace App\Models\Control;

use Illuminate\Database\Eloquent\Model;

class ControlLicenseState extends Model
{
    protected $table = 'control_license_states';

    public const STATUS_ACTIVO = 'activo';
    public const STATUS_EM_COBRANCA = 'em_cobranca';
    public const STATUS_EM_ATRASO = 'em_atraso';
    public const STATUS_BLOQUEADO = 'bloqueado';
    public const STATUS_DESCONHECIDO = 'desconhecido';

    protected $fillable = [
        'status',
        'message',
        'checked_at',
        'hub_reported_at',
        'blocked_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'hub_reported_at' => 'datetime',
            'blocked_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'status' => self::STATUS_ACTIVO,
            'message' => null,
        ]);
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOQUEADO;
    }
}
