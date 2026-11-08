<?php

namespace App\Notifications;

use App\Models\AgendaEvento;
use Illuminate\Notifications\Notification;

class AlunoAgendaEventoNotification extends Notification
{
    public function __construct(
        private AgendaEvento $evento
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
        $data = $this->evento->inicia_em?->format('d/m/Y H:i') ?? '—';

        return [
            'tipo_evento' => 'agenda_evento',
            'evento_id' => $this->evento->id,
            'titulo' => $this->evento->titulo,
            'data' => $data,
            'url' => route('portal.aluno.calendario', ['tab' => 'agenda']),
            'message' => "Novo na agenda: {$this->evento->titulo} ({$data}).",
        ];
    }
}
