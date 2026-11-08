<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $expireMinutes = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');

        return (new MailMessage)
            ->subject('Recuperação de Palavra-passe – ' . config('app.name'))
            ->greeting('Olá ' . ($notifiable->name ?? 'Utilizador') . ',')
            ->line('Recebemos um pedido para redefinir a palavra-passe da sua conta.')
            ->action('Redefinir Palavra-passe', $url)
            ->line("Este link expira em {$expireMinutes} minutos.")
            ->line('Se não solicitou a recuperação da palavra-passe, ignore este e-mail. A sua conta permanece segura.')
            ->salutation('Atenciosamente, ' . config('app.name'));
    }
}
