<?php

namespace App\Notifications;

use App\Models\Turma;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PautaProcessadaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Turma $turma,
        private int $trimestre,
        private string $tipoCalculo,
        private array $resultado
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $tipoLabel = match($this->tipoCalculo) {
            'medias_trimestre' => 'Médias Trimestrais',
            'notas_finais' => 'Notas Finais',
            'mft2' => 'MFT2',
            'cf' => 'Classificação Final',
            'ca' => 'Classificação Anual',
            'cfd' => 'CFD',
            default => $this->tipoCalculo,
        };

        return [
            'turma_id' => $this->turma->id,
            'turma_nome' => $this->turma->nome,
            'trimestre' => $this->trimestre,
            'tipo_calculo' => $this->tipoCalculo,
            'calculadas' => $this->resultado['calculadas'] ?? 0,
            'pendentes' => $this->resultado['pendentes'] ?? 0,
            'message' => "Pauta processada: {$tipoLabel} da turma {$this->turma->nome} ({$this->resultado['calculadas']} calculadas)",
            'url' => route('pautas.index', [
                'turma_id' => $this->turma->id,
                'trimestre' => $this->trimestre,
            ]),
        ];
    }
}
