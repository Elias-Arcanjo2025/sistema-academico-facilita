<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Disciplina extends Model
{
    use HasFactory, LogsActivity;

    // Tipos de disciplina
    public const TIPO_GERAL = 'geral';
    public const TIPO_CIENTIFICA = 'cientifica';
    public const TIPO_TECNICA = 'tecnica';
    public const TIPO_PROJETO_ESTAGIO = 'projeto_estagio';

    // Ordem de apresentação dos tipos (para ordenação)
    public const ORDEM_TIPOS = [
        self::TIPO_GERAL => 1,
        self::TIPO_CIENTIFICA => 2,
        self::TIPO_TECNICA => 3,
        self::TIPO_PROJETO_ESTAGIO => 4,
    ];

    public const DIFICULDADE_FACIL = 'facil';
    public const DIFICULDADE_MEDIO = 'medio';
    public const DIFICULDADE_DIFICIL = 'dificil';

    public const NIVEIS_DIFICULDADE = [
        self::DIFICULDADE_FACIL => 'Baixa',
        self::DIFICULDADE_MEDIO => 'Média',
        self::DIFICULDADE_DIFICIL => 'Alta',
    ];

    protected $fillable = [
        'codigo',
        'nome',
        'descricao',
        'curso_id', // Mantido para compatibilidade - será removido futuramente
        'tipo',
        'nivel_dificuldade',
        'ordem_apresentacao',
        'activo',
        'terminal',
        'classe_terminal_id',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'terminal' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['codigo', 'nome', 'tipo', 'ordem_apresentacao', 'activo', 'terminal', 'classe_terminal_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Classe onde a disciplina é terminal (a avaliação termina nesta classe e a
     * CFD é arrastada para a pauta do 3.º trimestre da classe seguinte).
     */
    public function classeTerminal(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'classe_terminal_id');
    }

    /**
     * Relação legacy - mantida para compatibilidade
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * Relação many-to-many com cursos
     */
    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'curso_disciplina')
            ->withPivot('classe_inicio', 'classe_fim', 'permite_pg', 'blindada')
            ->withTimestamps();
    }

    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class);
    }

    public function presencasDiarias(): HasMany
    {
        return $this->hasMany(AlunoPresenca::class);
    }

    public function avaliacoesDiarias(): HasMany
    {
        return $this->hasMany(AlunoAvaliacaoDiaria::class);
    }

    public function professores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'professor_disciplina_turma', 'disciplina_id', 'user_id')
            ->withPivot(['turma_id', 'ano_lectivo', 'activo'])
            ->withTimestamps();
    }

    public function turmas(): BelongsToMany
    {
        return $this->belongsToMany(Turma::class, 'professor_disciplina_turma', 'disciplina_id', 'turma_id')
            ->withPivot(['user_id', 'ano_lectivo', 'activo'])
            ->withTimestamps();
    }

    /**
     * Relação many-to-many com classes (anos escolares)
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'classe_disciplina')
            ->withTimestamps();
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para ordenar disciplinas por tipo e ordem de apresentação
     */
    public function scopeOrdenadaPorTipo($query)
    {
        return $query->orderByRaw("
            CASE tipo
                WHEN 'geral' THEN 1
                WHEN 'cientifica' THEN 2
                WHEN 'tecnica' THEN 3
                WHEN 'projeto_estagio' THEN 4
                ELSE 5
            END
        ")->orderBy('ordem_apresentacao')->orderBy('nome');
    }

    /**
     * Disciplinas do plano curricular da turma (curso + classe).
     */
    public function scopeParaTurma($query, Turma $turma)
    {
        if (! $turma->curso_id || ! $turma->classe_id) {
            return $query->whereRaw('0 = 1');
        }

        return $query
            ->whereHas('cursos', fn ($q) => $q->where('cursos.id', $turma->curso_id))
            ->whereHas('classes', fn ($q) => $q->where('classes.id', $turma->classe_id))
            ->activo();
    }

    /**
     * Obter label do tipo de disciplina
     */
    public function getTipoLabelAttribute(): string
    {
        return match($this->tipo) {
            self::TIPO_GERAL => 'Geral',
            self::TIPO_CIENTIFICA => 'Científica',
            self::TIPO_TECNICA => 'Técnica',
            self::TIPO_PROJETO_ESTAGIO => 'Projeto/Estágio',
            default => 'Não definido'
        };
    }

    public function getDificuldadeLabelAttribute(): string
    {
        return self::NIVEIS_DIFICULDADE[$this->nivel_dificuldade ?? self::DIFICULDADE_MEDIO]
            ?? 'Média';
    }

    public function dificuldadePeso(): int
    {
        return match ($this->nivel_dificuldade ?? self::DIFICULDADE_MEDIO) {
            self::DIFICULDADE_FACIL => 1,
            self::DIFICULDADE_DIFICIL => 3,
            default => 2,
        };
    }

    public function isDificuldadeAlta(): bool
    {
        return ($this->nivel_dificuldade ?? self::DIFICULDADE_MEDIO) === self::DIFICULDADE_DIFICIL;
    }

    public function isDificuldadeBaixa(): bool
    {
        return ($this->nivel_dificuldade ?? self::DIFICULDADE_MEDIO) === self::DIFICULDADE_FACIL;
    }

    /**
     * Obter nomes dos cursos associados
     */
    public function getCursosNomesAttribute(): string
    {
        return $this->cursos->pluck('nome')->implode(', ') ?: ($this->curso?->nome ?? 'Sem curso');
    }

    /**
     * Obter nomes das classes associadas
     */
    public function getClassesNomesAttribute(): string
    {
        return $this->classes->pluck('nome')->implode(', ') ?: 'Todas as classes';
    }
}
