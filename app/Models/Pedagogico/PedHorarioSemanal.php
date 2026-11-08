<?php

namespace App\Models\Pedagogico;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PedHorarioSemanal extends Model
{
    use LogsActivity, SoftDeletes;

    protected $table = 'ped_horarios_semanais';

    protected $fillable = [
        'planificacao_id',
        'dia_semana',
        'hora_inicio',
        'hora_fim',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
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
}
