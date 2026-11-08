<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConformidadeRelatorio extends Model
{
    protected $table = 'conformidade_relatorios';

    public const SELO_CONFORME = 'conforme';
    public const SELO_ALERTA = 'alerta';
    public const SELO_NAO_CONFORME = 'nao_conforme';

    protected $fillable = [
        'turma_id',
        'ano_lectivo',
        'trimestre',
        'selo',
        'total_checks',
        'passed',
        'failed',
        'warnings',
        'resultado',
        'gerado_por',
    ];

    protected function casts(): array
    {
        return [
            'resultado' => 'array',
            'trimestre' => 'integer',
        ];
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function geradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gerado_por');
    }
}
