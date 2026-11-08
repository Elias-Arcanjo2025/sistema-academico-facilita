<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialAcademico extends Model
{
    use SoftDeletes;

    protected $table = 'materiais_academicos';

    protected $fillable = [
        'titulo',
        'descricao',
        'ficheiro_path',
        'mime',
        'tamanho',
        'disciplina_id',
        'turma_id',
        'ano_lectivo_id',
        'publicado',
        'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'tamanho' => 'integer',
            'publicado' => 'boolean',
        ];
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function scopePublicados($query)
    {
        return $query->where('publicado', true);
    }
}
