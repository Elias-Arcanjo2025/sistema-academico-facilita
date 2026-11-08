<?php

namespace App\Models\Control;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ControlAluno extends Model
{
    protected $table = 'control_alunos';

    public const PAGAMENTO_PENDENTE = 'pendente';
    public const PAGAMENTO_PAGO = 'pago';
    public const PAGAMENTO_ISENTO = 'isento';
    public const PAGAMENTO_ANULADO = 'anulado';

    protected $fillable = [
        'control_escola_id',
        'aluno_uuid',
        'aluno_local_id',
        'numero_processo',
        'nome',
        'genero',
        'ano_lectivo',
        'turma_codigo',
        'turma_nome',
        'curso_nome',
        'classe_nome',
        'situacao_academica',
        'activo',
        'pagamento_estado',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(ControlEscola::class, 'control_escola_id');
    }
}
