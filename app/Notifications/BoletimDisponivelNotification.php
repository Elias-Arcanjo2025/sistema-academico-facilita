<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BoletimDisponivelNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private int $trimestre,
        private string $anoLectivo
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Boletim Disponível - Facilita')
            ->greeting('Olá, ' . $notifiable->nome . '!')
            ->line("Seu boletim do {$this->trimestre}º Trimestre já está disponível!")
            ->line("**Ano Lectivo:** {$this->anoLectivo}")
            ->line("Você pode visualizar suas notas e desempenho acadêmico.")
            ->action('Ver Boletim', route('relatorios.boletins'))
            ->line('Continue se dedicando e alcançando seus objetivos!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'trimestre' => $this->trimestre,
            'ano_lectivo' => $this->anoLectivo,
            'message' => "Boletim do {$this->trimestre}º Trimestre disponível",
            'url' => route('relatorios.boletins'),
        ];
    }
}
