<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlunoPresenca extends Model
{
    public const ESTADOS = [
        'presente' => 'Presente',
        'ausente' => 'Ausente',
        'atrasado' => 'Atrasado',
        'justificado' => 'Falta justificada',
    ];

    protected $table = 'aluno_presencas';

    protected $fillable = [
        'aluno_id',
        'turma_id',
        'disciplina_id',
        'professor_id',
        'data',
        'estado',
        'observacao',
    ];

    protected function casts(): array
    {
        return ['data' => 'date'];
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
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
}
