<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConteudoMinimo extends Model
{
    protected $table = 'conteudos_minimos';

    public const ESTADO_RASCUNHO = 'rascunho';

    public const ESTADO_PUBLICADO = 'publicado';

    public const ESTADO_ARQUIVADO = 'arquivado';

    protected $fillable = [
        'disciplina_id',
        'classe_id',
        'curso_id',
        'ano_lectivo_id',
        'trimestre',
        'titulo',
        'codigo_oficial',
        'descricao',
        'estado',
        'versao',
        'publicado_por',
        'publicado_em',
        'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'trimestre' => 'integer',
            'versao' => 'integer',
            'publicado_em' => 'datetime',
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

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function publicador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ConteudoMinimoItem::class)->orderBy('ordem');
    }

    public function itensRaiz(): HasMany
    {
        return $this->hasMany(ConteudoMinimoItem::class)
            ->whereNull('parent_id')
            ->orderBy('ordem');
    }

    public function scopePublicados($query)
    {
        return $query->where('estado', self::ESTADO_PUBLICADO);
    }

    public function scopeRascunhos($query)
    {
        return $query->where('estado', self::ESTADO_RASCUNHO);
    }

    public function isRascunho(): bool
    {
        return $this->estado === self::ESTADO_RASCUNHO;
    }

    public function isPublicado(): bool
    {
        return $this->estado === self::ESTADO_PUBLICADO;
    }
}
