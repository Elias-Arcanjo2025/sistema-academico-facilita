<?php

namespace App\Notifications;

use App\Models\Aluno;
use App\Models\Nota;
use Illuminate\Notifications\Notification;

class EncarregadoNotaPublicadaNotification extends Notification
{
    public function __construct(
        private Nota $nota,
        private Aluno $aluno,
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
        $alunoNome = $this->aluno->nome;

        return [
            'tipo_evento' => 'encarregado_nota_publicada',
            'nota_id' => $this->nota->id,
            'aluno_id' => $this->aluno->id,
            'disciplina' => $disciplina,
            'tipo_nota' => $this->nota->tipo,
            'valor' => $this->nota->valor,
            'trimestre' => $this->nota->trimestre,
            'url' => route('portal.encarregado.filho', $this->aluno),
            'message' => "Nova nota de {$alunoNome} em {$disciplina} ({$trimestre}): {$this->nota->tipo} — "
                .number_format((float) $this->nota->valor, 1).' val.',
        ];
    }
}
