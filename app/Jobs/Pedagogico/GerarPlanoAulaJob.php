<?php

namespace App\Jobs\Pedagogico;

use App\Models\Pedagogico\PedAula;
use App\Models\Pedagogico\PedGeracaoIA;
use App\Models\Pedagogico\PedPlanificacao;
use App\Models\Pedagogico\PedQuinzena;
use App\Models\User;
use App\Services\Pedagogico\IaGenerationLogger;
use App\Services\Pedagogico\PlanoAulaGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GerarPlanoAulaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public int $planificacaoId,
        public ?int $quinzenaId = null,
        public ?int $aulaId = null,
        public ?int $userId = null,
        public ?int $geracaoIaId = null,
        public int $tempos = 1,
    ) {
        $this->onQueue('default');
    }

    public function handle(PlanoAulaGeneratorService $service): void
    {
        $planificacao = PedPlanificacao::findOrFail($this->planificacaoId);
        $quinzena = $this->quinzenaId ? PedQuinzena::find($this->quinzenaId) : null;
        $aula = $this->aulaId ? PedAula::find($this->aulaId) : null;
        $user = $this->userId ? User::findOrFail($this->userId) : null;

        $service->gerar($planificacao, $quinzena, $aula, $user, $this->tempos);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GerarPlanoAulaJob falhou', [
            'planificacao_id' => $this->planificacaoId,
            'erro' => $exception->getMessage(),
        ]);

        $this->marcarGeracaoComoFalhada($exception->getMessage());
    }

    protected function marcarGeracaoComoFalhada(string $mensagem): void
    {
        if (! $this->geracaoIaId) {
            return;
        }

        $geracao = PedGeracaoIA::find($this->geracaoIaId);
        if ($geracao && $geracao->status === PedGeracaoIA::STATUS_PROCESSING) {
            app(IaGenerationLogger::class)->falhar($geracao, $mensagem);
        }
    }
}
