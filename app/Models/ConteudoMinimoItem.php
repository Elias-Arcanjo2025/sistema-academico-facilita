<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConteudoMinimoItem extends Model
{
    protected $table = 'conteudo_minimo_itens';

    protected $fillable = [
        'conteudo_minimo_id',
        'parent_id',
        'codigo',
        'titulo',
        'descricao',
        'ordem',
        'obrigatorio',
        'carga_horaria_sugerida',
        'trimestre_sugerido',
    ];

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'obrigatorio' => 'boolean',
            'carga_horaria_sugerida' => 'integer',
            'trimestre_sugerido' => 'integer',
        ];
    }

    public function conteudoMinimo(): BelongsTo
    {
        return $this->belongsTo(ConteudoMinimo::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function filhos(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('ordem');
    }
}
