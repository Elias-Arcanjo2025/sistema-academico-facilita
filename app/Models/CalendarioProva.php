<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarioProva extends Model
{
    protected $table = 'calendario_provas';

    protected $fillable = [
        'turma_id',
        'disciplina_id',
        'ano_lectivo_id',
        'data_prova',
        'slot',
        'hora_inicio',
        'hora_fim',
        'tipo',
        'periodo',
        'observacoes',
        'supervisor_nome',
    ];

    protected function casts(): array
    {
        return [
            'data_prova' => 'date',
            'slot' => 'integer',
        ];
    }

    public static function tipos(): array
    {
        return [
            'normal' => 'Prova Normal',
            'recurso' => 'Recurso',
            'melhoria' => 'Melhoria',
            'extraordinario' => 'Extraordinário',
        ];
    }

    public function diaSemanaNome(): string
    {
        $dias = TurmaHorario::diasSemana();

        return $dias[$this->data_prova->dayOfWeekIso] ?? $this->data_prova->translatedFormat('l');
    }

    public function dataFormatada(): string
    {
        return $this->data_prova->format('d/m/Y');
    }

    public function horarioFormatado(): string
    {
        if (! $this->hora_inicio) {
            return '-';
        }

        $inicio = Carbon::parse($this->hora_inicio)->format('H:i');
        $fim = $this->hora_fim ? Carbon::parse($this->hora_fim)->format('H:i') : null;

        return $fim ? "{$inicio} – {$fim}" : $inicio;
    }

    public function abreviaturaDisciplina(): string
    {
        $disc = $this->disciplina;
        if (! $disc) {
            return '-';
        }

        if ($disc->codigo) {
            return mb_strtoupper($disc->codigo);
        }

        return mb_strtoupper(mb_substr($disc->nome, 0, 8));
    }

    public function nomeDisciplinaCalendario(): string
    {
        if ($this->observacoes === 'ENCERRAMENTO') {
            return 'ENCERRAMENTO';
        }

        return $this->disciplina?->nome ?? '-';
    }

    public function labelSlot(): string
    {
        return ((int) $this->slot) === 2 ? '2ª Prova' : '1ª Prova';
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }
}
