<?php

namespace App\Models\Pedagogico;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedExecucaoAnalise extends Model
{
    protected $table = 'ped_execucao_analises';

    protected $fillable = [
        'planificacao_id',
        'percentagem_execucao',
        'conteudos_atraso_json',
        'conteudos_adiantados_json',
        'previsao_conclusao',
        'recomendacoes_json',
        'geracao_ia_id',
        'calculado_em',
    ];

    protected function casts(): array
    {
        return [
            'percentagem_execucao' => 'decimal:2',
            'conteudos_atraso_json' => 'array',
            'conteudos_adiantados_json' => 'array',
            'previsao_conclusao' => 'date',
            'recomendacoes_json' => 'array',
            'calculado_em' => 'datetime',
        ];
    }

    public function planificacao(): BelongsTo
    {
        return $this->belongsTo(PedPlanificacao::class, 'planificacao_id');
    }

    public function geracaoIA(): BelongsTo
    {
        return $this->belongsTo(PedGeracaoIA::class, 'geracao_ia_id');
    }
}
