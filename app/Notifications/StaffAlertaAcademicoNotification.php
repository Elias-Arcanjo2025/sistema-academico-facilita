<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StaffAlertaAcademicoNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{titulo:string, mensagem:string, url:string, tipo_evento?:string}  $dados
     */
    public function __construct(
        private string $tipo,
        private array $dados,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => $this->tipo,
            'tipo_evento' => $this->dados['tipo_evento'] ?? $this->tipo,
            'titulo' => $this->dados['titulo'],
            'mensagem' => $this->dados['mensagem'],
            'url' => $this->dados['url'],
        ];
    }
}
