<?php

namespace App\Models;

use App\Support\ConselhoAjusteRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class AjusteConselho extends Model
{
    use LogsActivity;

    protected $table = 'ajustes_conselho';

    protected $fillable = [
        'conselho_id',
        'nota_pre_id',
        'nota_pos_id',
        'valor_anterior',
        'valor_novo',
        'justificacao',
        'motivo_reducao',
        'ajustado_por',
    ];

    protected $casts = [
        'valor_anterior' => 'decimal:2',
        'valor_novo' => 'decimal:2',
    ];

    // ============================================
    // Relacionamentos
    // ============================================

    public function conselho(): BelongsTo
    {
        return $this->belongsTo(ConselhoTrimestre::class, 'conselho_id');
    }

    public function notaPre(): BelongsTo
    {
        return $this->belongsTo(Nota::class, 'nota_pre_id');
    }

    public function notaPos(): BelongsTo
    {
        return $this->belongsTo(Nota::class, 'nota_pos_id');
    }

    public function ajustadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ajustado_por');
    }

    // ============================================
    // Métodos Helper
    // ============================================

    /**
     * Calcula a diferença entre valor novo e valor anterior
     */
    public function getDiferenca(): float
    {
        return round($this->valor_novo - $this->valor_anterior, 2);
    }

    /**
     * Verifica se a nota foi aumentada
     */
    public function foiAumentada(): bool
    {
        return $this->valor_novo > $this->valor_anterior;
    }

    /**
     * Verifica se a nota foi diminuída
     */
    public function foiDiminuida(): bool
    {
        return $this->valor_novo < $this->valor_anterior;
    }

    /**
     * Retorna a cor do badge baseado no tipo de ajuste
     */
    public function getBadgeColor(): string
    {
        if ($this->foiAumentada()) {
            return 'success'; // Verde para aumentos
        } elseif ($this->foiDiminuida()) {
            return 'danger'; // Vermelho para diminuições
        }
        return 'secondary'; // Cinza se for igual (improvável)
    }

    /**
     * Retorna ícone para o tipo de ajuste
     */
    public function getIcone(): string
    {
        if ($this->foiAumentada()) {
            return '↑';
        } elseif ($this->foiDiminuida()) {
            return '↓';
        }

        return '=';
    }

    public function getMotivoReducaoRotulo(): ?string
    {
        return ConselhoAjusteRules::rotuloMotivoReducao($this->motivo_reducao);
    }

    public function isCorrecaoExcepcional(): bool
    {
        return $this->foiDiminuida() && filled($this->motivo_reducao);
    }

    // ============================================
    // Activity Logging
    // ============================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['conselho_id', 'nota_pre_id', 'nota_pos_id', 'valor_anterior', 'valor_novo', 'justificacao', 'motivo_reducao'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
