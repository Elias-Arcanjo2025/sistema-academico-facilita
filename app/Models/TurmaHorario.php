<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurmaHorario extends Model
{
    protected $fillable = [
        'turma_id',
        'disciplina_id',
        'professor_id',
        'sala_id',
        'ano_lectivo_id',
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

    public static function diasSemana(): array
    {
        return [
            1 => 'Segunda-feira',
            2 => 'Terça-feira',
            3 => 'Quarta-feira',
            4 => 'Quinta-feira',
            5 => 'Sexta-feira',
            6 => 'Sábado',
        ];
    }

    public function diaSemanaNome(): string
    {
        return self::diasSemana()[$this->dia_semana] ?? '-';
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

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }
}
