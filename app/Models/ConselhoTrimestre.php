<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ConselhoTrimestre extends Model
{
    use LogsActivity;

    const ESTADO_PENDENTE = 'pendente';
    const ESTADO_EM_CURSO = 'em_curso';
    const ESTADO_CONCLUIDO = 'concluido';

    protected $table = 'conselhos_trimestre';

    protected $fillable = [
        'turma_id',
        'trimestre',
        'ano_lectivo_id',
        'ano_lectivo',
        'estado',
        'data_inicio',
        'data_conclusao',
        'iniciado_por',
        'concluido_por',
        'observacoes',
    ];

    protected $casts = [
        'trimestre' => 'integer',
        'data_inicio' => 'datetime',
        'data_conclusao' => 'datetime',
    ];

    // ============================================
    // Relacionamentos
    // ============================================

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }

    public function iniciadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iniciado_por');
    }

    public function concluidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concluido_por');
    }

    public function ajustes(): HasMany
    {
        return $this->hasMany(AjusteConselho::class, 'conselho_id');
    }

    // ============================================
    // Métodos de Status
    // ============================================

    public function isPendente(): bool
    {
        return $this->estado === self::ESTADO_PENDENTE;
    }

    public function isEmCurso(): bool
    {
        return $this->estado === self::ESTADO_EM_CURSO;
    }

    public function isConcluido(): bool
    {
        return $this->estado === self::ESTADO_CONCLUIDO;
    }

    public function podeIniciar(): bool
    {
        return $this->isPendente();
    }

    public function podeAjustar(): bool
    {
        return $this->isEmCurso();
    }

    public function podeConcluir(): bool
    {
        return $this->isEmCurso();
    }

    // ============================================
    // Scopes
    // ============================================

    public function scopePendente($query)
    {
        return $query->where('estado', self::ESTADO_PENDENTE);
    }

    public function scopeEmCurso($query)
    {
        return $query->where('estado', self::ESTADO_EM_CURSO);
    }

    public function scopeConcluido($query)
    {
        return $query->where('estado', self::ESTADO_CONCLUIDO);
    }

    // ============================================
    // Activity Logging
    // ============================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['turma_id', 'trimestre', 'estado', 'data_inicio', 'data_conclusao'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
