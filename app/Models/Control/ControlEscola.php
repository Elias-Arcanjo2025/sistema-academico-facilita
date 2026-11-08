<?php

namespace App\Models\Control;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ControlEscola extends Model
{
    protected $table = 'control_escolas';

    public const STATUS_ACTIVO = 'activo';
    public const STATUS_EM_COBRANCA = 'em_cobranca';
    public const STATUS_EM_ATRASO = 'em_atraso';
    public const STATUS_BLOQUEADO = 'bloqueado';

    protected $fillable = [
        'installation_id',
        'nome',
        'slug',
        'secret_hash',
        'app_url',
        'contacto_email',
        'contacto_telefone',
        'status',
        'ano_lectivo',
        'app_version',
        'total_alunos',
        'total_professores',
        'total_cursos',
        'total_turmas',
        'total_salas',
        'total_disciplinas',
        'total_utilizadores',
        'preco_anual_aluno',
        'minutos_ate_aviso',
        'minutos_ate_atraso',
        'minutos_ate_bloqueio',
        'bloqueio_motivo',
        'bloqueado_por',
        'bloqueado_em',
        'last_heartbeat_at',
        'last_sync_at',
        'sync_requested_at',
        'secret_encrypted',
        'metricas',
        'saude',
        'notas_internas',
        'activo',
    ];

    protected $hidden = [
        'secret_hash',
        'secret_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'preco_anual_aluno' => 'decimal:2',
            'minutos_ate_aviso' => 'integer',
            'minutos_ate_atraso' => 'integer',
            'minutos_ate_bloqueio' => 'integer',
            'bloqueado_em' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'sync_requested_at' => 'datetime',
            'secret_encrypted' => 'encrypted',
            'metricas' => 'array',
            'saude' => 'array',
            'activo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $escola) {
            if (empty($escola->installation_id)) {
                $escola->installation_id = (string) Str::uuid();
            }
            if (empty($escola->slug) && ! empty($escola->nome)) {
                $escola->slug = Str::slug($escola->nome).'-'.Str::lower(Str::random(4));
            }
        });
    }

    public function alunos(): HasMany
    {
        return $this->hasMany(ControlAluno::class, 'control_escola_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(ControlLicenseEvent::class, 'control_escola_id');
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(ControlCobranca::class, 'control_escola_id');
    }

    public function cobrancaAberta(): ?ControlCobranca
    {
        return $this->cobrancas()
            ->whereIn('status', [ControlCobranca::STATUS_ABERTA, ControlCobranca::STATUS_VENCIDA])
            ->latest('id')
            ->first();
    }

    public function bloqueadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bloqueado_por');
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOQUEADO;
    }

    public function isOnline(int $minutes = 90): bool
    {
        return $this->last_heartbeat_at !== null
            && $this->last_heartbeat_at->gt(now()->subMinutes($minutes));
    }

    public function valorEstimado(): float
    {
        return (float) $this->total_alunos * (float) $this->preco_anual_aluno;
    }

    public function plainSecret(): ?string
    {
        return $this->secret_encrypted;
    }

    public function storePlainSecret(string $plainSecret): void
    {
        $this->forceFill([
            'secret_hash' => self::hashSecret($plainSecret),
            'secret_encrypted' => $plainSecret,
        ])->save();
    }

    public function verifySecret(string $plainSecret): bool
    {
        return hash_equals($this->secret_hash, hash('sha256', $plainSecret));
    }

    public static function hashSecret(string $plainSecret): string
    {
        return hash('sha256', $plainSecret);
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVO => 'Activo',
            self::STATUS_EM_COBRANCA => 'Em cobrança',
            self::STATUS_EM_ATRASO => 'Em atraso',
            self::STATUS_BLOQUEADO => 'Bloqueado',
        ];
    }
}
