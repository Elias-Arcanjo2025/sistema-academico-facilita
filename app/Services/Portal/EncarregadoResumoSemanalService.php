<?php

namespace App\Services\Portal;

use App\Models\Aluno;
use App\Models\Encarregado;
use App\Models\Ocorrencia;
use App\Models\PortalConversa;
use App\Models\PortalMensagem;
use App\Models\PresencaRegisto;
use App\Models\User;
use App\Notifications\StaffAlertaAcademicoNotification;
use App\Services\WhatsApp\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EncarregadoResumoSemanalService
{
    public function __construct(
        private readonly EncarregadoPortalAcessoService $acesso,
        private readonly EncarregadoDashboardService $dashboard,
        private readonly WhatsAppService $whatsapp,
    ) {}

    /**
     * @return array{inicio: Carbon, fim: Carbon, rotulo: string, chave: string}
     */
    public function periodoSemana(?Carbon $referencia = null): array
    {
        $ref = ($referencia ?? now())->copy()->timezone(config('app.timezone'));
        $inicio = $ref->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $fim = $ref->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        return [
            'inicio' => $inicio,
            'fim' => $fim,
            'rotulo' => $inicio->format('d/m').' – '.$fim->format('d/m/Y'),
            'chave' => $inicio->format('o').'-W'.$inicio->format('W'),
        ];
    }

    /**
     * Página: na segunda mostra a semana que acabou; nos outros dias a semana actual.
     *
     * @return array{inicio: Carbon, fim: Carbon, rotulo: string, chave: string}
     */
    public function periodoParaExibicao(?Carbon $agora = null): array
    {
        $agora = ($agora ?? now())->copy();

        if ($agora->isMonday()) {
            return $this->periodoSemana($agora->copy()->subWeek());
        }

        return $this->periodoSemana($agora);
    }

    /**
     * Semana completa anterior (para o digest de segunda-feira).
     *
     * @return array{inicio: Carbon, fim: Carbon, rotulo: string, chave: string}
     */
    public function periodoDigest(?Carbon $agora = null): array
    {
        return $this->periodoSemana(($agora ?? now())->copy()->subWeek());
    }

    /**
     * @return array<string, mixed>
     */
    public function gerarParaFamilia(Encarregado $encarregado, ?array $periodo = null): array
    {
        $periodo ??= $this->periodoParaExibicao();
        $filhos = $this->acesso->filhos($encarregado);
        $periodoAnterior = $this->periodoSemana($periodo['inicio']->copy()->subWeek());

        $cards = $filhos->values()->map(function (Aluno $filho, int $i) use ($encarregado, $periodo, $periodoAnterior) {
            return $this->resumoFilho($encarregado, $filho, $i, $periodo, $periodoAnterior);
        });

        return [
            'periodo' => $periodo,
            'filhos' => $cards,
            'totais' => [
                'educandos' => $cards->count(),
                'faltas' => $cards->sum(fn (array $c) => $c['faltas_injustificadas'] + $c['faltas_justificadas']),
                'atrasos' => $cards->sum('atrasos'),
                'ocorrencias' => $cards->sum('ocorrencias'),
                'mensagens' => $cards->sum('mensagens'),
            ],
        ];
    }

    /**
     * Envia notificação do digest (semana anterior) se ainda não enviada esta semana ISO.
     */
    public function notificarDigest(User $encarregadoUser): bool
    {
        if (! $encarregadoUser->encarregado_id || ! $encarregadoUser->hasRole('encarregado')) {
            return false;
        }

        $periodo = $this->periodoDigest();
        $chave = 'encarregado_digest.'.$encarregadoUser->id.'.'.$periodo['chave'];
        if (Cache::has($chave)) {
            return false;
        }

        $encarregado = $this->acesso->encarregadoDoUser($encarregadoUser);
        $resumo = $this->gerarParaFamilia($encarregado, $periodo);

        if ($resumo['filhos']->isEmpty()) {
            return false;
        }

        $nomes = $resumo['filhos']->pluck('nome_curto')->take(2)->implode(', ');
        if ($resumo['filhos']->count() > 2) {
            $nomes .= '…';
        }

        $partes = [];
        if ($resumo['totais']['faltas'] > 0) {
            $partes[] = $resumo['totais']['faltas'].' falta(s)';
        }
        if ($resumo['totais']['ocorrencias'] > 0) {
            $partes[] = $resumo['totais']['ocorrencias'].' ocorrência(s)';
        }
        if ($resumo['totais']['mensagens'] > 0) {
            $partes[] = $resumo['totais']['mensagens'].' mensagem(ns)';
        }
        $corpo = $partes === []
            ? 'Semana '.$periodo['rotulo'].': ritmo estável. Abra o resumo completo.'
            : 'Semana '.$periodo['rotulo'].': '.implode(', ', $partes).'.';

        try {
            $encarregadoUser->notify(new StaffAlertaAcademicoNotification('encarregado_digest_semanal', [
                'titulo' => 'Resumo da semana — '.$nomes,
                'mensagem' => $corpo,
                'url' => route('portal.encarregado.resumo-semanal', absolute: false),
                'tipo_evento' => 'encarregado_digest_semanal',
            ]));
        } catch (\Throwable $e) {
            Log::warning('Falha digest encarregado: '.$e->getMessage(), [
                'user_id' => $encarregadoUser->id,
            ]);

            return false;
        }

        $this->enviarDigestWhatsApp($encarregado, $nomes, $periodo['rotulo'], $corpo);

        Cache::put($chave, true, now()->addDays(10));

        return true;
    }

    private function enviarDigestWhatsApp(
        Encarregado $encarregado,
        string $nomes,
        string $periodoRotulo,
        string $corpo,
    ): void {
        if (! $encarregado->whatsapp_opt_in || ! $encarregado->telefone) {
            return;
        }

        if (! $this->whatsapp->enabled()) {
            return;
        }

        try {
            $this->whatsapp->notifyEncarregado($encarregado, 'resumo_semanal', [
                'aluno' => $nomes,
                'periodo' => $periodoRotulo,
                'resumo' => $corpo,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Falha digest WhatsApp encarregado: '.$e->getMessage(), [
                'encarregado_id' => $encarregado->id,
            ]);
        }
    }

    /**
     * @return int Número de notificações enviadas
     */
    public function notificarTodosOsEncarregados(): int
    {
        $enviados = 0;

        User::query()
            ->whereNotNull('encarregado_id')
            ->where('estado', 'activo')
            ->whereHas('roles', fn ($q) => $q->where('name', 'encarregado'))
            ->orderBy('id')
            ->chunkById(100, function (Collection $users) use (&$enviados) {
                foreach ($users as $user) {
                    if ($this->notificarDigest($user)) {
                        $enviados++;
                    }
                }
            });

        return $enviados;
    }

    /**
     * @param  array{inicio: Carbon, fim: Carbon, rotulo: string, chave: string}  $periodo
     * @param  array{inicio: Carbon, fim: Carbon, rotulo: string, chave: string}  $periodoAnterior
     * @return array<string, mixed>
     */
    private function resumoFilho(
        Encarregado $encarregado,
        Aluno $aluno,
        int $indice,
        array $periodo,
        array $periodoAnterior,
    ): array {
        $cartao = $this->dashboard->cartaoFilho($encarregado, $aluno, $indice);
        $presencas = $this->contarPresencas($aluno, $periodo['inicio'], $periodo['fim']);
        $presencasAnt = $this->contarPresencas($aluno, $periodoAnterior['inicio'], $periodoAnterior['fim']);

        $faltasActual = $presencas['injustificadas'] + $presencas['justificadas'];
        $faltasAnt = $presencasAnt['injustificadas'] + $presencasAnt['justificadas'];
        $tendencia = match (true) {
            $faltasActual < $faltasAnt => 'Menos faltas do que na semana anterior.',
            $faltasActual > $faltasAnt => 'Mais faltas do que na semana anterior.',
            default => 'Faltas iguais à semana anterior.',
        };

        $ocorrencias = Ocorrencia::query()
            ->where('aluno_id', $aluno->id)
            ->whereBetween('data', [
                $periodo['inicio']->toDateString(),
                $periodo['fim']->toDateString(),
            ])
            ->count();

        $mensagens = $this->contarMensagensSemana($encarregado, $aluno, $periodo['inicio'], $periodo['fim']);

        return [
            'id' => $aluno->id,
            'nome' => $cartao['nome'],
            'nome_curto' => $cartao['nome_curto'],
            'turma' => $cartao['turma'],
            'cor' => $cartao['cor'],
            'url' => $cartao['url'],
            'url_assiduidade' => route('portal.encarregado.assiduidade', $aluno),
            'url_ocorrencias' => route('portal.encarregado.ocorrencias', $aluno),
            'url_mensagens' => route('portal.encarregado.mensagens.show', $aluno),
            'media_mt' => $cartao['media_mt'],
            'pode_notas' => $cartao['pode_notas'],
            'score' => $cartao['score'],
            'score_label' => $cartao['score_label'],
            'faltas_injustificadas' => $presencas['injustificadas'],
            'faltas_justificadas' => $presencas['justificadas'],
            'atrasos' => $presencas['atrasos'],
            'presentes' => $presencas['presentes'],
            'ocorrencias' => $ocorrencias,
            'mensagens' => $mensagens,
            'tendencia_faltas' => $tendencia,
        ];
    }

    /**
     * @return array{injustificadas: int, justificadas: int, atrasos: int, presentes: int}
     */
    private function contarPresencas(Aluno $aluno, Carbon $inicio, Carbon $fim): array
    {
        $rows = PresencaRegisto::query()
            ->where('aluno_id', $aluno->id)
            ->whereHas('aula', function ($q) use ($inicio, $fim) {
                $q->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()]);
            })
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return [
            'injustificadas' => (int) ($rows[PresencaRegisto::FALTA_INJUSTIFICADA] ?? 0),
            'justificadas' => (int) ($rows[PresencaRegisto::FALTA_JUSTIFICADA] ?? 0),
            'atrasos' => (int) ($rows[PresencaRegisto::ATRASO] ?? 0),
            'presentes' => (int) ($rows[PresencaRegisto::PRESENTE] ?? 0),
        ];
    }

    private function contarMensagensSemana(
        Encarregado $encarregado,
        Aluno $aluno,
        Carbon $inicio,
        Carbon $fim,
    ): int {
        $userIds = User::query()
            ->where('encarregado_id', $encarregado->id)
            ->pluck('id');

        if ($userIds->isEmpty()) {
            return 0;
        }

        $conversaIds = PortalConversa::query()
            ->where('aluno_id', $aluno->id)
            ->whereIn('encarregado_user_id', $userIds)
            ->pluck('id');

        if ($conversaIds->isEmpty()) {
            return 0;
        }

        return PortalMensagem::query()
            ->whereIn('conversa_id', $conversaIds)
            ->whereBetween('created_at', [$inicio, $fim])
            ->count();
    }
}
