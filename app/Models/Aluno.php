<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Aluno extends Model
{
    use HasFactory, HasPublicUuid, LogsActivity, Notifiable;

    // Constantes de Situação Acadêmica
    const SITUACAO_MATRICULADO = 'matriculado';
    const SITUACAO_APROVADO = 'aprovado';
    const SITUACAO_REPROVADO = 'reprovado';
    const SITUACAO_DESISTENTE = 'desistente';
    const SITUACAO_TRANSFERIDO = 'transferido';

    protected $fillable = [
        'numero_processo',
        'nome',
        'foto_path',
        'data_nascimento',
        'genero',
        'bi_numero',
        'educacao_especial',
        'necessidades_especificas',
        'atleta_alta_competicao',
        'faltou_exame_normal',
        'turma_id',
        'numero_ordem',
        'activo',
        'consulta_notas_portal',
        'motivo_bloqueio_portal',
        'situacao_academica',
    ];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'numero_ordem' => 'integer',
            'activo' => 'boolean',
            'consulta_notas_portal' => 'boolean',
            'educacao_especial' => 'boolean',
            'atleta_alta_competicao' => 'boolean',
            'faltou_exame_normal' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['numero_processo', 'nome', 'turma_id', 'activo', 'consulta_notas_portal', 'situacao_academica', 'educacao_especial'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function scopeEducacaoEspecial($query)
    {
        return $query->where('educacao_especial', true);
    }

    /**
     * Retorna array de situações acadêmicas disponíveis
     */
    public static function getSituacoesAcademicas(): array
    {
        return [
            self::SITUACAO_MATRICULADO => 'Matriculado',
            self::SITUACAO_APROVADO => 'Aprovado',
            self::SITUACAO_REPROVADO => 'Reprovado',
            self::SITUACAO_DESISTENTE => 'Desistente',
            self::SITUACAO_TRANSFERIDO => 'Transferido',
        ];
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(User::class);
    }

    public function encarregados(): BelongsToMany
    {
        return $this->belongsToMany(Encarregado::class, 'aluno_encarregado')
            ->withPivot(['parentesco', 'principal', 'pode_ver_notas', 'pode_assinar_boletim'])
            ->withTimestamps();
    }

    public function encarregadoPorParentesco(string $parentesco): ?Encarregado
    {
        return $this->encarregados->first(
            fn (Encarregado $e) => ($e->pivot->parentesco ?? null) === $parentesco
        );
    }

    public function nomePai(): ?string
    {
        return $this->encarregadoPorParentesco('pai')?->nome;
    }

    public function nomeMae(): ?string
    {
        return $this->encarregadoPorParentesco('mae')?->nome;
    }

    public function encarregadoPrincipal(): ?Encarregado
    {
        return $this->encarregados->first(fn (Encarregado $e) => (bool) ($e->pivot->principal ?? false))
            ?? $this->encarregados->first();
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(AlunoDocumento::class);
    }

    public function historicosMatricula(): HasMany
    {
        return $this->hasMany(MatriculaHistorico::class)->latest();
    }

    public function presencaRegistos(): HasMany
    {
        return $this->hasMany(PresencaRegisto::class);
    }

    public function presencasDiarias(): HasMany
    {
        return $this->hasMany(AlunoPresenca::class);
    }

    public function avaliacoesDiarias(): HasMany
    {
        return $this->hasMany(AlunoAvaliacaoDiaria::class);
    }

    public function ocorrencias(): HasMany
    {
        return $this->hasMany(Ocorrencia::class);
    }

    public function fotoUrl(): ?string
    {
        if (! $this->foto_path) {
            return null;
        }

        return asset('storage/'.$this->foto_path);
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeOrdenado($query)
    {
        return $query->orderBy('numero_ordem');
    }

    // Scopes de Situação Acadêmica
    public function scopeMatriculados($query)
    {
        return $query->where('situacao_academica', self::SITUACAO_MATRICULADO);
    }

    public function scopeAprovados($query)
    {
        return $query->where('situacao_academica', self::SITUACAO_APROVADO);
    }

    public function scopeReprovados($query)
    {
        return $query->where('situacao_academica', self::SITUACAO_REPROVADO);
    }

    public function scopeDesistentes($query)
    {
        return $query->where('situacao_academica', self::SITUACAO_DESISTENTE);
    }

    public function scopeTransferidos($query)
    {
        return $query->where('situacao_academica', self::SITUACAO_TRANSFERIDO);
    }

    public function scopePorSituacao($query, string $situacao)
    {
        return $query->where('situacao_academica', $situacao);
    }

    // Helpers de Situação
    public function isAprovado(): bool
    {
        return $this->situacao_academica === self::SITUACAO_APROVADO;
    }

    public function isReprovado(): bool
    {
        return $this->situacao_academica === self::SITUACAO_REPROVADO;
    }

    public function getSituacaoAcademicaBadgeAttribute(): string
    {
        return match($this->situacao_academica) {
            self::SITUACAO_MATRICULADO => '<span class="badge bg-primary">Matriculado</span>',
            self::SITUACAO_APROVADO => '<span class="badge bg-success">Aprovado</span>',
            self::SITUACAO_REPROVADO => '<span class="badge bg-danger">Reprovado</span>',
            self::SITUACAO_DESISTENTE => '<span class="badge bg-warning">Desistente</span>',
            self::SITUACAO_TRANSFERIDO => '<span class="badge bg-info">Transferido</span>',
            default => '<span class="badge bg-secondary">-</span>',
        };
    }

    public function getIdadeAttribute(): ?int
    {
        if (!$this->data_nascimento) {
            return null;
        }
        return $this->data_nascimento->age;
    }

    /**
     * Elimina o aluno e todas as avaliações (notas arquivadas incluídas).
     * Operação irreversível — apenas admin_full.
     */
    public function eliminarPermanentementeComAvaliacoes(): int
    {
        $notasCount = $this->notas()->count();
        $this->notas()->delete();

        \App\Models\Pedagogico\PedCorrecao::where('aluno_id', $this->id)->delete();

        $user = $this->user;
        if ($user) {
            $user->roles()->detach();
            $user->permissions()->detach();
            $user->delete();
        }

        $turmaId = $this->turma_id;
        $this->delete();

        if ($turmaId) {
            self::recalcularNumerosOrdemTurma($turmaId);
        }

        return $notasCount;
    }

    public static function recalcularNumerosOrdemTurma(int $turmaId): void
    {
        self::where('turma_id', $turmaId)->update(['numero_ordem' => null]);

        $alunos = self::where('turma_id', $turmaId)->orderBy('nome')->get();

        foreach ($alunos as $index => $aluno) {
            $aluno->update(['numero_ordem' => $index + 1]);
        }
    }
}
