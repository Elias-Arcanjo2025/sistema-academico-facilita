<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurmaHorarioConfig extends Model
{
    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_APROVADO = 'aprovado';

    protected $table = 'turma_horario_config';

    protected $fillable = [
        'turma_id',
        'ano_lectivo_id',
        'intervalo_activo',
        'intervalo_apos_periodo',
        'intervalo_duracao_minutos',
        'intervalo_texto',
        'status',
        'aprovado_por',
        'aprovado_em',
    ];

    protected function casts(): array
    {
        return [
            'intervalo_activo' => 'boolean',
            'intervalo_apos_periodo' => 'integer',
            'intervalo_duracao_minutos' => 'integer',
            'aprovado_em' => 'datetime',
        ];
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }

    public function aprovador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovado_por');
    }

    public function estaAprovado(): bool
    {
        return $this->status === self::STATUS_APROVADO;
    }

    public function marcarRascunho(): void
    {
        $this->forceFill([
            'status' => self::STATUS_RASCUNHO,
            'aprovado_por' => null,
            'aprovado_em' => null,
        ])->save();
    }

    public function textoIntervalo(): string
    {
        if ($this->intervalo_texto) {
            return mb_strtoupper($this->intervalo_texto);
        }

        $min = max(1, (int) $this->intervalo_duracao_minutos);

        return "INTERVALO DE {$min} MINUTOS";
    }

    public static function padrao(int $turmaId, int $anoLectivoId): self
    {
        return new self([
            'turma_id' => $turmaId,
            'ano_lectivo_id' => $anoLectivoId,
            'intervalo_activo' => true,
            'intervalo_apos_periodo' => 3,
            'intervalo_duracao_minutos' => 25,
            'intervalo_texto' => null,
            'status' => self::STATUS_RASCUNHO,
        ]);
    }

    public static function paraTurma(int $turmaId, ?int $anoLectivoId = null): self
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();

        return self::firstOrNew([
            'turma_id' => $turmaId,
            'ano_lectivo_id' => $anoLectivoId,
        ], self::padrao($turmaId, $anoLectivoId)->only([
            'intervalo_activo',
            'intervalo_apos_periodo',
            'intervalo_duracao_minutos',
            'intervalo_texto',
            'status',
        ]));
    }
}
