<?php

namespace App\Models\Pedagogico;

use App\Models\Pedagogico\Concerns\HasGeracaoIA;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PedPlanoAula extends Model
{
    use HasGeracaoIA, LogsActivity, SoftDeletes;

    public const ESTADO_RASCUNHO = 'rascunho';
    public const ESTADO_APROVADO = 'aprovado';
    public const ESTADO_DADO = 'dado';

    protected $table = 'ped_planos_aula';

    protected $fillable = [
        'planificacao_id',
        'quinzena_id',
        'aula_id',
        'tempos',
        'conteudo_json',
        'versao',
        'estado',
        'ministrado_em',
        'geracao_ia_id',
    ];

    protected function casts(): array
    {
        return [
            'conteudo_json' => 'array',
            'versao' => 'integer',
            'tempos' => 'integer',
            'ministrado_em' => 'datetime',
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

    public function aula(): BelongsTo
    {
        return $this->belongsTo(PedAula::class, 'aula_id');
    }

    public function geracaoIA(): BelongsTo
    {
        return $this->belongsTo(PedGeracaoIA::class, 'geracao_ia_id');
    }
}
