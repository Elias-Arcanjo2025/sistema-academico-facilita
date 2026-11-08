<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendaEvento extends Model
{
    protected $table = 'agenda_eventos';

    public const TIPO_FERIADO = 'feriado';

    public const TIPO_REUNIAO = 'reuniao';

    public const TIPO_ACTIVIDADE = 'actividade';

    public const TIPO_AVISO = 'aviso';

    public const TIPO_EXAME = 'exame';

    public const TIPO_CONSELHO = 'conselho';

    public const TIPO_OUTRO = 'outro';

    public const VIS_ESCOLA = 'escola';

    public const VIS_CURSO = 'curso';

    public const VIS_TURMA = 'turma';

    public const VIS_DISCIPLINA = 'disciplina';

    protected $fillable = [
        'titulo',
        'descricao',
        'tipo',
        'inicia_em',
        'termina_em',
        'ano_lectivo_id',
        'turma_id',
        'curso_id',
        'disciplina_id',
        'visibilidade',
        'criado_por',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'inicia_em' => 'datetime',
            'termina_em' => 'datetime',
            'activo' => 'boolean',
        ];
    }

    public static function tipos(): array
    {
        return [
            self::TIPO_FERIADO => 'Feriado',
            self::TIPO_REUNIAO => 'Reunião',
            self::TIPO_ACTIVIDADE => 'Actividade / Evento',
            self::TIPO_AVISO => 'Aviso',
            self::TIPO_EXAME => 'Exame / Prova',
            self::TIPO_CONSELHO => 'Conselho de notas',
            self::TIPO_OUTRO => 'Outro',
        ];
    }

    public static function visibilidades(): array
    {
        return [
            self::VIS_ESCOLA => 'Toda a escola',
            self::VIS_CURSO => 'Curso',
            self::VIS_TURMA => 'Turma',
            self::VIS_DISCIPLINA => 'Disciplina (professores)',
        ];
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }
}
