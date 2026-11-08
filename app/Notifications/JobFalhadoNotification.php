<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JobFalhadoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $jobName,
        private int $entityId,
        private string $errorMessage
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Falha em Job - Facilita')
            ->greeting('Atenção, ' . ($notifiable->name ?? 'Administrador') . '!')
            ->line("Um job falhou no sistema Facilita.")
            ->line("**Job:** {$this->jobName}")
            ->line("**ID da entidade:** {$this->entityId}")
            ->line("**Erro:** {$this->errorMessage}")
            ->action('Ver Filas', route('queue.index'))
            ->line('Por favor, verifique os logs para mais detalhes.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'job_name' => $this->jobName,
            'entity_id' => $this->entityId,
            'error' => $this->errorMessage,
            'message' => "Job '{$this->jobName}' falhou: {$this->errorMessage}",
            'url' => route('queue.index'),
        ];
    }
}
