<?php

namespace App\Notifications;

use App\Models\Turma;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlunoMatriculadoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private Turma $turma,
        private string $anoLectivo
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * Canal 'mail' apenas activo quando MAIL_USERNAME estiver configurado.
     * Por defeito, usa apenas o canal 'database' para evitar erros em ambientes
     * sem servidor SMTP configurado.
     *
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

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Matrícula Confirmada - Facilita')
            ->greeting('Olá, ' . $notifiable->nome . '!')
            ->line("Sua matrícula foi confirmada com sucesso!")
            ->line("**Turma:** {$this->turma->nome}")
            ->line("**Curso:** {$this->turma->curso->nome}")
            ->line("**Ano Lectivo:** {$this->anoLectivo}")
            ->line("**Sala:** " . ($this->turma->sala->nome ?? 'A definir'))
            ->action('Ver Detalhes da Turma', route('turmas.show', $this->turma->id))
            ->line('Bem-vindo(a) ao Sistema Facilita! Desejamos um excelente ano lectivo.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'turma_id' => $this->turma->id,
            'turma_nome' => $this->turma->nome,
            'curso_nome' => $this->turma->curso->nome,
            'ano_lectivo' => $this->anoLectivo,
            'message' => "Matrícula confirmada na turma {$this->turma->nome}",
            'url' => route('turmas.show', $this->turma->id),
        ];
    }
}
