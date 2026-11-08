<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Encarregado extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'nome',
        'telefone',
        'telefone_normalizado',
        'email',
        'documento',
        'whatsapp_opt_in',
        'whatsapp_opt_in_at',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'whatsapp_opt_in' => 'boolean',
            'whatsapp_opt_in_at' => 'datetime',
            'activo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Encarregado $encarregado) {
            if ($encarregado->isDirty('telefone') || blank($encarregado->telefone_normalizado)) {
                $encarregado->telefone_normalizado = filled($encarregado->telefone)
                    ? app(WhatsAppService::class)->normalizePhone((string) $encarregado->telefone)
                    : null;
            }
        });
    }

    public function alunos(): BelongsToMany
    {
        return $this->belongsToMany(Aluno::class, 'aluno_encarregado')
            ->withPivot(['parentesco', 'principal', 'pode_ver_notas', 'pode_assinar_boletim'])
            ->withTimestamps();
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function assinaturas(): HasMany
    {
        return $this->hasMany(BoletimAssinatura::class);
    }

    public function telefoneNormalizado(): ?string
    {
        if ($this->telefone_normalizado) {
            return $this->telefone_normalizado;
        }

        if (! $this->telefone) {
            return null;
        }

        return app(WhatsAppService::class)->normalizePhone((string) $this->telefone) ?: null;
    }
}
