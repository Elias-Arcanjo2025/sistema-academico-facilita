<?php

namespace App\Services\Assistente;

use App\Models\AssistenteConversa;
use App\Models\AssistenteMensagem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssistantAuditService
{
    /**
     * @return array<string, mixed>
     */
    public function resumo(int $dias = 30): array
    {
        $desde = now()->subDays($dias)->startOfDay();

        $mensagens = AssistenteMensagem::query()
            ->where('created_at', '>=', $desde);

        $totalMensagens = (clone $mensagens)->count();
        $totalUser = (clone $mensagens)->where('papel', 'user')->count();
        $totalAssistant = (clone $mensagens)->where('papel', 'assistant')->count();
        $tokensIn = (int) (clone $mensagens)->sum('tokens_in');
        $tokensOut = (int) (clone $mensagens)->sum('tokens_out');

        $feedbackUtil = (clone $mensagens)->where('feedback', 'util')->count();
        $feedbackInutil = (clone $mensagens)->where('feedback', 'inutil')->count();

        $recusas = (clone $mensagens)
            ->where('papel', 'assistant')
            ->whereNotNull('contexto_usado')
            ->get()
            ->filter(fn (AssistenteMensagem $m) => ! empty(($m->contexto_usado ?? [])['bloqueio_perfil']))
            ->count();

        $conversas = AssistenteConversa::query()
            ->where('created_at', '>=', $desde)
            ->count();

        $utilizadoresActivos = AssistenteConversa::query()
            ->where('updated_at', '>=', $desde)
            ->distinct('user_id')
            ->count('user_id');

        return [
            'periodo_dias' => $dias,
            'desde' => $desde->toDateString(),
            'conversas' => $conversas,
            'mensagens_total' => $totalMensagens,
            'perguntas' => $totalUser,
            'respostas' => $totalAssistant,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'tokens_total' => $tokensIn + $tokensOut,
            'feedback_util' => $feedbackUtil,
            'feedback_inutil' => $feedbackInutil,
            'recusas_perfil' => $recusas,
            'utilizadores_activos' => $utilizadoresActivos,
        ];
    }

    /**
     * @return Collection<int, object>
     */
    public function porDia(int $dias = 30): Collection
    {
        $desde = now()->subDays($dias)->startOfDay();

        return AssistenteMensagem::query()
            ->select(DB::raw('DATE(created_at) as data'), DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', $desde)
            ->where('papel', 'user')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('data')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function topUtilizadores(int $dias = 30, int $limite = 15): Collection
    {
        $desde = now()->subDays($dias)->startOfDay();

        return AssistenteMensagem::query()
            ->select(
                'assistente_conversas.user_id',
                DB::raw('COUNT(*) as perguntas'),
                DB::raw('SUM(assistente_mensagens.tokens_in + assistente_mensagens.tokens_out) as tokens')
            )
            ->join('assistente_conversas', 'assistente_conversas.id', '=', 'assistente_mensagens.conversa_id')
            ->where('assistente_mensagens.created_at', '>=', $desde)
            ->where('assistente_mensagens.papel', 'user')
            ->groupBy('assistente_conversas.user_id')
            ->orderByDesc('perguntas')
            ->limit($limite)
            ->get()
            ->map(function ($row) {
                $user = User::query()->find($row->user_id);

                return (object) [
                    'user_id' => $row->user_id,
                    'nome' => $user?->name ?? 'Utilizador #'.$row->user_id,
                    'email' => $user?->email,
                    'cargos' => $user?->getRoleNames()->implode(', '),
                    'perguntas' => (int) $row->perguntas,
                    'tokens' => (int) $row->tokens,
                ];
            });
    }

    /**
     * @return Collection<int, object>
     */
    public function porPerfil(int $dias = 30): Collection
    {
        $desde = now()->subDays($dias)->startOfDay();

        $userIds = AssistenteConversa::query()
            ->where('updated_at', '>=', $desde)
            ->pluck('user_id')
            ->unique();

        $access = app(AssistantAccess::class);

        return $userIds
            ->map(function ($userId) use ($access, $desde) {
                $user = User::query()->find($userId);
                if (! $user) {
                    return null;
                }

                $perguntas = AssistenteMensagem::query()
                    ->where('papel', 'user')
                    ->where('created_at', '>=', $desde)
                    ->whereHas('conversa', fn ($q) => $q->where('user_id', $userId))
                    ->count();

                return (object) [
                    'perfil' => $access->perfilResposta($user),
                    'perguntas' => $perguntas,
                ];
            })
            ->filter()
            ->groupBy('perfil')
            ->map(fn (Collection $grupo, string $perfil) => (object) [
                'perfil' => $perfil,
                'perguntas' => $grupo->sum('perguntas'),
                'utilizadores' => $grupo->count(),
            ])
            ->values()
            ->sortByDesc('perguntas')
            ->values();
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function conversasRecentes(int $dias = 30, int $porPagina = 20)
    {
        $desde = now()->subDays($dias)->startOfDay();

        return AssistenteConversa::query()
            ->with(['user'])
            ->withCount([
                'mensagens',
                'mensagens as perguntas_count' => fn ($q) => $q->where('papel', 'user'),
            ])
            ->withSum('mensagens as tokens_in_sum', 'tokens_in')
            ->withSum('mensagens as tokens_out_sum', 'tokens_out')
            ->where('updated_at', '>=', $desde)
            ->orderByDesc('updated_at')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
