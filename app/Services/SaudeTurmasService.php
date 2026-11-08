<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\Turma;
use App\Models\User;
use App\Services\Assistente\AssistantAccess;
use App\Support\PautaResultadoTransicao;
use Illuminate\Support\Facades\Cache;

/**
 * Indicadores de saúde académica ao nível das turmas acessíveis ao utilizador.
 */
class SaudeTurmasService
{
    public function __construct(
        private AssistantAccess $access,
        private RecuperacaoTransicaoService $transicaoService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indicadores(User $user, ?string $anoLectivo = null): array
    {
        $anoLectivo = $anoLectivo ?? Configuracao::getAnoLectivoActual();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);

        if (! $anoLectivoId) {
            return $this->vazio($user, $anoLectivo);
        }

        $turmaIds = $this->access->getTurmasIdsAcessiveis($user, $anoLectivoId);

        if ($turmaIds === []) {
            return $this->vazio($user, $anoLectivo);
        }

        return Cache::remember(
            'saude_turmas.'.$user->id.'.'.$anoLectivoId.'.'.md5(implode(',', $turmaIds)),
            300,
            function () use ($user, $anoLectivo, $anoLectivoId, $turmaIds) {
                $turmas = Turma::with(['classe', 'curso'])
                    ->whereIn('id', $turmaIds)
                    ->where('activo', true)
                    ->orderBy('nome')
                    ->get();

                $transicao = $this->resumoTransicao($turmas, $anoLectivo);
                $lancamento = $this->resumoLancamento($turmaIds, $anoLectivoId);
                $score = $this->calcularScore($transicao, $lancamento);

                return [
                    'titulo' => $this->tituloParaUtilizador($user),
                    'escopo' => count($turmas).' turma(s)',
                    'score' => $score,
                    'score_label' => $this->scoreLabel($score),
                    'transicao' => $transicao,
                    'lancamento' => $lancamento,
                    'turmas' => $turmas->map(fn (Turma $t) => [
                        'id' => $t->id,
                        'nome' => $t->nome,
                        'curso' => $t->curso?->nome,
                    ])->values()->all(),
                    'trimestre_actual' => Configuracao::getTrimestreActual(),
                    'trimestre_aberto' => Configuracao::trimestreAbertoParaLancamento(Configuracao::getTrimestreActual()),
                    'ano_lectivo' => $anoLectivo,
                ];
            }
        );
    }

    private function tituloParaUtilizador(User $user): string
    {
        if ($user->hasRole('director_turma')) {
            return 'Saúde das suas turmas (direcção)';
        }
        if ($user->hasRole('coordenador_curso')) {
            return 'Saúde dos seus cursos';
        }
        if ($user->hasRole('professor')) {
            return 'Saúde das suas turmas';
        }

        return 'Saúde das turmas';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Turma>  $turmas
     * @return array<string, int|float>
     */
    private function resumoTransicao($turmas, string $anoLectivo): array
    {
        $transita = 0;
        $deficiencia = 0;
        $naoTransita = 0;
        $incompletos = 0;
        $total = 0;

        foreach ($turmas as $turma) {
            $classe = $this->transicaoService->classeNumero($turma);
            if (! in_array($classe, [10, 11, 12], true)) {
                continue;
            }

            foreach ($this->transicaoService->analisarTurma($turma, $anoLectivo) as $a) {
                $total++;
                $res = $a['resultado'] ?? '';
                if ($res === '') {
                    $incompletos++;
                    continue;
                }
                if ($res === PautaResultadoTransicao::RESULTADO_TRANSITA) {
                    $transita++;
                } elseif (PautaResultadoTransicao::ehTransitaComDeficiencia($res)) {
                    $deficiencia++;
                } else {
                    $naoTransita++;
                }
            }
        }

        $avaliados = max(1, $total - $incompletos);

        return [
            'total_alunos' => $total,
            'transita' => $transita,
            'deficiencia' => $deficiencia,
            'nao_transita' => $naoTransita,
            'incompletos' => $incompletos,
            'taxa_transita' => $total > 0 ? round(($transita / $avaliados) * 100, 1) : 0,
        ];
    }

    /**
     * @param  array<int, int>  $turmaIds
     * @return array<string, int|float>
     */
    private function resumoLancamento(array $turmaIds, int $anoLectivoId): array
    {
        $totalAlunos = Aluno::where('activo', true)->whereIn('turma_id', $turmaIds)->count();
        $trimestre = Configuracao::getTrimestreActual();
        $notasLancadas = Nota::where('ano_lectivo_id', $anoLectivoId)
            ->whereIn('turma_id', $turmaIds)
            ->where('trimestre', $trimestre)
            ->whereIn('tipo', [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT])
            ->count();

        $esperadas = max(1, $totalAlunos * 3);

        return [
            'total_alunos' => $totalAlunos,
            'notas_avaliacao' => $notasLancadas,
            'cobertura_pct' => min(100, round(($notasLancadas / $esperadas) * 100, 1)),
        ];
    }

    private function calcularScore(array $transicao, array $lancamento): int
    {
        $score = 65;
        $score += min(20, (int) round($transicao['taxa_transita'] / 5));
        $score += min(15, (int) round($lancamento['cobertura_pct'] / 10));
        $score -= min(25, (int) round(($transicao['incompletos'] / max(1, $transicao['total_alunos'])) * 35));

        return max(0, min(100, $score));
    }

    private function scoreLabel(int $score): string
    {
        return match (true) {
            $score >= 85 => 'Excelente',
            $score >= 70 => 'Bom',
            $score >= 50 => 'Atenção',
            default => 'Crítico',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function vazio(User $user, string $anoLectivo): array
    {
        return [
            'titulo' => $this->tituloParaUtilizador($user),
            'escopo' => '0 turmas',
            'score' => 0,
            'score_label' => '—',
            'transicao' => ['total_alunos' => 0, 'transita' => 0, 'deficiencia' => 0, 'nao_transita' => 0, 'incompletos' => 0, 'taxa_transita' => 0],
            'lancamento' => ['total_alunos' => 0, 'notas_avaliacao' => 0, 'cobertura_pct' => 0],
            'turmas' => [],
            'trimestre_actual' => Configuracao::getTrimestreActual(),
            'trimestre_aberto' => Configuracao::trimestreAbertoParaLancamento(Configuracao::getTrimestreActual()),
            'ano_lectivo' => $anoLectivo,
        ];
    }
}
