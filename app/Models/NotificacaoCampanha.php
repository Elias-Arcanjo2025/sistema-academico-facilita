<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificacaoCampanha extends Model
{
    protected $table = 'notificacao_campanhas';

    public const PUBLICO_PROFESSORES = 'professores';

    public const PUBLICO_DIRECTORES = 'directores_turma';

    public const PUBLICO_STAFF = 'staff';

    public const PUBLICO_ENCARREGADOS = 'encarregados';

    public const PUBLICO_ALUNOS = 'alunos';

    public const PUBLICO_TURMA = 'turma';

    protected $fillable = [
        'titulo',
        'mensagem',
        'publico',
        'turma_id',
        'enviados',
        'destinatario_ids',
        'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'destinatario_ids' => 'array',
            'enviados' => 'integer',
        ];
    }

    public static function publicos(): array
    {
        return [
            self::PUBLICO_PROFESSORES => 'Professores',
            self::PUBLICO_DIRECTORES => 'Directores de turma',
            self::PUBLICO_STAFF => 'Todo o staff',
            self::PUBLICO_ENCARREGADOS => 'Encarregados (portal)',
            self::PUBLICO_ALUNOS => 'Alunos (portal)',
            self::PUBLICO_TURMA => 'Turma (alunos + encarregados + DT)',
        ];
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}
