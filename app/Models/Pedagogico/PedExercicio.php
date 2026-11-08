<?php

namespace App\Models\Pedagogico;

use App\Models\Pedagogico\Concerns\HasGeracaoIA;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PedExercicio extends Model
{
    use HasGeracaoIA, LogsActivity, SoftDeletes;

    protected $table = 'ped_exercicios';

    protected $fillable = [
        'planificacao_id',
        'quinzena_id',
        'tipo',
        'dificuldade',
        'conteudo_json',
        'geracao_ia_id',
    ];

    protected function casts(): array
    {
        return ['conteudo_json' => 'array'];
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

    public function geracaoIA(): BelongsTo
    {
        return $this->belongsTo(PedGeracaoIA::class, 'geracao_ia_id');
    }
}
