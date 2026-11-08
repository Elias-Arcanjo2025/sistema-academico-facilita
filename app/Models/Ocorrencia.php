<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ocorrencia extends Model
{
    public const TIPO_ATRASO = 'atraso';

    public const TIPO_INDISCIPLINA = 'indisciplina';

    public const TIPO_COMPORTAMENTO = 'comportamento';

    public const TIPO_ADVERTENCIA = 'advertencia';

    public const TIPO_DANO = 'dano_material';

    public const TIPO_AGRESSAO = 'agressao';

    public const TIPO_OUTRO = 'outro';

    public const SEV_BAIXA = 'baixa';

    public const SEV_MEDIA = 'media';

    public const SEV_ALTA = 'alta';

    public const ESTADO_ABERTA = 'aberta';

    public const ESTADO_RESOLVIDA = 'resolvida';

    protected $fillable = [
        'aluno_id',
        'turma_id',
        'ano_lectivo_id',
        'data',
        'tipo',
        'severidade',
        'descricao',
        'medida',
        'estado',
        'registado_por',
        'resolvido_por',
        'resolvido_em',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'resolvido_em' => 'datetime',
        ];
    }

    public static function tipos(): array
    {
        return [
            self::TIPO_ATRASO => 'Atraso',
            self::TIPO_COMPORTAMENTO => 'Comportamento',
            self::TIPO_ADVERTENCIA => 'Advertência',
            self::TIPO_INDISCIPLINA => 'Indisciplina',
            self::TIPO_DANO => 'Dano material',
            self::TIPO_AGRESSAO => 'Agressão',
            self::TIPO_OUTRO => 'Outro',
        ];
    }

    /**
     * Medidas disciplinares sugeridas (advertências / sanções).
     *
     * @return array<string, string>
     */
    public static function medidasSugeridas(): array
    {
        return [
            'advertencia_verbal' => 'Advertência verbal',
            'advertencia_escrita' => 'Advertência escrita',
            'chamada_encarregado' => 'Chamada ao encarregado',
            'suspensao_aula' => 'Suspensão de aula',
            'outra' => 'Outra (descrever)',
        ];
    }

    public static function severidades(): array
    {
        return [
            self::SEV_BAIXA => 'Baixa',
            self::SEV_MEDIA => 'Média',
            self::SEV_ALTA => 'Alta',
        ];
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class, 'ano_lectivo_id');
    }

    public function registadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registado_por');
    }

    public function resolvidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolvido_por');
    }

    public function isAberta(): bool
    {
        return $this->estado === self::ESTADO_ABERTA;
    }
}
