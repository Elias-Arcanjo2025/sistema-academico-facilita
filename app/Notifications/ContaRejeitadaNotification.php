<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContaRejeitadaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $motivo
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (config('mail.mailers.smtp.username')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Registo Não Aprovado — Facilita')
            ->greeting('Olá, '.$notifiable->name.',')
            ->line('Lamentamos informar que o seu registo no sistema Facilita **não foi aprovado**.')
            ->line('**Motivo:** '.$this->motivo)
            ->line('Se acredita que se trata de um erro, contacte a secretaria ou administração da escola.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'O seu registo não foi aprovado.',
            'motivo' => $this->motivo,
            'tipo' => 'conta_rejeitada',
            'url' => route('login'),
        ];
    }
}
