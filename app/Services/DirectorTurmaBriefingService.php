<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\Ocorrencia;
use App\Models\PortalConversa;
use App\Models\PortalMensagem;
use App\Models\PresencaRegisto;
use App\Models\User;

/**
 * Briefing semanal para o director de turma: famílias a contactar.
 */
class DirectorTurmaBriefingService
{
    /**
     * @return array{periodo: string, alertas: list<array<string, mixed>>, total: int}
     */
    public function briefing(User $director): array
    {
        $turmaIds = $director->turmasComoDirector()
            ->where('director_turma.activo', true)
            ->where('turmas.activo', true)
            ->pluck('turmas.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($turmaIds->isEmpty()) {
            return ['periodo' => 'esta semana', 'alertas' => [], 'total' => 0];
        }

        $anoId = Configuracao::getAnoLectivoActualId();
        $notaMinima = (float) (Configuracao::get('nota_minima_aprovacao') ?: 10);
        $desde = now()->subDays(14)->toDateString();
        $alertas = [];

        $alunos = Aluno::query()
            ->with(['turma:id,nome,codigo', 'encarregados.user'])
            ->whereIn('turma_id', $turmaIds)
            ->where('activo', true)
            ->orderBy('nome')
            ->get();

        foreach ($alunos as $aluno) {
            $itens = [];

            $faltas = PresencaRegisto::query()
                ->where('aluno_id', $aluno->id)
                ->where('estado', PresencaRegisto::FALTA_INJUSTIFICADA)
                ->whereHas('aula', function ($q) use ($desde, $anoId) {
                    $q->where('data', '>=', $desde)
                        ->when($anoId, fn ($qq) => $qq->where('ano_lectivo_id', $anoId));
                })
                ->count();

            if ($faltas >= 2) {
                $itens[] = $faltas.' falta(s) injustificada(s) (14 dias)';
            }

            $ocorrencias = Ocorrencia::query()
                ->where('aluno_id', $aluno->id)
                ->where('data', '>=', $desde)
                ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
                ->count();

            if ($ocorrencias > 0) {
                $itens[] = $ocorrencias.' ocorrência(ões) recente(s)';
            }

            $negativas = Nota::query()
                ->where('aluno_id', $aluno->id)
                ->whereNull('archived_at')
                ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
                ->whereIn('tipo', [Nota::TIPO_MT, Nota::TIPO_CFD])
                ->where('valor', '<', $notaMinima)
                ->count();

            if ($negativas >= 2) {
                $itens[] = $negativas.' nota(s) abaixo do mínimo';
            }

            $msgsPendentes = $this->mensagensEncarregadoNaoRespondidas($aluno);
            if ($msgsPendentes > 0) {
                $itens[] = $msgsPendentes.' mensagem(ns) do encarregado por responder';
            }

            if ($itens === []) {
                continue;
            }

            $enc = $aluno->encarregadoPrincipal();
            $alertas[] = [
                'aluno_id' => $aluno->id,
                'aluno' => $aluno->nome,
                'turma' => $aluno->turma?->codigo ?? $aluno->turma?->nome ?? '—',
                'encarregado' => $enc?->nome ?? 'Sem encarregado',
                'tem_portal' => (bool) $enc?->user,
                'motivos' => $itens,
                'url_aluno' => route('alunos.show', $aluno),
                'url_mensagens' => $enc?->user
                    ? route('mensagens-encarregados.create', ['aluno_id' => $aluno->id])
                    : null,
            ];
        }

        // Priorizar quem tem mais motivos
        usort($alertas, fn ($a, $b) => count($b['motivos']) <=> count($a['motivos']));

        return [
            'periodo' => 'últimos 14 dias',
            'alertas' => array_slice($alertas, 0, 12),
            'total' => count($alertas),
        ];
    }

    private function mensagensEncarregadoNaoRespondidas(Aluno $aluno): int
    {
        $conversas = PortalConversa::query()
            ->where('aluno_id', $aluno->id)
            ->get(['id', 'encarregado_user_id']);

        if ($conversas->isEmpty()) {
            return 0;
        }

        $total = 0;
        foreach ($conversas as $conversa) {
            $total += PortalMensagem::query()
                ->where('conversa_id', $conversa->id)
                ->where('user_id', $conversa->encarregado_user_id)
                ->whereNull('read_at')
                ->count();
        }

        return $total;
    }
}
