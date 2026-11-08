<?php

namespace App\Services;

use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\User;
use App\Notifications\AlunoAlertaAcademicoNotification;
use App\Support\PautaResultadoTransicao;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Alertas proactivos para alunos no portal.
 */
class AlunoNotificacaoProactivaService
{
    public function __construct(
        private RecuperacaoTransicaoService $transicaoService,
        private SaudeAlunoTurmaService $saudeAlunoTurma,
    ) {}

    public function verificarEnotificar(User $user): void
    {
        if (! $user->hasRole('aluno') || ! $user->aluno_id) {
            return;
        }

        $aluno = $user->aluno;
        if (! $aluno) {
            return;
        }

        $this->alertarNegativas($user, $aluno);
        $this->alertarRecuperacaoPendente($user, $aluno);
        $this->alertarRiscoTransicao($user, $aluno);
        $this->alertarSaudeTurmaBaixa($user, $aluno);
    }

    private function alertarNegativas(User $user, $aluno): void
    {
        $chave = 'aluno_notif.negativas.'.$user->id.'.'.today()->format('Y-m-d');
        if (Cache::has($chave)) {
            return;
        }

        $anoLectivoId = Configuracao::getAnoLectivoActualId();
        $notaMinima = (float) (Configuracao::get('nota_minima_aprovacao') ?: 10);
        $negativas = $aluno->notas()
            ->whereNull('archived_at')
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereIn('tipo', [Nota::TIPO_MT, Nota::TIPO_CFD, Nota::TIPO_CA10, Nota::TIPO_CA11, Nota::TIPO_CA12])
            ->where('valor', '<', $notaMinima)
            ->count();

        if ($negativas < 2) {
            return;
        }

        $this->enviar($user, 'negativas', [
            'titulo' => 'Atenção ao desempenho',
            'mensagem' => "Tem {$negativas} disciplina(s) com nota abaixo do mínimo. Consulte as suas notas.",
            'url' => route('portal.aluno.notas'),
        ]);

        Cache::put($chave, true, now()->endOfDay());
    }

    private function alertarRecuperacaoPendente(User $user, $aluno): void
    {
        $chave = 'aluno_notif.recuperacao.'.$user->id.'.'.today()->format('Y-m-d');
        if (Cache::has($chave)) {
            return;
        }

        $anoLectivoId = Configuracao::getAnoLectivoActualId();
        $notaMinima = (float) (Configuracao::get('nota_minima_aprovacao') ?: 10);
        $pendentes = $aluno->notas()
            ->whereNull('archived_at')
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereIn('tipo', [Nota::TIPO_RECUPERACAO, Nota::TIPO_RECURSO])
            ->where('valor', '<', $notaMinima)
            ->count();

        if ($pendentes < 1) {
            return;
        }

        $this->enviar($user, 'recuperacao_pendente', [
            'titulo' => 'Recuperação / recurso',
            'mensagem' => 'Tem resultado(s) de exame abaixo do mínimo. Confirme a situação com a escola.',
            'url' => route('portal.aluno.notas'),
        ]);

        Cache::put($chave, true, now()->endOfDay());
    }

    private function alertarRiscoTransicao(User $user, $aluno): void
    {
        if (Configuracao::getTrimestreActual() < 3) {
            return;
        }

        $chave = 'aluno_notif.transicao.'.$user->id.'.'.today()->format('Y-m-d');
        if (Cache::has($chave)) {
            return;
        }

        $saude = $this->saudeAlunoTurma->indicadores($aluno);
        $resultado = $saude['resultado_transicao'] ?? null;

        if ($resultado !== PautaResultadoTransicao::RESULTADO_N_TRANSITA
            && ! PautaResultadoTransicao::ehTransitaComDeficiencia($resultado ?? '')) {
            return;
        }

        $mensagem = $resultado === PautaResultadoTransicao::RESULTADO_N_TRANSITA
            ? 'A sua situação de transição T3 indica N/TRANSITA. Fale com o director de turma.'
            : 'Transição T3 com deficiência — pode precisar de recuperação ou reforço.';

        $this->enviar($user, 'risco_transicao', [
            'titulo' => 'Situação de transição T3',
            'mensagem' => $mensagem,
            'url' => route('portal.aluno.executivo-ia'),
        ]);

        Cache::put($chave, true, now()->endOfDay());
    }

    private function alertarSaudeTurmaBaixa(User $user, $aluno): void
    {
        $chave = 'aluno_notif.saude.'.$user->id.'.'.today()->format('Y-m-d');
        if (Cache::has($chave)) {
            return;
        }

        $saude = $this->saudeAlunoTurma->indicadores($aluno);
        $score = (int) ($saude['score'] ?? 100);

        if ($score >= 50) {
            return;
        }

        $this->enviar($user, 'saude_baixa', [
            'titulo' => 'Saúde académica da turma',
            'mensagem' => "O seu indicador pessoal está em {$score}/100. Revise as disciplinas com notas baixas.",
            'url' => route('portal.aluno.dashboard'),
        ]);

        Cache::put($chave, true, now()->endOfDay());
    }

    /**
     * @param  array{titulo:string, mensagem:string, url:string}  $dados
     */
    private function enviar(User $user, string $tipo, array $dados): void
    {
        try {
            $user->notify(new AlunoAlertaAcademicoNotification($tipo, $dados));
        } catch (\Throwable $e) {
            Log::warning('Falha notificação aluno: '.$e->getMessage(), ['user_id' => $user->id, 'tipo' => $tipo]);
        }
    }
}
