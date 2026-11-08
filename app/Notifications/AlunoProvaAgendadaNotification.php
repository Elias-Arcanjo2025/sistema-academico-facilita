<?php

namespace App\Notifications;

use App\Models\CalendarioProva;
use Illuminate\Notifications\Notification;

class AlunoProvaAgendadaNotification extends Notification
{

    public function __construct(
        private CalendarioProva $prova
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $disciplina = $this->prova->disciplina?->nome ?? 'Disciplina';
        $data = $this->prova->data_prova?->format('d/m/Y') ?? '—';

        return [
            'tipo_evento' => 'prova_agendada',
            'prova_id' => $this->prova->id,
            'disciplina' => $disciplina,
            'data_prova' => $data,
            'tipo' => $this->prova->tipo,
            'url' => route('portal.aluno.calendario', ['tab' => 'provas']),
            'message' => "Prova de {$disciplina} agendada para {$data}.",
        ];
    }
}
