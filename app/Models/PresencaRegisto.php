<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresencaRegisto extends Model
{
    public const PRESENTE = 'presente';

    public const FALTA_INJUSTIFICADA = 'falta_injustificada';

    public const FALTA_JUSTIFICADA = 'falta_justificada';

    public const ATRASO = 'atraso';

    protected $table = 'presenca_registos';

    protected $fillable = [
        'presenca_aula_id',
        'aluno_id',
        'estado',
        'justificacao',
        'justificado_por',
        'justificado_em',
    ];

    protected function casts(): array
    {
        return [
            'justificado_em' => 'datetime',
        ];
    }

    public static function estados(): array
    {
        return [
            self::PRESENTE => 'Presente',
            self::FALTA_INJUSTIFICADA => 'Falta injustificada',
            self::FALTA_JUSTIFICADA => 'Falta justificada',
            self::ATRASO => 'Atraso',
        ];
    }

    public function aula(): BelongsTo
    {
        return $this->belongsTo(PresencaAula::class, 'presenca_aula_id');
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function justificadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'justificado_por');
    }

    public function isFalta(): bool
    {
        return in_array($this->estado, [self::FALTA_INJUSTIFICADA, self::FALTA_JUSTIFICADA], true);
    }
}
