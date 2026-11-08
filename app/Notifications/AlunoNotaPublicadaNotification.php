<?php

namespace App\Notifications;

use App\Models\Nota;
use Illuminate\Notifications\Notification;

class AlunoNotaPublicadaNotification extends Notification
{

    public function __construct(
        private Nota $nota
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $disciplina = $this->nota->disciplina?->nome ?? 'Disciplina';
        $trimestre = $this->nota->trimestre ? $this->nota->trimestre.'º trimestre' : 'Anual';

        return [
            'tipo_evento' => 'nota_publicada',
            'nota_id' => $this->nota->id,
            'disciplina' => $disciplina,
            'tipo_nota' => $this->nota->tipo,
            'valor' => $this->nota->valor,
            'trimestre' => $this->nota->trimestre,
            'url' => route('portal.aluno.notas'),
            'message' => "Nova nota em {$disciplina} ({$trimestre}): {$this->nota->tipo} — ".number_format((float) $this->nota->valor, 1).' val.',
        ];
    }
}
