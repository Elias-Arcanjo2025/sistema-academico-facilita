<?php

namespace App\Notifications;

use App\Models\Aluno;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BoletimGeradoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Aluno $aluno,
        private string $filename,
        private string $url
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'aluno_id' => $this->aluno->id,
            'aluno_nome' => $this->aluno->nome,
            'filename' => $this->filename,
            'url' => $this->url,
            'message' => "Boletim do aluno {$this->aluno->nome} gerado com sucesso.",
        ];
    }
}
