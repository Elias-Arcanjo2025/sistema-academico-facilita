<?php

namespace App\Services;

use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\StaffAlertaAcademicoNotification;
use App\Services\Assistente\AssistantAccess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Notificações proactivas para professores e administradores.
 */
class StaffNotificacaoService
{
    public function __construct(
        private RecuperacaoTransicaoService $transicaoService,
        private AssistantAccess $access,
        private SaudeTurmasService $saudeTurmas,
    ) {}

    /**
     * Verifica alertas administrativos (máx. 1x/dia por tipo).
     */
    public function verificarEnotificarAdmin(): void
    {
        $this->alertarContasPendentes();
        $this->alertarAlunosRiscoTransicao();
        $this->alertarTrimestreFecho();
    }

    /**
     * @deprecated Use verificarEnotificarAdmin() ou ProactiveNotificacaoDispatcher
     */
    public function verificarEnotificar(): void
    {
        $this->verificarEnotificarAdmin();
    }

    /**
     * Alertas proactivos para professores, directores e coordenadores.
     */
    public function verificarEnotificarProfessor(User $user): void
    {
        $this->alertarProfessorCoberturaBaixa($user);
        $this->alertarProfessorAlunosCriticos($user);
    }

    private function alertarContasPendentes(): void
    {
        $chave = 'staff_notif.contas_pendentes.'.today()->format('Y-m-d');
        if (Cache::has($chave)) {
            return;
        }

        $pendentes = User::where('estado', 'pendente')->count();
        if ($pendentes < 1) {
            return;
        }

        $admins = User::role(['admin_full', 'directora_geral'])->where('estado', 'activo')->get();
        foreach ($admins as $admin) {
            $this->enviar($admin, 'contas_pendentes', [
                'titulo' => 'Contas pendentes de aprovação',
                'mensagem' => "{$pendentes} utilizador(es) aguardam aprovação.",
                'url' => route('users.index'),
            ]);
        }

        Cache::put($chave, true, now()->endOfDay());
    }

    private function alertarAlunosRiscoTransicao(): void
    {
        $chave = 'staff_notif.risco_transicao.'.today()->format('Y-m-d');
        if (Cache::has($chave)) {
            return;
        }

        $anoLectivo = Configuracao::getAnoLectivoActual();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);
        $trimestre = Configuracao::getTrimestreActual();

        if ($trimestre < 3) {
            return;
        }

        $turmas = Turma::with('classe')
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->get();

        foreach ($turmas as $turma) {
            $classe = $this->transicaoService->classeNumero($turma);
            if (! in_array($classe, [10, 11, 12], true)) {
                continue;
            }

            $reprovados = $this->transicaoService->analisarTurma($turma, $anoLectivo)
                ->filter(fn ($a) => ($a['resultado'] ?? '') === 'N/TRANSITA')
                ->count();

            if ($reprovados < 3) {
                continue;
            }

            $directores = User::role('director_turma')
                ->where('estado', 'activo')
                ->whereHas('directorTurmas', fn ($q) => $q->where('turma_id', $turma->id)->where('activo', true))
                ->get();

            foreach ($directores as $dir) {
                $this->enviar($dir, "risco_{$turma->id}", [
                    'titulo' => "Risco de transição — {$turma->nome}",
                    'mensagem' => "{$reprovados} aluno(s) com N/TRANSITA na pauta T3.",
                    'url' => route('pautas.index', ['turma_id' => $turma->id, 'trimestre' => 3]),
                ]);
            }
        }

        Cache::put($chave, true, now()->endOfDay());
    }

    private function alertarTrimestreFecho(): void
    {
        $trimestre = Configuracao::getTrimestreActual();
        if (Configuracao::trimestreAbertoParaLancamento($trimestre)) {
            return;
        }

        $chave = 'staff_notif.trim_fechado.'.$trimestre.'.'.today()->format('Y-m-d');
        if (Cache::has($chave)) {
            return;
        }

        $notasHoje = Nota::whereDate('created_at', today())->count();
        if ($notasHoje < 5) {
            return;
        }

        $gestores = User::role(['admin_full', 'subdiretor_pedagogico'])->where('estado', 'activo')->get();
        foreach ($gestores as $g) {
            $this->enviar($g, 'trim_fechado', [
                'titulo' => 'Lançamentos com trimestre fechado',
                'mensagem' => "{$notasHoje} nota(s) registadas hoje com o {$trimestre}.º trimestre fechado.",
                'url' => route('configuracoes.index'),
            ]);
        }

        Cache::put($chave, true, now()->endOfDay());
    }

    private function alertarProfessorCoberturaBaixa(User $user): void
    {
        $chave = 'staff_notif.prof_cobertura.'.$user->id.'.'.today()->format('Y-m-d');
        if (Cache::has($chave)) {
            return;
        }

        $saude = $this->saudeTurmas->indicadores($user);
        $cobertura = (float) ($saude['lancamento']['cobertura_pct'] ?? 100);

        if ($cobertura >= 60 || ($saude['lancamento']['total_alunos'] ?? 0) < 1) {
            return;
        }

        $trimestre = $saude['trimestre_actual'] ?? Configuracao::getTrimestreActual();
        $this->enviar($user, 'cobertura_baixa', [
            'titulo' => 'Cobertura de lançamento baixa',
            'mensagem' => "Cobertura de {$cobertura}% no {$trimestre}.º trimestre nas suas turmas. Considere completar MAC/PP/PT.",
            'url' => route('notas.lancar'),
        ]);

        Cache::put($chave, true, now()->endOfDay());
    }

    private function alertarProfessorAlunosCriticos(User $user): void
    {
        $chave = 'staff_notif.prof_criticos.'.$user->id.'.'.today()->format('Y-m-d');
        if (Cache::has($chave)) {
            return;
        }

        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome(Configuracao::getAnoLectivoActual());
        $turmaIds = $this->access->getTurmasIdsAcessiveis($user, $anoLectivoId);

        if ($turmaIds === []) {
            return;
        }

        $criticos = Nota::query()
            ->whereIn('turma_id', $turmaIds)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('tipo', Nota::TIPO_MT)
            ->where('valor', '<', 10)
            ->whereNull('archived_at')
            ->select('aluno_id')
            ->groupBy('aluno_id')
            ->havingRaw('COUNT(*) >= 3')
            ->get()
            ->count();

        if ($criticos < 1) {
            return;
        }

        $this->enviar($user, 'alunos_criticos', [
            'titulo' => 'Alunos em situação crítica',
            'mensagem' => "{$criticos} aluno(s) com 3 ou mais negativas nas suas turmas.",
            'url' => route('dashboard'),
        ]);

        Cache::put($chave, true, now()->endOfDay());
    }

    /**
     * @param  array{titulo:string, mensagem:string, url:string}  $dados
     */
    private function enviar(User $user, string $tipo, array $dados): void
    {
        try {
            $user->notify(new StaffAlertaAcademicoNotification($tipo, $dados));
        } catch (\Throwable $e) {
            Log::warning('Falha notificação staff: '.$e->getMessage(), ['user_id' => $user->id, 'tipo' => $tipo]);
        }
    }
}
