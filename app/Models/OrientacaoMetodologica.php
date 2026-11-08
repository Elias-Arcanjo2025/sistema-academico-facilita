<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrientacaoMetodologica extends Model
{
    protected $table = 'orientacoes_metodologicas';

    public const ESTADO_RASCUNHO = 'rascunho';

    public const ESTADO_PUBLICADO = 'publicado';

    public const ESTADO_ARQUIVADO = 'arquivado';

    protected $fillable = [
        'disciplina_id',
        'classe_id',
        'ano_lectivo_id',
        'trimestre',
        'titulo',
        'conteudo',
        'estado',
        'criado_por',
        'publicado_por',
        'publicado_em',
    ];

    protected function casts(): array
    {
        return [
            'publicado_em' => 'datetime',
            'trimestre' => 'integer',
        ];
    }

    public static function estados(): array
    {
        return [
            self::ESTADO_RASCUNHO => 'Rascunho',
            self::ESTADO_PUBLICADO => 'Publicado',
            self::ESTADO_ARQUIVADO => 'Arquivado',
        ];
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function publicador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por');
    }

    public function isPublicado(): bool
    {
        return $this->estado === self::ESTADO_PUBLICADO;
    }
}
