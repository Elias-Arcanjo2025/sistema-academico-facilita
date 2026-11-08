<?php

namespace App\Models\Control;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ControlCobranca extends Model
{
    protected $table = 'control_cobrancas';

    public const STATUS_ABERTA = 'aberta';
    public const STATUS_PAGA = 'paga';
    public const STATUS_CANCELADA = 'cancelada';
    public const STATUS_VENCIDA = 'vencida';

    protected $fillable = [
        'control_escola_id',
        'referencia',
        'ano_lectivo',
        'total_alunos',
        'preco_unitario',
        'valor_total',
        'status',
        'vencimento_em',
        'minutos_ate_aviso',
        'minutos_ate_atraso',
        'minutos_ate_bloqueio',
        'aviso_em',
        'atraso_em',
        'bloqueio_em',
        'pago_em',
        'metodo_pagamento',
        'referencia_pagamento',
        'notas',
        'aviso_escola',
        'criado_por',
        'pago_por',
    ];

    protected function casts(): array
    {
        return [
            'preco_unitario' => 'decimal:2',
            'valor_total' => 'decimal:2',
            'vencimento_em' => 'date',
            'minutos_ate_aviso' => 'integer',
            'minutos_ate_atraso' => 'integer',
            'minutos_ate_bloqueio' => 'integer',
            'aviso_em' => 'datetime',
            'atraso_em' => 'datetime',
            'bloqueio_em' => 'datetime',
            'pago_em' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $cobranca) {
            if (empty($cobranca->referencia)) {
                $cobranca->referencia = 'FC-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
            }
        });
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(ControlEscola::class, 'control_escola_id');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function pagoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pago_por');
    }

    public function isAberta(): bool
    {
        return $this->status === self::STATUS_ABERTA
            || $this->status === self::STATUS_VENCIDA;
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_ABERTA => 'Aberta',
            self::STATUS_PAGA => 'Paga',
            self::STATUS_CANCELADA => 'Cancelada',
            self::STATUS_VENCIDA => 'Vencida',
        ];
    }
}
