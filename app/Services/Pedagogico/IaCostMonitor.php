<?php

namespace App\Services\Pedagogico;

use App\Models\Pedagogico\PedGeracaoIA;
use Illuminate\Support\Facades\Log;

class IaCostMonitor
{
    public function estimarCusto(string $model, int $tokensIn, int $tokensOut): float
    {
        $precos = config('pedagogico.custo_por_milhao', [
            'default' => ['input' => 0.10, 'output' => 0.40],
        ]);

        $tarifa = $precos[$model] ?? $precos['default'];
        $inputCost = ($tokensIn / 1_000_000) * ($tarifa['input'] ?? 0.10);
        $outputCost = ($tokensOut / 1_000_000) * ($tarifa['output'] ?? 0.40);

        return round($inputCost + $outputCost, 6);
    }

    public function registarConclusao(PedGeracaoIA $geracao, array $meta): void
    {
        $model = $meta['model'] ?? $geracao->model ?? config('openrouter.default_model');
        $tokensIn = (int) ($meta['tokens_in'] ?? 0);
        $tokensOut = (int) ($meta['tokens_out'] ?? 0);
        $custo = $this->estimarCusto($model, $tokensIn, $tokensOut);

        $geracao->update([
            'custo_estimado' => $custo,
            'model' => $model,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'duracao_ms' => $meta['duration_ms'] ?? $meta['duracao_ms'] ?? 0,
        ]);

        $limiar = (float) config('pedagogico.alerta_custo_diario_usd', 5.0);
        $totalDia = PedGeracaoIA::whereDate('created_at', today())
            ->where('status', PedGeracaoIA::STATUS_COMPLETED)
            ->sum('custo_estimado');

        if ($totalDia >= $limiar) {
            Log::warning('Pedagógico IA: custo diário estimado excedeu o limiar', [
                'total_usd' => $totalDia,
                'limiar_usd' => $limiar,
            ]);
        }
    }
}
