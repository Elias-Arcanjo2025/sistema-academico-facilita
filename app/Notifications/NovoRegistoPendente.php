<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NovoRegistoPendente extends Notification
{
    use Queueable;

    public function __construct(
        protected User $novoUtilizador
    ) {}

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
            ->subject('Novo Registo Pendente de Aprovação')
            ->greeting('Olá ' . $notifiable->name . ',')
            ->line('Um novo utilizador registou-se no sistema e aguarda aprovação.')
            ->line('**Nome:** ' . $this->novoUtilizador->name)
            ->line('**Email:** ' . $this->novoUtilizador->email)
            ->action('Gerir Utilizadores Pendentes', route('users.pendentes'))
            ->line('Por favor, reveja este registo e aprove ou rejeite a conta.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->novoUtilizador->id,
            'user_name' => $this->novoUtilizador->name,
            'user_email' => $this->novoUtilizador->email,
            'message' => 'Novo registo pendente: ' . $this->novoUtilizador->name,
            'url' => route('users.pendentes'),
        ];
    }
}
