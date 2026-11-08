<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatriculaHistorico extends Model
{
    protected $table = 'matricula_historicos';

    public const TIPO_MATRICULA = 'matricula';
    public const TIPO_RENOVACAO = 'renovacao';
    public const TIPO_TRANSFERENCIA = 'transferencia';
    public const TIPO_MUDANCA_CURSO = 'mudanca_curso';
    public const TIPO_ARQUIVO = 'arquivo';

    protected $fillable = [
        'aluno_id',
        'ano_lectivo_id',
        'turma_id',
        'tipo',
        'de_turma_id',
        'para_turma_id',
        'motivo',
        'dados',
        'registado_por',
    ];

    protected function casts(): array
    {
        return [
            'dados' => 'array',
        ];
    }

    public static function tipos(): array
    {
        return [
            self::TIPO_MATRICULA => 'Matrícula',
            self::TIPO_RENOVACAO => 'Renovação',
            self::TIPO_TRANSFERENCIA => 'Transferência',
            self::TIPO_MUDANCA_CURSO => 'Mudança de curso',
            self::TIPO_ARQUIVO => 'Arquivo',
        ];
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class, 'ano_lectivo_id');
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function deTurma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'de_turma_id');
    }

    public function paraTurma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'para_turma_id');
    }

    public function registadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registado_por');
    }
}
