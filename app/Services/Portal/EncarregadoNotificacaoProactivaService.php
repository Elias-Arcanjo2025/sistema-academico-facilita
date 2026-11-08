<?php

namespace App\Services\Portal;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\Encarregado;
use App\Models\PortalConversa;
use App\Models\PortalMensagem;
use App\Models\PresencaRegisto;
use App\Models\User;
use App\Notifications\StaffAlertaAcademicoNotification;
use App\Services\PresencaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Alertas proactivos para encarregados no portal.
 */
class EncarregadoNotificacaoProactivaService
{
    public function __construct(
        private readonly EncarregadoPortalAcessoService $acesso,
        private readonly EncarregadoDashboardService $dashboard,
        private readonly PresencaService $presencaService,
    ) {}

    public function verificarEnotificar(User $user): void
    {
        if (! $user->encarregado_id || ! $user->getRoleNames()->contains('encarregado')) {
            return;
        }

        try {
            $encarregado = $this->acesso->encarregadoDoUser($user);
        } catch (\Throwable) {
            return;
        }

        $filhos = $this->acesso->filhos($encarregado);
        if ($filhos->isEmpty()) {
            return;
        }

        foreach ($filhos as $filho) {
            $this->alertarFaltas($user, $encarregado, $filho);
            $this->alertarNotas($user, $encarregado, $filho);
            $this->alertarScore($user, $encarregado, $filho);
            $this->alertarMensagens($user, $filho);
        }
    }

    /**
     * @return int Número de utilizadores processados
     */
    public function verificarTodosOsEncarregados(): int
    {
        $processados = 0;

        User::query()
            ->whereNotNull('encarregado_id')
            ->where('estado', 'activo')
            ->whereHas('roles', fn ($q) => $q->where('name', 'encarregado'))
            ->orderBy('id')
            ->chunkById(100, function (Collection $users) use (&$processados) {
                foreach ($users as $user) {
                    $this->verificarEnotificar($user);
                    $processados++;
                }
            });

        return $processados;
    }

    private function alertarFaltas(User $user, Encarregado $encarregado, Aluno $aluno): void
    {
        $chave = 'encarregado_notif.faltas.'.$user->id.'.'.$aluno->id.'.'.today()->format('Y-m-d');
        if (Cache::has($chave)) {
            return;
        }

        $anoId = Configuracao::getAnoLectivoActualId();
        $totais = $this->presencaService->resumoAluno((int) $aluno->id, $anoId)['totais'] ?? [];
        $injust = (int) ($totais[PresencaRegisto::FALTA_INJUSTIFICADA] ?? 0);

        if ($injust < 3) {
            return;
        }

        $nome = $this->nomeCurto($aluno);
        $this->enviar($user, 'encarregado_alerta_faltas', [
            'titulo' => 'Faltas — '.$nome,
            'mensagem' => $nome.' acumula '.$injust.' faltas injustificadas. Consulte a assiduidade.',
            'url' => route('portal.encarregado.assiduidade', $aluno, absolute: false),
        ]);

        Cache::put($chave, true, now()->endOfDay());
    }

    private function alertarNotas(User $user, Encarregado $encarregado, Aluno $aluno): void
    {
        $chave = 'encarregado_notif.notas.'.$user->id.'.'.$aluno->id.'.'.today()->format('Y-m-d');
        if (Cache::has($chave)) {
            return;
        }

        $cartao = $this->dashboard->cartaoFilho($encarregado, $aluno);
        if (! $cartao['pode_notas'] || $cartao['negativas'] < 1) {
            return;
        }

        $nome = $cartao['nome_curto'];
        $this->enviar($user, 'encarregado_alerta_notas', [
            'titulo' => 'Notas — '.$nome,
            'mensagem' => $nome.' tem '.$cartao['negativas'].' negativa(s) (CFD). Veja o dashboard do educando.',
            'url' => route('portal.encarregado.filho', $aluno, absolute: false),
        ]);

        Cache::put($chave, true, now()->endOfDay());
    }

    private function alertarScore(User $user, Encarregado $encarregado, Aluno $aluno): void
    {
        $chave = 'encarregado_notif.score.'.$user->id.'.'.$aluno->id.'.'.today()->format('Y-m-d');
        if (Cache::has($chave)) {
            return;
        }

        // Dedup 2 dias: se enviou ontem, não reenviar hoje
        $chaveOntem = 'encarregado_notif.score.'.$user->id.'.'.$aluno->id.'.'.today()->subDay()->format('Y-m-d');
        if (Cache::has($chaveOntem)) {
            Cache::put($chave, true, now()->endOfDay());

            return;
        }

        $cartao = $this->dashboard->cartaoFilho($encarregado, $aluno);
        $label = $cartao['score_label'];
        if (! in_array($label, ['Situação crítica', 'Precisa de acompanhamento'], true)) {
            return;
        }

        $nome = $cartao['nome_curto'];
        $this->enviar($user, 'encarregado_alerta_score', [
            'titulo' => 'Acompanhamento — '.$nome,
            'mensagem' => $nome.': '.$label.' (score '.$cartao['score'].'). Abra o portal para detalhes.',
            'url' => route('portal.encarregado.filho', $aluno, absolute: false),
        ]);

        Cache::put($chave, true, now()->addDays(2)->endOfDay());
    }

    private function alertarMensagens(User $user, Aluno $aluno): void
    {
        $chave = 'encarregado_notif.msgs.'.$user->id.'.'.$aluno->id.'.'.today()->format('Y-m-d');
        if (Cache::has($chave)) {
            return;
        }

        $conversaIds = PortalConversa::query()
            ->where('aluno_id', $aluno->id)
            ->where('encarregado_user_id', $user->id)
            ->pluck('id');

        if ($conversaIds->isEmpty()) {
            return;
        }

        $naoLidas = PortalMensagem::query()
            ->whereIn('conversa_id', $conversaIds)
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();

        if ($naoLidas < 1) {
            return;
        }

        $nome = $this->nomeCurto($aluno);
        $this->enviar($user, 'encarregado_alerta_mensagens', [
            'titulo' => 'Mensagens — '.$nome,
            'mensagem' => 'Tem '.$naoLidas.' mensagem(ns) da escola por ler sobre '.$nome.'.',
            'url' => route('portal.encarregado.mensagens.show', $aluno, absolute: false),
        ]);

        Cache::put($chave, true, now()->endOfDay());
    }

    /**
     * @param  array{titulo: string, mensagem: string, url: string}  $dados
     */
    private function enviar(User $user, string $tipo, array $dados): void
    {
        try {
            $user->notify(new StaffAlertaAcademicoNotification($tipo, [
                'titulo' => $dados['titulo'],
                'mensagem' => $dados['mensagem'],
                'url' => $dados['url'],
                'tipo_evento' => $tipo,
            ]));
        } catch (\Throwable $e) {
            Log::warning('Falha alerta encarregado: '.$e->getMessage(), [
                'user_id' => $user->id,
                'tipo' => $tipo,
            ]);
        }
    }

    private function nomeCurto(Aluno $aluno): string
    {
        $partes = preg_split('/\s+/', trim($aluno->nome)) ?: [];

        return count($partes) >= 2
            ? $partes[0].' '.$partes[count($partes) - 1]
            : ($partes[0] ?? $aluno->nome);
    }
}
