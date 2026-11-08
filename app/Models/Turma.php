<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Turma extends Model
{
    use HasFactory, HasPublicUuid, LogsActivity;

    protected $fillable = [
        'codigo',
        'nome',
        'curso_id',
        'classe_id',
        'sala_id',
        'turno_id',
        'ano_lectivo',
        'ano_lectivo_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'ano_lectivo' => 'string',
            'activo' => 'boolean',
        ];
    }

    /**
     * Mutator: Sincronizar ano_lectivo quando ano_lectivo_id é definido
     * Sempre preenche ano_lectivo baseado no ano_lectivo_id
     */
    protected function anoLectivoId(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                // Quando ID é definido, preencher string automaticamente
                if ($value) {
                    $this->attributes['ano_lectivo'] = AnoLectivo::getNomeById($value);
                }
                return $value;
            }
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['codigo', 'nome', 'curso_id', 'classe_id', 'ano_lectivo', 'activo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }

    public function alunos(): HasMany
    {
        return $this->hasMany(Aluno::class);
    }

    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class);
    }

    public function horariosAula(): HasMany
    {
        return $this->hasMany(TurmaHorario::class);
    }

    public function horarioConfig(): HasMany
    {
        return $this->hasMany(TurmaHorarioConfig::class);
    }

    public function calendarioProvas(): HasMany
    {
        return $this->hasMany(CalendarioProva::class);
    }

    public function presencaAulas(): HasMany
    {
        return $this->hasMany(PresencaAula::class);
    }

    public function ocorrencias(): HasMany
    {
        return $this->hasMany(Ocorrencia::class);
    }

    public function professores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'professor_disciplina_turma', 'turma_id', 'user_id')
            ->withPivot(['disciplina_id', 'ano_lectivo', 'activo'])
            ->withTimestamps();
    }

    public function directores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'director_turma', 'turma_id', 'user_id')
            ->withPivot(['ano_lectivo', 'activo'])
            ->withTimestamps();
    }

    public function disciplinas(): BelongsToMany
    {
        return $this->belongsToMany(Disciplina::class, 'professor_disciplina_turma', 'turma_id', 'disciplina_id')
            ->withPivot(['user_id', 'ano_lectivo', 'activo'])
            ->withTimestamps();
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeAnoLectivo($query, $ano)
    {
        if (is_numeric($ano)) {
            return $query->where('ano_lectivo_id', (int) $ano);
        }

        $id = \App\Models\AnoLectivo::getIdByNome((string) $ano);

        return $id
            ? $query->where(function ($q) use ($ano, $id) {
                $q->where('ano_lectivo_id', $id)
                    ->orWhere('ano_lectivo', $ano);
            })
            : $query->where('ano_lectivo', $ano);
    }

    /**
     * Número da classe (10, 11, 12…) extraído do código ou nome.
     */
    public function numeroClasse(): int
    {
        $this->loadMissing('classe');

        return (int) preg_replace('/\D/', '', $this->classe?->codigo ?? $this->classe?->nome ?? '');
    }

    /**
     * Letra de turno no fim do código/nome (M=Manhã, T=Tarde, N=Noite).
     * Ex.: CG10AT → T
     */
    public function inferirTurnoLetra(): ?string
    {
        $ref = mb_strtoupper(trim($this->codigo ?: $this->nome ?: ''));

        if (preg_match('/([MTN])$/u', $ref, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Resolve o turno pela relação directa ou pela letra no nome/código.
     */
    public function resolverTurno(): ?Turno
    {
        $this->loadMissing('turno');

        if ($this->turno) {
            return $this->turno;
        }

        $letra = $this->inferirTurnoLetra();
        if (! $letra) {
            return null;
        }

        $mapa = [
            'M' => ['manh', 'matut'],
            'T' => ['tard'],
            'N' => ['noit', 'vesp'],
        ];

        $padroes = $mapa[$letra] ?? [];

        return Turno::query()
            ->activo()
            ->get()
            ->first(function (Turno $turno) use ($padroes, $letra) {
                $codigo = mb_strtoupper($turno->codigo ?? '');
                $nome = mb_strtolower($turno->nome ?? '');

                if ($codigo === $letra) {
                    return true;
                }

                foreach ($padroes as $padrao) {
                    if (str_contains($nome, $padrao)) {
                        return true;
                    }
                }

                return false;
            });
    }

    /**
     * ID do turno resolvido (directo ou inferido do nome).
     */
    public function turnoIdResolvido(): ?int
    {
        return $this->resolverTurno()?->id ?? $this->turno_id;
    }

    public function turnoNomeResolvido(): string
    {
        return $this->resolverTurno()?->nome ?? $this->turno?->nome ?? 'Manhã';
    }
}
