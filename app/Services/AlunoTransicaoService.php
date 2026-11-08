<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\Turma;
use App\Models\User;
use App\Support\PautaResultadoTransicao;
use App\Support\SituacaoAcademicaOficial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AlunoTransicaoService
{
    /**
     * Valida a transição antes de executar
     */
    public function validateTransicao(string $anoOrigem, string $anoDestino): array
    {
        $errors = [];

        if (! preg_match('/^\d{4}-\d{4}$/', $anoOrigem)) {
            $errors[] = 'Ano de origem deve estar no formato YYYY-YYYY (ex: 2024-2025)';
        }

        if (! preg_match('/^\d{4}-\d{4}$/', $anoDestino)) {
            $errors[] = 'Ano de destino deve estar no formato YYYY-YYYY (ex: 2025-2026)';
        }

        if ($anoOrigem === $anoDestino) {
            $errors[] = 'Ano de origem e destino não podem ser iguais';
        }

        $anoOrigemId = AnoLectivo::getIdByNome($anoOrigem);
        if ($anoOrigemId) {
            $totalAlunos = Aluno::whereHas('turma', function ($q) use ($anoOrigemId) {
                $q->where('ano_lectivo_id', $anoOrigemId);
            })->count();

            if ($totalAlunos === 0) {
                $errors[] = "Não existem alunos no ano lectivo {$anoOrigem}";
            }
        } else {
            $errors[] = "Ano lectivo de origem {$anoOrigem} não encontrado no sistema";
        }

        if (! AnoLectivo::where('nome', $anoDestino)->exists()) {
            $errors[] = "Ano lectivo de destino {$anoDestino} não encontrado. Por favor, cadastre o ano antes de fazer a transição.";
        }

        return $errors;
    }

    /**
     * Calcula situação (aprovado/reprovado/pendente/desistente) de cada aluno.
     * Classes 10–12: regras oficiais DE 424/25 via RecuperacaoTransicaoService.
     */
    public function calcularSituacaoAlunos(Collection $alunos, string $anoLectivo): array
    {
        if ($alunos->isEmpty()) {
            return [];
        }

        $alunos = Aluno::with('turma.classe')
            ->whereIn('id', $alunos->pluck('id'))
            ->get();
        $alunosPorTurma = $alunos->groupBy('turma_id');
        $resultado = [];
        $recuperacaoService = app(RecuperacaoTransicaoService::class);

        foreach ($alunosPorTurma as $turmaId => $grupo) {
            $turma = $grupo->first()->turma ?? Turma::with('classe')->find($turmaId);
            if (! $turma) {
                continue;
            }

            $classeNumero = (int) ($turma->classe->codigo ?? 0);

            if (in_array($classeNumero, [10, 11, 12], true)) {
                $analises = $recuperacaoService->resumoPorTurma($turma, $anoLectivo)->keyBy('aluno_id');
                $notasPorAluno = $this->carregarNotasFinaisBatch($grupo->pluck('id')->all(), $anoLectivo);

                foreach ($grupo as $aluno) {
                    $analise = $analises->get($aluno->id, []);
                    if (($analise['situacao'] ?? '') === 'incompleta' || trim((string) ($analise['resultado'] ?? '')) === '') {
                        $resultado[$aluno->id] = $this->calcularSituacaoAlunoComNotas(
                            $aluno,
                            $notasPorAluno->get($aluno->id, collect()),
                            $anoLectivo
                        );
                    } else {
                        $resultado[$aluno->id] = $this->montarSituacaoDeAnaliseOficial($aluno, $analise);
                    }
                }

                continue;
            }

            $notasPorAluno = $this->carregarNotasFinaisBatch($grupo->pluck('id')->all(), $anoLectivo);
            foreach ($grupo as $aluno) {
                $resultado[$aluno->id] = $this->calcularSituacaoAlunoComNotas(
                    $aluno,
                    $notasPorAluno->get($aluno->id, collect()),
                    $anoLectivo
                );
            }
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $analise
     * @return array<string, mixed>
     */
    private function montarSituacaoDeAnaliseOficial(Aluno $aluno, array $analise): array
    {
        if (($analise['situacao'] ?? '') === 'incompleta' || trim((string) ($analise['resultado'] ?? '')) === '') {
            return [
                'aluno' => $aluno,
                'situacao' => 'pendente',
                'reprovacoes' => null,
                'total_disciplinas' => 0,
                'disciplinas' => [],
                'resultado_oficial' => null,
            ];
        }

        $resultado = trim((string) $analise['resultado']);
        $situacao = SituacaoAcademicaOficial::resultadoTransicaoParaChave($resultado, $aluno);

        return [
            'aluno' => $aluno,
            'situacao' => $situacao,
            'reprovacoes' => count($analise['disciplinas_recuperacao'] ?? []),
            'total_disciplinas' => count($analise['disciplinas_recuperacao'] ?? []),
            'disciplinas' => [],
            'resultado_oficial' => $resultado,
        ];
    }

    /**
     * @param  array<int, int>  $alunoIds
     */
    private function carregarNotasFinaisBatch(array $alunoIds, string $anoLectivo): Collection
    {
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);
        $tipos = [
            Nota::TIPO_CFD,
            Nota::TIPO_CA10,
            Nota::TIPO_CA11,
            Nota::TIPO_CA12,
            Nota::TIPO_FINAL,
            Nota::TIPO_CF,
            Nota::TIPO_MFT2,
        ];
        $prioridade = array_flip($tipos);

        $notas = Nota::whereIn('aluno_id', $alunoIds)
            ->where(function ($q) use ($anoLectivo, $anoLectivoId) {
                $q->where('ano_lectivo', $anoLectivo);
                if ($anoLectivoId) {
                    $q->orWhere('ano_lectivo_id', $anoLectivoId);
                }
            })
            ->whereIn('tipo', $tipos)
            ->whereNull('archived_at')
            ->with('disciplina')
            ->get()
            ->sortBy(fn ($n) => $prioridade[$n->tipo] ?? 99);

        return $notas->groupBy('aluno_id')->map(
            fn ($grupo) => $grupo->unique('disciplina_id')->values()
        );
    }

    /**
     * @param  Collection<int, Nota>  $notasFinais
     * @return array<string, mixed>
     */
    private function calcularSituacaoAlunoComNotas(Aluno $aluno, Collection $notasFinais, string $anoLectivo): array
    {
        $pautaService = app(PautaService::class);

        if ($notasFinais->isEmpty()) {
            $situacaoViaPauta = $pautaService->determinarSituacaoAluno($aluno, $anoLectivo);
            if ($situacaoViaPauta === null) {
                return [
                    'aluno' => $aluno,
                    'situacao' => 'pendente',
                    'reprovacoes' => null,
                    'total_disciplinas' => 0,
                    'disciplinas' => [],
                ];
            }

            return [
                'aluno' => $aluno,
                'situacao' => $situacaoViaPauta,
                'reprovacoes' => null,
                'total_disciplinas' => 0,
                'disciplinas' => [],
            ];
        }

        $disciplinas = $notasFinais->map(function ($nota) use ($pautaService) {
            return [
                'disciplina_id' => $nota->disciplina_id,
                'disciplina_nome' => $nota->disciplina->nome ?? 'Desconhecida',
                'nota_final' => $nota->valor,
                'situacao' => $pautaService->determinarSituacao($nota->valor),
            ];
        })->values()->all();

        $situacao = $pautaService->determinarSituacaoFinal($disciplinas);
        $reprovacoes = collect($disciplinas)->where('situacao', 'reprovado')->count();

        return [
            'aluno' => $aluno,
            'situacao' => $situacao,
            'reprovacoes' => $reprovacoes,
            'total_disciplinas' => count($disciplinas),
            'disciplinas' => $disciplinas,
        ];
    }

    /**
     * Arquiva notas de um ano lectivo (fecho definitivo).
     */
    public function arquivarNotasAnoLectivo(string $anoLectivo): int
    {
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);

        return Nota::whereNull('archived_at')
            ->where(function ($q) use ($anoLectivo, $anoLectivoId) {
                $q->where('ano_lectivo', $anoLectivo);
                if ($anoLectivoId) {
                    $q->orWhere('ano_lectivo_id', $anoLectivoId);
                }
            })
            ->update(['archived_at' => now()]);
    }

    /**
     * Mapeamento automático: turma origem → turma destino (promoção = classe+1)
     *
     * @return array<int, int|null>
     */
    public function criarMapeamentoPromocao(Collection $turmasOrigem, Collection $turmasDestino): array
    {
        return $this->criarMapeamentoPorOffsetClasse($turmasOrigem, $turmasDestino, 1);
    }

    /**
     * Mapeamento automático: mesma classe (repetência / reprovados)
     *
     * @return array<int, int|null>
     */
    public function criarMapeamentoRepetencia(Collection $turmasOrigem, Collection $turmasDestino): array
    {
        return $this->criarMapeamentoPorOffsetClasse($turmasOrigem, $turmasDestino, 0);
    }

    /**
     * @return array<int, int|null>
     */
    private function criarMapeamentoPorOffsetClasse(
        Collection $turmasOrigem,
        Collection $turmasDestino,
        int $offsetClasse
    ): array {
        $mapping = [];

        foreach ($turmasOrigem as $turmaOrigem) {
            $mapping[$turmaOrigem->id] = $this->resolverTurmaDestinoId($turmaOrigem, $turmasDestino, $offsetClasse);
        }

        return $mapping;
    }

    private function resolverTurmaDestinoId(Turma $turmaOrigem, Collection $turmasDestino, int $offsetClasse): ?int
    {
        $classeAtual = (int) ($turmaOrigem->classe->codigo ?? 0);
        if ($classeAtual <= 0) {
            return null;
        }

        $classeAlvo = $offsetClasse > 0 ? min($classeAtual + $offsetClasse, 13) : $classeAtual;

        $candidatas = $turmasDestino->filter(function ($td) use ($turmaOrigem, $classeAlvo) {
            return $td->curso_id === $turmaOrigem->curso_id
                && (int) ($td->classe->codigo ?? 0) === $classeAlvo;
        });

        if ($turmaOrigem->turno_id) {
            $comTurno = $candidatas->where('turno_id', $turmaOrigem->turno_id);
            if ($comTurno->isNotEmpty()) {
                $candidatas = $comTurno;
            }
        }

        if ($candidatas->isEmpty()) {
            return null;
        }

        if ($candidatas->count() === 1) {
            return $candidatas->first()->id;
        }

        $sufixo = $this->extrairSufixoTurma($turmaOrigem);
        if ($sufixo !== '') {
            $porSufixo = $candidatas->first(fn ($td) => $this->extrairSufixoTurma($td) === $sufixo);
            if ($porSufixo) {
                return $porSufixo->id;
            }
        }

        return $candidatas->first()->id;
    }

    /**
     * Letras identificadoras da turma após o número da classe (ex.: TI10AT → AT).
     */
    private function extrairSufixoTurma(Turma $turma): string
    {
        $ref = mb_strtoupper(trim($turma->codigo ?: $turma->nome ?: ''));
        if (preg_match('/\d{2}([A-Z]+)/', $ref, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Gera preview da transição com estatísticas e mapeamento
     */
    public function previewTransicao(
        string $anoOrigem,
        string $anoDestino,
        ?array $mapeamentoPromocaoManual = null,
        ?array $mapeamentoRepetenciaManual = null
    ): array {
        $anoOrigemId = AnoLectivo::getIdByNome($anoOrigem);
        $anoDestinoId = AnoLectivo::getIdByNome($anoDestino);

        $turmasOrigem = Turma::with(['curso', 'classe', 'sala', 'turno', 'alunos'])
            ->where('ano_lectivo_id', $anoOrigemId)
            ->get();

        $turmasDestino = Turma::with(['curso', 'classe', 'sala', 'turno'])
            ->where('ano_lectivo_id', $anoDestinoId)
            ->get();

        $mapeamentoPromocao = $mapeamentoPromocaoManual ?? $this->criarMapeamentoPromocao($turmasOrigem, $turmasDestino);
        $mapeamentoRepetencia = $mapeamentoRepetenciaManual ?? $this->criarMapeamentoRepetencia($turmasOrigem, $turmasDestino);

        $todosAlunos = $turmasOrigem->pluck('alunos')->flatten();
        $situacoesAlunos = $this->calcularSituacaoAlunos($todosAlunos, $anoOrigem);

        $aprovados = collect($situacoesAlunos)->where('situacao', 'aprovado')->count();
        $reprovados = collect($situacoesAlunos)->where('situacao', 'reprovado')->count();
        $pendentes = collect($situacoesAlunos)->where('situacao', 'pendente')->count();

        $estatisticasPorTurma = [];
        foreach ($turmasOrigem as $turma) {
            $alunosTurma = $turma->alunos->pluck('id')->toArray();
            $situacoesTurma = collect($situacoesAlunos)
                ->filter(fn ($s, $id) => in_array($id, $alunosTurma, true));

            $estatisticasPorTurma[$turma->id] = [
                'turma' => $turma,
                'total' => $situacoesTurma->count(),
                'aprovados' => $situacoesTurma->where('situacao', 'aprovado')->count(),
                'reprovados' => $situacoesTurma->where('situacao', 'reprovado')->count(),
                'pendentes' => $situacoesTurma->where('situacao', 'pendente')->count(),
                'tem_destino_promocao' => isset($mapeamentoPromocao[$turma->id]) && $mapeamentoPromocao[$turma->id] !== null,
                'tem_destino_repetencia' => isset($mapeamentoRepetencia[$turma->id]) && $mapeamentoRepetencia[$turma->id] !== null,
            ];
        }

        $warnings = $this->gerarWarnings($situacoesAlunos, $mapeamentoPromocao, $mapeamentoRepetencia, $estatisticasPorTurma);

        return [
            'ano_origem' => $anoOrigem,
            'ano_destino' => $anoDestino,
            'turmas_origem' => $turmasOrigem,
            'turmas_destino' => $turmasDestino,
            'mapeamento' => $mapeamentoPromocao,
            'mapeamento_promocao' => $mapeamentoPromocao,
            'mapeamento_repetencia' => $mapeamentoRepetencia,
            'situacoes_alunos' => $situacoesAlunos,
            'estatisticas_por_turma' => $estatisticasPorTurma,
            'stats' => [
                'total_alunos' => $todosAlunos->count(),
                'aprovados' => $aprovados,
                'reprovados' => $reprovados,
                'pendentes' => $pendentes,
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<int, int|null>  $mapeamentoPromocao
     * @param  array<int, int|null>  $mapeamentoRepetencia
     */
    private function gerarWarnings(
        array $situacoesAlunos,
        array $mapeamentoPromocao,
        array $mapeamentoRepetencia,
        array $estatisticasPorTurma
    ): array {
        $warnings = [];

        $pendentes = collect($situacoesAlunos)->where('situacao', 'pendente')->count();
        if ($pendentes > 0) {
            $warnings[] = "{$pendentes} aluno(s) têm situação 'pendente' (sem notas finais lançadas)";
        }

        $semPromocao = collect($estatisticasPorTurma)->where('tem_destino_promocao', false)->count();
        if ($semPromocao > 0) {
            $warnings[] = "{$semPromocao} turma(s) não têm destino de promoção (classe seguinte) mapeado";
        }

        $semRepetencia = collect($estatisticasPorTurma)->where('tem_destino_repetencia', false)->count();
        if ($semRepetencia > 0) {
            $warnings[] = "{$semRepetencia} turma(s) não têm destino de repetência (mesma classe) mapeado";
        }

        $reprovados = collect($situacoesAlunos)->where('situacao', 'reprovado')->count();
        if ($reprovados > 0) {
            $warnings[] = "{$reprovados} aluno(s) reprovado(s) — active a opção 'Transitar reprovados' para os mover para a mesma classe no ano novo";
        }

        return $warnings;
    }

    /**
     * Executa a transição de alunos com transação DB
     *
     * @param  array<int, int|null>  $mapeamentoPromocao
     * @param  array<int, int|null>  $mapeamentoRepetencia
     */
    public function executeTransicao(
        string $anoOrigem,
        string $anoDestino,
        array $mapeamentoPromocao,
        array $mapeamentoRepetencia,
        bool $transitarReprovados = false,
        ?array $alunosSelecionados = null
    ): array {
        $anoOrigemId = AnoLectivo::getIdByNome($anoOrigem);
        $anoDestinoId = AnoLectivo::getIdByNome($anoDestino);

        $stats = [
            'alunos_transitados' => 0,
            'alunos_aprovados' => 0,
            'alunos_reprovados' => 0,
            'alunos_ignorados' => 0,
            'contas_portal_actualizadas' => 0,
            'vinculos_encarregado' => 0,
            'erros' => [],
            'avisos' => [],
        ];

        $turmasOrigemIds = array_unique(array_merge(
            array_keys($mapeamentoPromocao),
            array_keys($mapeamentoRepetencia)
        ));

        DB::beginTransaction();

        try {
            $todosAlunos = Aluno::with(['turma.classe', 'encarregados'])
                ->whereIn('turma_id', $turmasOrigemIds)
                ->get();

            $situacoesAlunos = $this->calcularSituacaoAlunos($todosAlunos, $anoOrigem);
            $turmasDestinoParaRecalcular = [];

            foreach ($situacoesAlunos as $alunoId => $dados) {
                $aluno = $dados['aluno'];
                $situacao = $dados['situacao'];

                $deveTransitar = false;
                $motivo = '';

                if ($alunosSelecionados !== null) {
                    $deveTransitar = in_array($alunoId, $alunosSelecionados, true);
                    if (! $deveTransitar) {
                        $motivo = 'não selecionado';
                    }
                } else {
                    if ($situacao === 'aprovado') {
                        $deveTransitar = true;
                    } elseif ($situacao === 'reprovado' && $transitarReprovados) {
                        $deveTransitar = true;
                        $motivo = 'reprovado';
                    } elseif ($situacao === 'desistente') {
                        $stats['alunos_ignorados']++;
                        $motivo = 'desistente';
                    } elseif ($situacao === 'pendente') {
                        $stats['alunos_ignorados']++;
                        $motivo = 'pendente';
                    } else {
                        $stats['alunos_ignorados']++;
                        $motivo = 'reprovado (opção desactivada)';
                    }
                }

                if (! $deveTransitar) {
                    if ($motivo) {
                        $stats['avisos'][] = "Aluno {$aluno->nome} ignorado: {$motivo}";
                    }
                    continue;
                }

                $turmaOrigemId = $aluno->turma_id;
                $turmaDestinoId = $this->resolverTurmaDestinoParaSituacao(
                    $turmaOrigemId,
                    $situacao,
                    $mapeamentoPromocao,
                    $mapeamentoRepetencia
                );

                if (! $turmaDestinoId) {
                    $tipo = $situacao === 'reprovado' ? 'repetência' : 'promoção';
                    $stats['erros'][] = "Aluno {$aluno->nome} — sem turma destino de {$tipo} mapeada";
                    continue;
                }

                if ($this->alunoJaExisteNoAnoDestino($aluno->numero_processo, $anoDestinoId)) {
                    $stats['erros'][] = "Aluno {$aluno->nome} ({$aluno->numero_processo}) já existe no ano destino";
                    continue;
                }

                $novoAluno = $this->duplicarAlunoParaTurma($aluno, $turmaDestinoId, $situacao);
                $stats['vinculos_encarregado'] += $this->copiarEncarregados($aluno, $novoAluno);

                $this->marcarAlunoOrigemTransferido($aluno, $situacao);
                $this->actualizarContaPortal($aluno->id, $novoAluno->id, $stats);

                activity()
                    ->performedOn($novoAluno)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'aluno_origem_id' => $aluno->id,
                        'ano_origem' => $anoOrigem,
                        'ano_destino' => $anoDestino,
                        'turma_origem_id' => $turmaOrigemId,
                        'turma_destino_id' => $turmaDestinoId,
                        'situacao' => $situacao,
                    ])
                    ->log('Aluno transitado entre anos lectivos');

                if ($situacao === 'aprovado') {
                    $stats['alunos_aprovados']++;
                } elseif ($situacao === 'reprovado') {
                    $stats['alunos_reprovados']++;
                }

                $stats['alunos_transitados']++;
                $turmasDestinoParaRecalcular[$turmaDestinoId] = true;
            }

            foreach (array_keys($turmasDestinoParaRecalcular) as $turmaId) {
                Aluno::recalcularNumerosOrdemTurma($turmaId);
            }

            DB::commit();

            return [
                'success' => $stats['alunos_transitados'] > 0 || empty($stats['erros']),
                'partial' => ! empty($stats['erros']) && $stats['alunos_transitados'] > 0,
                'stats' => $stats,
                'message' => $this->buildSuccessMessage($stats),
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Erro ao executar transição: '.$e->getMessage(),
                'stats' => $stats,
            ];
        }
    }

    /**
     * @param  array<int, int|null>  $mapeamentoPromocao
     * @param  array<int, int|null>  $mapeamentoRepetencia
     */
    private function resolverTurmaDestinoParaSituacao(
        int $turmaOrigemId,
        string $situacao,
        array $mapeamentoPromocao,
        array $mapeamentoRepetencia
    ): ?int {
        if ($situacao === 'reprovado') {
            return $mapeamentoRepetencia[$turmaOrigemId] ?? null;
        }

        return $mapeamentoPromocao[$turmaOrigemId] ?? null;
    }

    public function alunoJaExisteNoAnoDestino(string $numeroProcesso, int $anoDestinoId): bool
    {
        return Aluno::where('numero_processo', $numeroProcesso)
            ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoDestinoId))
            ->exists();
    }

    private function marcarAlunoOrigemTransferido(Aluno $aluno, string $situacao): void
    {
        $novaSituacao = match ($situacao) {
            'aprovado' => Aluno::SITUACAO_APROVADO,
            'reprovado' => Aluno::SITUACAO_REPROVADO,
            default => Aluno::SITUACAO_TRANSFERIDO,
        };

        $aluno->update([
            'situacao_academica' => $novaSituacao,
            'activo' => false,
        ]);
    }

    private function duplicarAlunoParaTurma(Aluno $aluno, int $turmaDestinoId, string $situacao): Aluno
    {
        $proximoNumero = (Aluno::where('turma_id', $turmaDestinoId)->max('numero_ordem') ?? 0) + 1;

        return Aluno::create([
            'numero_processo' => $aluno->numero_processo,
            'nome' => $aluno->nome,
            'foto_path' => $aluno->foto_path,
            'data_nascimento' => $aluno->data_nascimento,
            'genero' => $aluno->genero,
            'bi_numero' => $aluno->bi_numero,
            'educacao_especial' => $aluno->educacao_especial,
            'necessidades_especificas' => $aluno->necessidades_especificas,
            'atleta_alta_competicao' => $aluno->atleta_alta_competicao,
            'faltou_exame_normal' => $aluno->faltou_exame_normal,
            'consulta_notas_portal' => $aluno->consulta_notas_portal,
            'motivo_bloqueio_portal' => $aluno->motivo_bloqueio_portal,
            'turma_id' => $turmaDestinoId,
            'numero_ordem' => $proximoNumero,
            'activo' => true,
            'situacao_academica' => Aluno::SITUACAO_MATRICULADO,
        ]);
    }

    /**
     * Reassocia os encarregados do aluno de origem ao novo registo (mesmo pivot).
     */
    private function copiarEncarregados(Aluno $origem, Aluno $destino): int
    {
        $origem->loadMissing('encarregados');
        $sync = [];

        foreach ($origem->encarregados as $encarregado) {
            $sync[$encarregado->id] = [
                'parentesco' => $encarregado->pivot->parentesco,
                'principal' => (bool) ($encarregado->pivot->principal ?? false),
                'pode_ver_notas' => (bool) ($encarregado->pivot->pode_ver_notas ?? true),
                'pode_assinar_boletim' => (bool) ($encarregado->pivot->pode_assinar_boletim ?? false),
            ];
        }

        if ($sync === []) {
            return 0;
        }

        $destino->encarregados()->syncWithoutDetaching($sync);

        return count($sync);
    }

    private function actualizarContaPortal(int $alunoOrigemId, int $novoAlunoId, array &$stats): void
    {
        $actualizados = User::where('aluno_id', $alunoOrigemId)->update(['aluno_id' => $novoAlunoId]);
        $stats['contas_portal_actualizadas'] += $actualizados;
    }

    /**
     * Integração com AnoLectivoTransferService.
     * Promoção (classe+1) para aprovados; repetência (mesma classe) para reprovados.
     *
     * @param  array<int, int|null>  $turmaMappingRepetencia  Mapeamento 1:1 da transferência de estrutura
     * @return array{success: bool, stats: array, message?: string, error?: string}
     */
    public function transitarAlunosAposTransferenciaEstrutura(
        string $anoOrigem,
        string $anoDestino,
        array $turmaMappingRepetencia,
        bool $transitarReprovados = false
    ): array {
        $anoOrigemId = AnoLectivo::getIdByNome($anoOrigem);
        $anoDestinoId = AnoLectivo::getIdByNome($anoDestino);

        $turmasOrigem = Turma::with(['curso', 'classe', 'turno'])
            ->where('ano_lectivo_id', $anoOrigemId)
            ->get();

        $turmasDestino = Turma::with(['curso', 'classe', 'turno'])
            ->where('ano_lectivo_id', $anoDestinoId)
            ->get();

        $mapeamentoPromocao = $this->criarMapeamentoPromocao($turmasOrigem, $turmasDestino);
        $mapeamentoRepetencia = ! empty($turmaMappingRepetencia)
            ? $turmaMappingRepetencia
            : $this->criarMapeamentoRepetencia($turmasOrigem, $turmasDestino);

        return $this->executeTransicao(
            $anoOrigem,
            $anoDestino,
            $mapeamentoPromocao,
            $mapeamentoRepetencia,
            $transitarReprovados,
            null
        );
    }

    /**
     * @deprecated Use transitarAlunosAposTransferenciaEstrutura()
     */
    public function transitarAlunosAprovados(
        string $anoOrigem,
        string $anoDestino,
        array $turmaMapping
    ): int {
        $result = $this->transitarAlunosAposTransferenciaEstrutura(
            $anoOrigem,
            $anoDestino,
            $turmaMapping,
            false
        );

        return $result['stats']['alunos_transitados'] ?? 0;
    }

    private function buildSuccessMessage(array $stats): string
    {
        $parts = [];

        if ($stats['alunos_transitados'] > 0) {
            $parts[] = "{$stats['alunos_transitados']} aluno(s) transitados com sucesso";
        }
        if ($stats['alunos_aprovados'] > 0) {
            $parts[] = "{$stats['alunos_aprovados']} aprovado(s)";
        }
        if ($stats['alunos_reprovados'] > 0) {
            $parts[] = "{$stats['alunos_reprovados']} reprovado(s) (repetência)";
        }
        if ($stats['contas_portal_actualizadas'] > 0) {
            $parts[] = "{$stats['contas_portal_actualizadas']} conta(s) de portal actualizada(s)";
        }
        if (($stats['vinculos_encarregado'] ?? 0) > 0) {
            $parts[] = "{$stats['vinculos_encarregado']} vínculo(s) de encarregado preservado(s)";
        }
        if ($stats['alunos_ignorados'] > 0) {
            $parts[] = "{$stats['alunos_ignorados']} aluno(s) não transitados";
        }

        $message = ! empty($parts)
            ? 'Transição concluída: '.implode(', ', $parts).'.'
            : 'Nenhum aluno foi transitado.';

        if (! empty($stats['erros'])) {
            $message .= ' '.count($stats['erros']).' erro(s) encontrado(s).';
        }

        return $message;
    }
}
