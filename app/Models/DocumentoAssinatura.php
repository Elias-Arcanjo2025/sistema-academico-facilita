<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoAssinatura extends Model
{
    public const CARGO_DIRECTOR_TURMA = 'director_turma';

    public const CARGO_COORDENADOR_CURSO = 'coordenador_curso';

    public const CARGO_SECRETARIA = 'secretaria';

    public const CARGO_DIRECTORA = 'directora_geral';

    public const CARGO_DIRECTOR_PEDAGOGICO = 'subdiretor_pedagogico';

    protected $table = 'documento_assinaturas';

    protected $fillable = [
        'documento_tipo',
        'documento_chave',
        'user_id',
        'cargo',
        'hash_documento',
        'ip',
        'user_agent',
        'assinado_em',
    ];

    protected function casts(): array
    {
        return [
            'assinado_em' => 'datetime',
        ];
    }

    public static function cargos(): array
    {
        return [
            self::CARGO_DIRECTOR_TURMA => 'Director de Turma',
            self::CARGO_COORDENADOR_CURSO => 'Coordenador de Curso',
            self::CARGO_SECRETARIA => 'Secretaria',
            self::CARGO_DIRECTORA => 'Directora Geral',
            self::CARGO_DIRECTOR_PEDAGOGICO => 'Director Pedagógico',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
