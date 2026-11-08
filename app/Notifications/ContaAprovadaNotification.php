<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContaAprovadaNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->subject('Conta Aprovada — Facilita')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('A sua conta no sistema Facilita foi **aprovada** pela administração.')
            ->line('Já pode iniciar sessão e utilizar todas as funcionalidades do seu perfil.')
            ->action('Iniciar Sessão', route('login'))
            ->line('Se não solicitou este registo, contacte a secretaria da escola.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'A sua conta foi aprovada. Já pode iniciar sessão.',
            'tipo' => 'conta_aprovada',
            'url' => route('login'),
        ];
    }
}
