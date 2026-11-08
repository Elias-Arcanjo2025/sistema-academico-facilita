<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurmaCalendarioProvaConfig extends Model
{
    protected $table = 'turma_calendario_prova_config';

    protected $fillable = [
        'turma_id',
        'ano_lectivo_id',
        'hora_1a_inicio',
        'hora_1a_fim',
        'hora_2a_inicio',
        'hora_2a_fim',
    ];

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }

    public function legenda1aProva(): string
    {
        return 'DAS '.$this->formatarHoraLegenda($this->hora_1a_inicio)
            .'-----'.$this->formatarHoraLegenda($this->hora_1a_fim);
    }

    public function legenda2aProva(): string
    {
        return 'DAS '.$this->formatarHoraLegenda($this->hora_2a_inicio)
            .'-----'.$this->formatarHoraLegenda($this->hora_2a_fim);
    }

    public static function horariosPadraoParaTurno(?Turno $turno): array
    {
        $nome = mb_strtolower($turno?->nome ?? 'manhã');

        if (str_contains($nome, 'tarde')) {
            return [
                'hora_1a_inicio' => '14:00:00',
                'hora_1a_fim' => '15:50:00',
                'hora_2a_inicio' => '16:00:00',
                'hora_2a_fim' => '17:50:00',
            ];
        }

        if (str_contains($nome, 'noite') || str_contains($nome, 'vesp')) {
            return [
                'hora_1a_inicio' => '18:30:00',
                'hora_1a_fim' => '20:20:00',
                'hora_2a_inicio' => '20:30:00',
                'hora_2a_fim' => '22:20:00',
            ];
        }

        return [
            'hora_1a_inicio' => '08:30:00',
            'hora_1a_fim' => '10:20:00',
            'hora_2a_inicio' => '10:30:00',
            'hora_2a_fim' => '12:20:00',
        ];
    }

    public static function paraTurmaComTurno(Turma $turma, ?int $anoLectivoId = null): self
    {
        $config = self::paraTurma($turma->id, $anoLectivoId);
        $turno = $turma->resolverTurno();
        $padrao = self::horariosPadraoParaTurno($turno);

        if (! $config->exists) {
            $config->fill($padrao);
        }

        return $config;
    }

    public static function paraTurma(int $turmaId, ?int $anoLectivoId = null): self
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();

        return self::firstOrNew([
            'turma_id' => $turmaId,
            'ano_lectivo_id' => $anoLectivoId,
        ], [
            'hora_1a_inicio' => '08:30',
            'hora_1a_fim' => '10:20',
            'hora_2a_inicio' => '10:30',
            'hora_2a_fim' => '12:20',
        ]);
    }

    protected function formatarHoraLegenda(?string $hora): string
    {
        if (! $hora) {
            return '--H--';
        }

        return strtoupper(str_replace(':', 'HR', substr($hora, 0, 5)));
    }
}
