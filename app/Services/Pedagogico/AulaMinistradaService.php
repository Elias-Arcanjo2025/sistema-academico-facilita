<?php

namespace App\Services\Pedagogico;

use App\Models\Pedagogico\PedAula;
use App\Models\Pedagogico\PedPlanificacao;
use Carbon\Carbon;

class AulaMinistradaService
{
    public function registarMinistrada(PedAula $aula, string $data, array $dados = []): PedAula
    {
        if (! in_array($aula->estado, [PedAula::ESTADO_PLANEADA, PedAula::ESTADO_ADIADA], true)) {
            throw new \Exception('Apenas aulas planeadas ou adiadas podem ser registadas como ministradas.');
        }

        $aula->update([
            'data' => $data,
            'estado' => PedAula::ESTADO_MINISTRADA,
            'tema_ministrado' => $dados['tema_ministrado'] ?? $aula->tema_ministrado,
            'conteudo_ministrado' => $dados['conteudo_ministrado'] ?? $aula->conteudo_ministrado,
            'observacoes' => $dados['observacoes'] ?? $aula->observacoes,
        ]);

        return $aula->fresh();
    }

    public function adiar(PedAula $aula, string $novaData, ?string $motivo = null): PedAula
    {
        if (! in_array($aula->estado, [PedAula::ESTADO_PLANEADA, PedAula::ESTADO_ADIADA], true)) {
            throw new \Exception('Apenas aulas planeadas ou adiadas podem ser adiadas.');
        }

        $observacoes = $aula->observacoes;
        if ($motivo) {
            $observacoes = trim(($observacoes ? $observacoes."\n" : '')."Adiada: {$motivo}");
        }

        $aula->update([
            'data' => $novaData,
            'estado' => PedAula::ESTADO_ADIADA,
            'observacoes' => $observacoes,
        ]);

        return $aula->fresh();
    }

    public function cancelar(PedAula $aula, ?string $motivo = null): PedAula
    {
        if ($aula->estado === PedAula::ESTADO_MINISTRADA) {
            throw new \Exception('Não é possível cancelar uma aula já ministrada.');
        }

        $observacoes = $aula->observacoes;
        if ($motivo) {
            $observacoes = trim(($observacoes ? $observacoes."\n" : '')."Cancelada: {$motivo}");
        }

        $aula->update([
            'estado' => PedAula::ESTADO_CANCELADA,
            'observacoes' => $observacoes,
        ]);

        return $aula->fresh();
    }

    public function percentagemExecucao(PedPlanificacao $planificacao): float
    {
        $hoje = Carbon::today();

        $relevantes = $planificacao->aulas()
            ->where('estado', '!=', PedAula::ESTADO_CANCELADA)
            ->where('data', '<=', $hoje)
            ->get();

        if ($relevantes->isEmpty()) {
            return 0.0;
        }

        $ministradas = $relevantes->where('estado', PedAula::ESTADO_MINISTRADA)->count();

        return round(($ministradas / $relevantes->count()) * 100, 2);
    }
}
