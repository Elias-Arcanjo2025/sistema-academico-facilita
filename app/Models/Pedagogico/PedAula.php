<?php

namespace App\Models\Pedagogico;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PedAula extends Model
{
    use LogsActivity, SoftDeletes;

    public const ESTADO_PLANEADA = 'planeada';
    public const ESTADO_MINISTRADA = 'ministrada';
    public const ESTADO_ADIADA = 'adiada';
    public const ESTADO_CANCELADA = 'cancelada';

    protected $table = 'ped_aulas';

    protected $fillable = [
        'planificacao_id',
        'quinzena_id',
        'data',
        'hora_inicio',
        'hora_fim',
        'tempos',
        'tema_planeado',
        'objetivos_geral',
        'objetivos_especificos',
        'tema_ministrado',
        'conteudo_ministrado',
        'observacoes',
        'estado',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'ordem' => 'integer',
            'tempos' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function planificacao(): BelongsTo
    {
        return $this->belongsTo(PedPlanificacao::class, 'planificacao_id');
    }

    public function quinzena(): BelongsTo
    {
        return $this->belongsTo(PedQuinzena::class, 'quinzena_id');
    }
}
