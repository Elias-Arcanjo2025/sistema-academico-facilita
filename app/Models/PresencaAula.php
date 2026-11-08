<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresencaAula extends Model
{
    protected $table = 'presenca_aulas';

    protected $fillable = [
        'turma_id',
        'disciplina_id',
        'professor_id',
        'turma_horario_id',
        'ano_lectivo_id',
        'data',
        'hora_inicio',
        'hora_fim',
        'trimestre',
        'registado_por',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'trimestre' => 'integer',
        ];
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
        return $this->belongsTo(User::class, 'professor_id');
    }

    public function turmaHorario(): BelongsTo
    {
        return $this->belongsTo(TurmaHorario::class, 'turma_horario_id');
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class, 'ano_lectivo_id');
    }

    public function registadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registado_por');
    }

    public function registos(): HasMany
    {
        return $this->hasMany(PresencaRegisto::class, 'presenca_aula_id');
    }
}
