<?php

namespace App\Notifications;

use App\Models\Nota;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NotaLancadaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Nota $nota,
        private string $disciplina,
        private string $turma,
        private string $trimestre
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
        $nome = $notifiable->name ?? $notifiable->nome ?? 'Professor';
        $alunoNome = $this->nota->relationLoaded('aluno')
            ? ($this->nota->aluno?->nome ?? 'aluno')
            : 'aluno';

        return (new MailMessage)
            ->subject('Nota registada - Facilita')
            ->greeting('Olá, '.$nome.'!')
            ->line('A nota foi registada com sucesso no Facilita.')
            ->line("**Aluno:** {$alunoNome}")
            ->line("**Disciplina:** {$this->disciplina}")
            ->line("**Turma:** {$this->turma}")
            ->line("**Trimestre:** {$this->trimestre}")
            ->line('**Tipo:** '.$this->nota->getTipoDescricao())
            ->line("**Valor:** {$this->nota->valor} valores")
            ->action('Ver lançamento de notas', route('notas.lancar'))
            ->line('Obrigado por manter o registo académico actualizado.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'nota_id' => $this->nota->id,
            'disciplina' => $this->disciplina,
            'turma' => $this->turma,
            'trimestre' => $this->trimestre,
            'tipo' => $this->nota->tipo,
            'valor' => $this->nota->valor,
            'message' => "Nota registada em {$this->disciplina} ({$this->turma})",
            'url' => route('notas.lancar'),
        ];
    }
}
