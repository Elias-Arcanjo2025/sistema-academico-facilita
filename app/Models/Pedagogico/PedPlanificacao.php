<?php

namespace App\Models\Pedagogico;

use App\Models\AnoLectivo;
use App\Models\Disciplina;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PedPlanificacao extends Model
{
    use LogsActivity, SoftDeletes;

    public const ESTADO_RASCUNHO = 'rascunho';
    public const ESTADO_PENDENTE = 'pendente_aprovacao';
    public const ESTADO_APROVADA = 'aprovada';
    public const ESTADO_ACTIVO = 'activo';
    public const ESTADO_CONCLUIDO = 'concluido';

    public const TIPO_QUINZENAL = 'quinzenal';
    public const TIPO_MENSAL = 'mensal';

    protected $table = 'ped_planificacoes';

    protected $fillable = [
        'ano_lectivo_id',
        'trimestre',
        'turma_id',
        'disciplina_id',
        'user_id',
        'data_inicial',
        'data_final',
        'estado',
        'tipo_periodo',
        'submetido_em',
        'aprovado_por',
        'aprovado_em',
        'motivo_rejeicao',
    ];

    protected function casts(): array
    {
        return [
            'trimestre' => 'integer',
            'data_inicial' => 'date',
            'data_final' => 'date',
            'submetido_em' => 'datetime',
            'aprovado_em' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function aprovadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovado_por');
    }

    public function quinzenas(): HasMany
    {
        return $this->hasMany(PedQuinzena::class, 'planificacao_id')->orderBy('numero');
    }

    public function horariosSemanais(): HasMany
    {
        return $this->hasMany(PedHorarioSemanal::class, 'planificacao_id');
    }

    public function aulas(): HasMany
    {
        return $this->hasMany(PedAula::class, 'planificacao_id')->orderBy('data')->orderBy('ordem');
    }

    public function planosAula(): HasMany
    {
        return $this->hasMany(PedPlanoAula::class, 'planificacao_id');
    }

    public function exercicios(): HasMany
    {
        return $this->hasMany(PedExercicio::class, 'planificacao_id');
    }

    public function provas(): HasMany
    {
        return $this->hasMany(PedProva::class, 'planificacao_id');
    }

    public function execucaoAnalises(): HasMany
    {
        return $this->hasMany(PedExecucaoAnalise::class, 'planificacao_id')->latest('calculado_em');
    }
}
