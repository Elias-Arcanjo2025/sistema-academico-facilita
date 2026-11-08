<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessorDisciplinaTurma extends Model
{
    use HasFactory;

    protected $table = 'professor_disciplina_turma';

    protected $fillable = [
        'user_id',
        'disciplina_id',
        'turma_id',
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
     */
    protected function anoLectivoId(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                // Quando ID é definido, preencher string automaticamente
                if ($value && !isset($this->attributes['ano_lectivo'])) {
                    $this->attributes['ano_lectivo'] = AnoLectivo::getNomeById($value);
                }
                return $value;
            }
        );
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeAnoLectivo($query, $ano)
    {
        return $query->where('ano_lectivo', $ano);
    }

    public function scopeInactivo($query)
    {
        return $query->where('activo', false);
    }

    /** Notas lançadas por este professor nesta turma/disciplina (histórico preservado). */
    public function contarNotas(): int
    {
        return Nota::where('professor_id', $this->user_id)
            ->where('disciplina_id', $this->disciplina_id)
            ->where('turma_id', $this->turma_id)
            ->count();
    }

    /**
     * Desactiva outras associações activas para a mesma turma/disciplina/ano.
     * Usado ao associar um novo professor — o registo anterior permanece inactivo.
     */
    public static function desactivarOutrasActivas(
        int $disciplinaId,
        int $turmaId,
        int $anoLectivoId,
        int $exceptUserId
    ): int {
        return self::query()
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('activo', true)
            ->where('user_id', '!=', $exceptUserId)
            ->update(['activo' => false]);
    }
}
