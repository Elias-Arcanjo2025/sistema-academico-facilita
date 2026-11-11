<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlunoAvaliacaoDiaria extends Model
{
    protected $table = 'aluno_avaliacoes_diarias';

    protected $fillable = [
        'aluno_id',
        'turma_id',
        'disciplina_id',
        'professor_id',
        'data',
        'participacao',
        'empenho',
        'comportamento',
        'realizacao_actividades',
        'compreensao',
        'observacao_geral',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'participacao' => 'decimal:2',
            'empenho' => 'decimal:2',
            'comportamento' => 'decimal:2',
            'realizacao_actividades' => 'decimal:2',
            'compreensao' => 'decimal:2',
        ];
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
