<?php

namespace App\Models\Pedagogico;

use App\Models\Disciplina;
use App\Models\Pedagogico\Concerns\HasGeracaoIA;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PedProva extends Model
{
    use HasGeracaoIA, LogsActivity, SoftDeletes;

    public const ESTADO_RASCUNHO = 'rascunho';

    public const ESTADO_PENDENTE = 'pendente_aprovacao';

    public const ESTADO_APROVADA = 'aprovada';

    public const ESTADO_PUBLICADA = 'publicada';

    protected $table = 'ped_provas';

    protected $fillable = [
        'tipo',
        'planificacao_id',
        'turma_id',
        'disciplina_id',
        'config_json',
        'conteudo_json',
        'tempo_minutos',
        'cotacao_total',
        'estado',
        'geracao_ia_id',
        'submetido_em',
        'aprovado_por',
        'aprovado_em',
        'motivo_rejeicao',
    ];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
            'conteudo_json' => 'array',
            'cotacao_total' => 'decimal:2',
            'submetido_em' => 'datetime',
            'aprovado_em' => 'datetime',
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

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function aprovadoPor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'aprovado_por');
    }

    public function matriz(): HasOne
    {
        return $this->hasOne(PedMatrizProva::class, 'prova_id');
    }

    public function chaveCorrecao(): HasOne
    {
        return $this->hasOne(PedChaveCorrecao::class, 'prova_id');
    }

    public function correcoes(): HasMany
    {
        return $this->hasMany(PedCorrecao::class, 'prova_id');
    }

    public function geracaoIA(): BelongsTo
    {
        return $this->belongsTo(PedGeracaoIA::class, 'geracao_ia_id');
    }
}
