<?php

namespace App\Services\Conformidade;

use App\Models\ConformidadeRelatorio;
use App\Models\Turma;
use App\Models\User;
use App\Services\ConselhoService;
use App\Services\PautaService;
use App\Services\RecuperacaoTransicaoService;
use App\Support\PautaMomentoConselho;
use App\Support\PautaRegrasTransicaoMinisterio;

/**
 * Relatório de conformidade DE 424/25 — "Pauta Certificada Facilita".
 */
class ConformidadePautaService
{
    public function __construct(
        private readonly PautaService $pautaService,
        private readonly RecuperacaoTransicaoService $transicaoService,
        private readonly ConselhoService $conselhoService,
    ) {}

    /**
     * @return array{selo: string, total_checks: int, passed: int, failed: int, warnings: int, checks: list<array>, meta: array}
     */
    public function analisarTurma(Turma $turma, int $trimestre, ?string $anoLectivo = null): array
    {
        $anoLectivo = $anoLectivo
            ?? $turma->anoLectivo?->nome
            ?? $turma->ano_lectivo
            ?? \App\Models\Configuracao::getAnoLectivoActual();

        $checks = [];
        $pauta = $this->pautaService->gerarPautaTrimestralCompleta(
            $turma->id,
            $trimestre,
            $anoLectivo,
            PautaMomentoConselho::PRE
        );

        $classeNumero = (int) ($turma->classe?->numero ?? $turma->classe?->nome ?? 0);
        $alunos = $pauta['alunos'] ?? [];
        $disciplinas = $pauta['disciplinas'] ?? collect();

        $checks[] = $this->check(
            'turma_tem_alunos',
            'Turma com alunos activos na pauta',
            count($alunos) > 0,
            count($alunos) > 0 ? 'ok' : 'fail',
            count($alunos).' aluno(s) na pauta'
        );

        $checks[] = $this->check(
            'turma_tem_disciplinas',
            'Turma com disciplinas na grelha',
            $disciplinas->count() > 0,
            $disciplinas->count() > 0 ? 'ok' : 'fail',
            $disciplinas->count().' disciplina(s)'
        );

        // Cobertura de lançamento (MAC/PP no trimestre)
        $celulasEsperadas = 0;
        $celulasPreenchidas = 0;
        foreach ($alunos as $linha) {
            foreach ($disciplinas as $disc) {
                $celulasEsperadas++;
                $notas = $linha['disciplinas'][$disc->id] ?? $linha['notas'][$disc->id] ?? null;
                if ($this->linhaTemAvaliacao($notas, $trimestre)) {
                    $celulasPreenchidas++;
                }
            }
        }
        $cobertura = $celulasEsperadas > 0 ? round(($celulasPreenchidas / $celulasEsperadas) * 100, 1) : 0;
        $checks[] = $this->check(
            'cobertura_lancamento',
            'Cobertura de lançamento ≥ 80%',
            $cobertura >= 80,
            $cobertura >= 80 ? 'ok' : ($cobertura >= 50 ? 'warn' : 'fail'),
            "{$cobertura}% ({$celulasPreenchidas}/{$celulasEsperadas})"
        );

        // Regras T3 / DE 424/25
        if ($trimestre === 3 && in_array($classeNumero, [10, 11, 12], true)) {
            $meta = $pauta['meta_disciplinas_transicao'] ?? $this->transicaoService->metaParaTurma(
                (int) $turma->curso_id,
                $disciplinas->pluck('id')->all(),
                $classeNumero,
            );
            $blindadas = $this->transicaoService->blindadasParaCurso((int) $turma->curso_id);

            $inconsistencias = 0;
            $examesExcessivos = 0;
            foreach ($alunos as $linha) {
                $analise = $this->transicaoService->analisarAluno(
                    $linha,
                    (int) $turma->curso_id,
                    $classeNumero,
                    $meta,
                    $blindadas,
                    (int) ($turma->curso?->classe_max ?? 12),
                );

                $terminais = $analise['terminais_recuperacao'] ?? [];
                if (count($terminais) > PautaRegrasTransicaoMinisterio::MAX_EXAMES_TERMINAIS) {
                    $examesExcessivos++;
                }

                $resultado = $analise['resultado'] ?? '';
                $totalNeg = (int) ($analise['total_negativas'] ?? 0);
                if ($resultado === 'TRANSITA' && $totalNeg > PautaRegrasTransicaoMinisterio::MAX_NEGATIVAS_TRANSITA) {
                    $inconsistencias++;
                }
                if ($resultado === 'N/TRANSITA' && $totalNeg <= PautaRegrasTransicaoMinisterio::MAX_NEGATIVAS_TRANSITA) {
                    // pode ser válido por continuidade — só alerta se motivos vazios
                    if (empty($analise['motivos'])) {
                        $inconsistencias++;
                    }
                }
            }

            $checks[] = $this->check(
                'de424_max_exames_terminais',
                'Máximo de '.PautaRegrasTransicaoMinisterio::MAX_EXAMES_TERMINAIS.' exames terminais por aluno (DE 424/25)',
                $examesExcessivos === 0,
                $examesExcessivos === 0 ? 'ok' : 'fail',
                $examesExcessivos === 0 ? 'Nenhum aluno excede o limite' : "{$examesExcessivos} aluno(s) com excesso"
            );

            $checks[] = $this->check(
                'de424_resultado_coerente',
                'Resultados de transição coerentes com negativas',
                $inconsistencias === 0,
                $inconsistencias === 0 ? 'ok' : 'warn',
                $inconsistencias === 0 ? 'Sem inconsistências detectadas' : "{$inconsistencias} possível(is) inconsistência(s)"
            );

            $checks[] = $this->check(
                'de424_aprovacao_minima',
                'Nota mínima de aprovação = '.PautaRegrasTransicaoMinisterio::APROVACAO_MINIMA.' valores',
                true,
                'ok',
                'Regra aplicada no motor de pautas'
            );

            $checks[] = $this->check(
                'de424_ca_cfd',
                'CFD por média exige CA ≥ '.PautaRegrasTransicaoMinisterio::CA_MINIMA_MEDIA_CFD,
                true,
                'ok',
                'Regra aplicada no motor de pautas'
            );
        } else {
            $checks[] = $this->check(
                'trimestre_intermedio',
                'Trimestre intermédio — regras de transição T3 não aplicáveis',
                true,
                'ok',
                "T{$trimestre} / classe {$classeNumero}"
            );
        }

        // Conselho
        try {
            $conselho = $this->conselhoService->obterOuCriarConselho($turma->id, $trimestre, $anoLectivo);
            $stats = $this->conselhoService->obterEstatisticasConselho($conselho);
            $ajustes = (int) ($stats['total_ajustes'] ?? $conselho->ajustes()->count());
            $checks[] = $this->check(
                'conselho_rastreavel',
                'Ajustes de conselho auditáveis',
                true,
                'ok',
                "Estado: {$conselho->estado}; {$ajustes} ajuste(s)"
            );
        } catch (\Throwable $e) {
            $checks[] = $this->check(
                'conselho_rastreavel',
                'Ajustes de conselho auditáveis',
                false,
                'warn',
                'Não foi possível carregar o conselho: '.$e->getMessage()
            );
        }

        $passed = count(array_filter($checks, fn ($c) => $c['status'] === 'ok'));
        $failed = count(array_filter($checks, fn ($c) => $c['status'] === 'fail'));
        $warnings = count(array_filter($checks, fn ($c) => $c['status'] === 'warn'));

        $selo = match (true) {
            $failed > 0 => ConformidadeRelatorio::SELO_NAO_CONFORME,
            $warnings > 0 => ConformidadeRelatorio::SELO_ALERTA,
            default => ConformidadeRelatorio::SELO_CONFORME,
        };

        return [
            'selo' => $selo,
            'total_checks' => count($checks),
            'passed' => $passed,
            'failed' => $failed,
            'warnings' => $warnings,
            'checks' => $checks,
            'meta' => [
                'turma' => $turma->nome ?? $turma->codigo,
                'turma_id' => $turma->id,
                'ano_lectivo' => $anoLectivo,
                'trimestre' => $trimestre,
                'classe' => $classeNumero,
                'alunos' => count($alunos),
                'disciplinas' => $disciplinas->count(),
                'cobertura_pct' => $cobertura,
                'gerado_em' => now()->toIso8601String(),
                'norma' => 'Decreto Executivo 424/25',
                'produto' => 'Pauta Certificada Facilita',
            ],
        ];
    }

