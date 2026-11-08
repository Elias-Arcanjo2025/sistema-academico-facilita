<?php

namespace App\Jobs\Pedagogico;

use App\Models\Pedagogico\PedGeracaoIA;
use App\Models\Pedagogico\PedPlanificacao;
use App\Models\User;
use App\Services\Pedagogico\IaGenerationLogger;
use App\Services\Pedagogico\ProvaGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GerarProvaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public int $planificacaoId,
        public string $tipo,
        public ?int $userId = null,
        public array $config = [],
        public ?int $geracaoIaId = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(ProvaGeneratorService $service): void
    {
        $planificacao = PedPlanificacao::findOrFail($this->planificacaoId);
        $user = $this->userId ? User::findOrFail($this->userId) : null;

        $service->gerar($planificacao, $this->tipo, $user, $this->config);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GerarProvaJob falhou', [
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
