<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\Turma;
use App\Models\User;
use App\Support\PautaResultadoTransicao;
use Illuminate\Support\Facades\Cache;

/**
 * Indicadores de «saúde» académica da escola para o dashboard administrativo.
 */
class SaudeEscolaService
{
    public function __construct(
        private RecuperacaoTransicaoService $transicaoService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indicadores(?string $anoLectivo = null): array
    {
        $anoLectivo = $anoLectivo ?? Configuracao::getAnoLectivoActual();
        $anoLectivoId = $anoLectivo
            ? \App\Models\AnoLectivo::getIdByNome($anoLectivo)
            : null;

        // BD recém-seedada / sem ano lectivo activo — não rebentar o dashboard
        if (! $anoLectivoId) {
            $contasPendentes = User::where('estado', 'pendente')->count();

            return [
                'score' => null,
                'score_label' => 'Sem ano lectivo',
                'transicao' => [
                    'total_alunos' => 0,
                    'transita' => 0,
                    'deficiencia' => 0,
                    'nao_transita' => 0,
                    'incompletos' => 0,
                    'taxa_transita' => 0,
                ],
                'lancamento' => [
                    'total_alunos' => 0,
                    'notas_avaliacao' => 0,
                    'cobertura_pct' => 0,
                ],
                'contas_pendentes' => $contasPendentes,
                'notas_hoje' => Nota::whereDate('created_at', today())->count(),
                'notas_ontem' => Nota::whereDate('created_at', today()->subDay())->count(),
                'trimestre_actual' => Configuracao::getTrimestreActual(),
                'trimestre_aberto' => false,
            ];
        }

        return Cache::remember("saude_escola.{$anoLectivoId}", 300, function () use ($anoLectivo, $anoLectivoId) {
            $turmas = Turma::with(['classe', 'curso'])
                ->where('activo', true)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->get();

            $transicao = $this->resumoTransicao($turmas, (string) $anoLectivo);
            $lancamento = $this->resumoLancamento($turmas, (int) $anoLectivoId);
            $contasPendentes = User::where('estado', 'pendente')->count();
            $notasHoje = Nota::whereDate('created_at', today())->count();
            $notasOntem = Nota::whereDate('created_at', today()->subDay())->count();

            $score = $this->calcularScore($transicao, $lancamento, $contasPendentes);

            return [
                'score' => $score,
                'score_label' => $this->scoreLabel($score),
                'transicao' => $transicao,
                'lancamento' => $lancamento,
                'contas_pendentes' => $contasPendentes,
                'notas_hoje' => $notasHoje,
                'notas_ontem' => $notasOntem,
                'trimestre_actual' => Configuracao::getTrimestreActual(),
                'trimestre_aberto' => Configuracao::trimestreAbertoParaLancamento(Configuracao::getTrimestreActual()),
            ];
        });
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
     * @param  \Illuminate\Support\Collection<int, Turma>  $turmas
     * @return array<string, int|float>
     */
    private function resumoLancamento($turmas, int $anoLectivoId): array
    {
        $totalAlunos = Aluno::where('activo', true)
            ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
            ->count();

        $trimestre = Configuracao::getTrimestreActual();
        $notasLancadas = Nota::where('ano_lectivo_id', $anoLectivoId)
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

    private function calcularScore(array $transicao, array $lancamento, int $contasPendentes): int
    {
        $score = 70;
        $score += min(20, (int) round($transicao['taxa_transita'] / 5));
        $score += min(10, (int) round($lancamento['cobertura_pct'] / 10));
        $score -= min(15, $contasPendentes * 3);
        $score -= min(20, (int) round(($transicao['incompletos'] / max(1, $transicao['total_alunos'])) * 30));

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
}
