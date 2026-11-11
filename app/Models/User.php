<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Support\CargosInstitucionais;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Traits\HasPermissions;

class User extends Authenticatable
{
    use HasFactory, HasPublicUuid, Notifiable, HasRoles, HasPermissions, LogsActivity;

    protected $fillable = [
        'name',
        'email',
        'password',
        'estado',
        'motivo_rejeicao',
        'aluno_id',
        'encarregado_id',
        'must_change_password',
        'password_changed_at',
        'last_login_at',
        'preferencias',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'preferencias' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'estado'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    public function isPendente(): bool
    {
        return $this->estado === 'pendente';
    }

    public function isActivo(): bool
    {
        return $this->estado === 'activo';
    }

    public function isRejeitado(): bool
    {
        return $this->estado === 'rejeitado';
    }

    public function isInactivo(): bool
    {
        return $this->estado === 'inactivo';
    }

    public function isAluno(): bool
    {
        return $this->hasRole('aluno') && $this->aluno_id !== null;
    }

    /** Conta do portal do aluno (listagens — inclui registos só com cargo aluno). */
    public function eUtilizadorAluno(): bool
    {
        return $this->hasRole('aluno') || $this->aluno_id !== null;
    }

    /** Conta do portal do encarregado. */
    public function eUtilizadorEncarregado(): bool
    {
        return $this->hasRole('encarregado') || $this->encarregado_id !== null;
    }

    /**
     * Conta exclusiva de portal (aluno ou encarregado), sem cargos de staff.
     */
    public function eContaPortalExclusiva(): bool
    {
        if ($this->hasAnyRole([
            'admin_full',
            'directora_geral',
            'subdiretor_pedagogico',
            'gestor_academico',
            'coordenador_curso',
            'coordenador_disciplina',
            'director_turma',
            'professor',
            'secretaria',
            'inspetor',
        ])) {
            return false;
        }

        return ($this->hasRole('aluno') && $this->aluno_id)
            || ($this->hasRole('encarregado') && $this->encarregado_id);
    }

    /**
     * Rota de entrada após login / alteração de senha.
     */
    public function rotaInicio(): string
    {
        if ($this->hasRole('aluno') && $this->aluno_id) {
            return route('portal.aluno.dashboard');
        }

        if ($this->hasRole('encarregado') && $this->encarregado_id) {
            return route('portal.encarregado.dashboard');
        }

        if (config('control.mode') === 'hub') {
            return route('control.hub.index');
        }

        return route('dashboard');
    }

    public function scopeUtilizadoresAlunos($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('aluno_id')
                ->orWhereHas('roles', fn ($r) => $r->where('name', 'aluno'));
        });
    }

    public function scopeUtilizadoresEncarregados($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('encarregado_id')
                ->orWhereHas('roles', fn ($r) => $r->where('name', 'encarregado'));
        });
    }

    public function scopeFuncionariosEscola($query)
    {
        return $query->whereNull('aluno_id')
            ->whereNull('encarregado_id')
            ->whereDoesntHave('roles', fn ($r) => $r->whereIn('name', ['aluno', 'encarregado']));
    }

    /** Visão de todos os cursos/turmas (inclui subdirector — consulta). */
    public function temVisaoInstitucional(): bool
    {
        return $this->hasAnyRole(CargosInstitucionais::visaoInstitucional());
    }

    /** Lançamento/edição de notas e pautas em qualquer curso/turma. */
    public function temControloAcademicoInstitucional(): bool
    {
        return $this->hasAnyRole(CargosInstitucionais::controloAcademicoTotal());
    }

    /** Acesso operacional a alunos/matrículas (gestão + secretaria). */
    public function temAcessoSecretariaAlunos(): bool
    {
        return $this->hasAnyRole(CargosInstitucionais::secretariaOperacional())
            || $this->can('secretaria.matriculas.gerir');
    }

    /** Pode forçar transferência entre cursos (arquiva notas). */
    public function podeTransferirEntreCursos(): bool
    {
        return $this->hasRole('admin_full') || $this->temAcessoSecretariaAlunos();
    }

    /** Visão transversal de assiduidade/ocorrências (inclui inspetor). */
    public function temVisaoAssiduidade(): bool
    {
        return $this->hasAnyRole(CargosInstitucionais::visaoAssiduidade());
    }

    /** Pode lançar notas fora do trimestre aberto. */
    public function podeIgnorarTrimestreFechado(): bool
    {
        return $this->hasAnyRole(CargosInstitucionais::ignoraTrimestreFechado());
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function encarregado(): BelongsTo
    {
        return $this->belongsTo(Encarregado::class);
    }

    /**
     * Associações professor-disciplina-turma (tabela pivot completa).
     *
     * Usar para aceder aos registos completos da tabela pivot com disciplina_id.
     * Para obter apenas as turmas, usar {@see turmasComoProfessor()}.
     */
    public function professorDisciplinaTurmas(): HasMany
    {
        return $this->hasMany(ProfessorDisciplinaTurma::class, 'user_id');
    }

    /**
     * Registos de direcção de turma (tabela director_turma).
     *
     * Usar para filtrar por ano_lectivo_id ou activo com query directa:
     *   $user->directorTurmas()->where('ano_lectivo_id', $id)->where('activo', true)->get()
     */
    public function directorTurmas(): HasMany
    {
        return $this->hasMany(DirectorTurma::class, 'user_id');
    }

    /**
     * Registos de coordenação de curso (tabela coordenador_curso).
     *
     * Usar para filtrar por ano_lectivo_id ou activo com query directa:
     *   $user->coordenadorCursos()->where('activo', true)->pluck('curso_id')
     */
    public function coordenadorCursos(): HasMany
    {
        return $this->hasMany(CoordenadorCurso::class, 'user_id');
    }

    public function coordenadorDisciplinas(): HasMany
    {
        return $this->hasMany(CoordenadorDisciplina::class, 'user_id');
    }

    public function disciplinasComoCoordenador(): BelongsToMany
    {
        return $this->belongsToMany(Disciplina::class, 'coordenador_disciplina', 'user_id', 'disciplina_id')
            ->withPivot(['ano_lectivo', 'ano_lectivo_id', 'activo'])
            ->withTimestamps();
    }

    /**
     * Notas lançadas pelo professor (identificadas por professor_id).
     */
    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class, 'professor_id');
    }

    public function presencasDiarias(): HasMany
    {
        return $this->hasMany(AlunoPresenca::class, 'professor_id');
    }

    public function avaliacoesDiarias(): HasMany
    {
        return $this->hasMany(AlunoAvaliacaoDiaria::class, 'professor_id');
    }

    /**
     * Turmas onde o utilizador lecciona (via pivot professor_disciplina_turma).
     *
     * Pivot disponível: disciplina_id, ano_lectivo (string), activo.
     * Exemplos de uso:
     *   // Turmas activas no ano actual
     *   $user->turmasComoProfessor()->wherePivot('activo', true)->get()
     *   // Obter IDs directamente
     *   $user->turmasComoProfessor()->pluck('turma_id')
     */
    public function turmasComoProfessor(): BelongsToMany
    {
        return $this->belongsToMany(Turma::class, 'professor_disciplina_turma', 'user_id', 'turma_id')
            ->withPivot(['disciplina_id', 'ano_lectivo', 'activo'])
            ->withTimestamps();
    }

    /**
     * Turmas que o utilizador dirige como Director de Turma (pivot director_turma).
     *
     * Pivot disponível: ano_lectivo (string), activo.
     * Para filtrar por ano lectivo activo:
     *   $user->turmasComoDirector()
     *       ->wherePivot('activo', true)
     *       ->where('turmas.ano_lectivo_id', $anoLectivoId)
     *       ->get()
     *
     * Para obter IDs: usar ->pluck('turmas.id') (prefixo da tabela obrigatório).
     */
    public function turmasComoDirector(): BelongsToMany
    {
        return $this->belongsToMany(Turma::class, 'director_turma', 'user_id', 'turma_id')
            ->withPivot(['ano_lectivo', 'activo'])
            ->withTimestamps();
    }

    /**
     * Cursos que o utilizador coordena como Coordenador de Curso (pivot coordenador_curso).
     *
     * Pivot disponível: ano_lectivo (string), ano_lectivo_id (int), activo.
     * Para filtrar por ano lectivo activo:
     *   $user->cursosComoCoordenador()
     *       ->wherePivot('activo', true)
     *       ->wherePivot('ano_lectivo_id', $anoLectivoId)
     *       ->get()
     *
     * Para obter IDs dos cursos: ->pluck('cursos.id').
     */
    public function cursosComoCoordenador(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'coordenador_curso', 'user_id', 'curso_id')
            ->withPivot(['ano_lectivo', 'ano_lectivo_id', 'activo'])
            ->withTimestamps();
    }

    /**
     * Retorna permissões diretas (extras) atribuídas ao usuário
     * (exclui permissões herdadas dos roles)
     */
    public function getExtraPermissions()
    {
        return $this->getDirectPermissions();
    }

    /**
     * Retorna permissões herdadas dos roles
     */
    public function getRolePermissions()
    {
        return $this->getPermissionsViaRoles();
    }

    /**
     * Verifica se uma permissão é "extra" (direta) ou herdada do role
     */
    public function isExtraPermission(string $permissionName): bool
    {
        return $this->getDirectPermissions()
            ->pluck('name')
            ->contains($permissionName);
    }
}