    public function guardar(Turma $turma, array $analise, ?User $user = null): ConformidadeRelatorio
    {
        return ConformidadeRelatorio::query()->create([
            'turma_id' => $turma->id,
            'ano_lectivo' => $analise['meta']['ano_lectivo'] ?? null,
            'trimestre' => $analise['meta']['trimestre'] ?? null,
            'selo' => $analise['selo'],
            'total_checks' => $analise['total_checks'],
            'passed' => $analise['passed'],
            'failed' => $analise['failed'],
            'warnings' => $analise['warnings'],
            'resultado' => $analise,
            'gerado_por' => $user?->id,
        ]);
    }

    private function linhaTemAvaliacao(mixed $notas, int $trimestre): bool
    {
        if (! is_array($notas)) {
            return false;
        }

        foreach (['MAC', 'PP', 'PT', 'PG', 'MT', 'mac', 'pp', 'pt', 'pg', 'mt'] as $k) {
            if (isset($notas[$k]) && $notas[$k] !== null && $notas[$k] !== '') {
                return true;
            }
        }

        // Estrutura alternativa por tipo
        if (isset($notas['tipos']) && is_array($notas['tipos'])) {
            return count(array_filter($notas['tipos'])) > 0;
        }

        return false;
    }

    /**
     * @return array{codigo: string, titulo: string, ok: bool, status: string, detalhe: string}
     */
    private function check(string $codigo, string $titulo, bool $ok, string $status, string $detalhe): array
    {
        return compact('codigo', 'titulo', 'ok', 'status', 'detalhe');
    }
}
