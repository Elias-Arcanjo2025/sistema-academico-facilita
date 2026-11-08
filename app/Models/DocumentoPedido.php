<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoPedido extends Model
{
    protected $table = 'documento_pedidos';

    public const TIPO_FREQUENCIA = 'frequencia';
    public const TIPO_MATRICULA = 'matricula';
    public const TIPO_COMPROVATIVO = 'comprovativo';
    public const TIPO_FICHA = 'ficha';
    public const TIPO_CARTAO = 'cartao';

    public const ESTADO_PENDENTE = 'pendente';
    public const ESTADO_EM_PREPARACAO = 'em_preparacao';
    public const ESTADO_PRONTO = 'pronto';
    public const ESTADO_ENTREGUE = 'entregue';
    public const ESTADO_CANCELADO = 'cancelado';

    protected $fillable = [
        'aluno_id',
        'encarregado_id',
        'solicitado_por',
        'tipo',
        'estado',
        'observacao',
        'nota_secretaria',
        'atendido_por',
        'atendido_em',
    ];

    protected function casts(): array
    {
        return [
            'atendido_em' => 'datetime',
        ];
    }

    public static function tipos(): array
    {
        return [
            self::TIPO_FREQUENCIA => 'Declaração de frequência',
            self::TIPO_MATRICULA => 'Declaração de matrícula',
            self::TIPO_COMPROVATIVO => 'Comprovativo de inscrição',
            self::TIPO_FICHA => 'Ficha do aluno',
            self::TIPO_CARTAO => 'Cartão de estudante',
        ];
    }

    public static function estados(): array
    {
        return [
            self::ESTADO_PENDENTE => 'Pendente',
            self::ESTADO_EM_PREPARACAO => 'Em preparação',
            self::ESTADO_PRONTO => 'Pronto para levantar',
            self::ESTADO_ENTREGUE => 'Entregue',
            self::ESTADO_CANCELADO => 'Cancelado',
        ];
    }

    public function tipoLabel(): string
    {
        return self::tipos()[$this->tipo] ?? $this->tipo;
    }

    public function estadoLabel(): string
    {
        return self::estados()[$this->estado] ?? $this->estado;
    }

    public function podeCancelar(): bool
    {
        return in_array($this->estado, [self::ESTADO_PENDENTE, self::ESTADO_EM_PREPARACAO], true);
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function encarregado(): BelongsTo
    {
        return $this->belongsTo(Encarregado::class);
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function atendidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendido_por');
    }
}
