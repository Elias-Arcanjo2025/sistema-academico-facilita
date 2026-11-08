<?php

namespace App\Services;

use App\Models\Configuracao;
use App\Models\Turma;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Regras de transição na pauta T3 (10.ª–12.ª) — Condições de Transição de Classe (DE 424/25).
 *
 * 10.ª/11.ª:
 * - 0 negativas → TRANSITA
 * - Só contínuas (≤N) → TRANSITA/C DEF (Def.: …)
 * - Com terminais (≤N) → RECUPERAÇÃO (Rec.: …)
 * - Mais de N negativas ou 3+ contínuas → N/TRANSITA
 *
 * 12.ª: APTO | RECURSO | N/APTO (limiar configurável)
 * A partir da 11.ª: negativas em terminais de anos anteriores contam no total
 */
class RecuperacaoTransicaoService
{
    public const APROVACAO_MINIMA = 10;

    /** @deprecated Use PautaResultadoTransicao::MAX_RECUPERACAO */
    public const MAX_DEFICIENCIAS_CONTINUAS = 3;

    /** @deprecated Use PautaResultadoTransicao::MIN_REPROVACAO_DIRECTA */
    public const MIN_REPROVACAO_DIRECTA = 4;

    /**
     * @return Collection<int, array{
     *   aluno_id:int,
     *   numero_ordem:?int,
     *   nome:string,
     *   situacao:string,
     *   resultado:string,
     *   reprova_directamente:bool,
     *   motivos:array<int, string>,
     *   terminais_recuperacao:array<int, array{disciplina_id:int, disciplina_nome:string, ca:float}>,
     *   continuas_deficientes:array<int, array{disciplina_id:int, disciplina_nome:string, ca:float}>,
     *   blindadas_negativas:array<int, array{disciplina_id:int, disciplina_nome:string, ca:float}>,
     * }>
     */
    public function analisarTurma(Turma $turma, ?string $anoLectivo = null): Collection
    {
        $classeNumero = $this->classeNumero($turma);

        if (! in_array($classeNumero, [10, 11, 12], true)) {
            return collect();
        }

        $anoLectivo = $anoLectivo
            ?? $turma->anoLectivo?->nome
            ?? $turma->ano_lectivo
            ?? Configuracao::getAnoLectivoActual();

        $pauta = app(PautaService::class)->gerarPautaTrimestralCompleta(
            $turma->id,
            3,
            $anoLectivo,
        );

        $cursoId = (int) $turma->curso_id;
        $disciplinas = $pauta['disciplinas'];
        $meta = $this->metaDisciplinas(
            $cursoId,
            $disciplinas->pluck('id')->all(),
            $classeNumero,
            $pauta['meta_disciplinas_transicao'] ?? [],
        );
        $blindadasIds = $this->idsDisciplinasBlindadas($cursoId);

        return collect($pauta['alunos'])->map(function (array $aluno) use (
            $meta,
            $blindadasIds,
            $cursoId,
            $classeNumero,
        ) {
            return $this->analisarAluno(
                $aluno,
                $cursoId,
                $classeNumero,
                $meta,
                $blindadasIds,
            );
        })->values();
    }

    /**
     * @param  array<int, int>  $disciplinasIds
     * @return array<int, array{disciplina_id:int, nome:string, codigo:?string, classe_fim:int, blindada:bool}>
     */
    public function metaParaTurma(int $cursoId, array $disciplinasIds, int $classeNumero = 0): array
    {
        return $this->metaDisciplinas($cursoId, $disciplinasIds, $classeNumero);
    }

    /**
     * @return array<int, int>
     */
    public function blindadasParaCurso(int $cursoId): array
    {
        return $this->idsDisciplinasBlindadas($cursoId);
    }

    /**
     * Lista plana — uma linha por aluno × disciplina terminal sujeita a recuperação.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listarRecuperacaoTerminalPlano(
        ?int $turmaId = null,
        ?string $anoLectivo = null,
    ): Collection {
        $anoLectivo = $anoLectivo ?? Configuracao::getAnoLectivoActual();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);

        $query = Turma::with(['classe', 'curso', 'anoLectivo'])
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereHas('classe', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('codigo', 'like', '10%')->orWhere('nome', 'like', '10%')
                        ->orWhere('codigo', 'like', '11%')->orWhere('nome', 'like', '11%');
                });
            });

        if ($turmaId) {
            $query->where('id', $turmaId);
        }

        $linhas = collect();

        foreach ($query->orderBy('nome')->get() as $turma) {
            $analises = $this->analisarTurma($turma, $anoLectivo);

            foreach ($analises as $a) {
                if (! \App\Support\PautaResultadoTransicao::elegivelRecuperacao(
                    count($a['disciplinas_recuperacao'] ?? []),
                    count($a['terminais_recuperacao'] ?? []),
                    count($a['continuas_deficientes'] ?? []),
                )) {
                    continue;
                }

                foreach ($a['terminais_recuperacao'] as $tr) {
                    $linhas->push([
                        'tipo_exame' => 'Recuperação terminal',
                        'turma_id' => $turma->id,
                        'turma_nome' => $turma->nome,
                        'classe' => $this->classeNumero($turma),
                        'trimestre' => 3,
                        'aluno_id' => $a['aluno_id'],
                        'numero_ordem' => $a['numero_ordem'],
                        'aluno_nome' => $a['nome'],
                        'disciplina_id' => $tr['disciplina_id'],
                        'disciplina_nome' => $tr['disciplina_nome'],
                        'nota_referencia' => $tr['ca'],
                        'detalhe' => 'CA: '.(int) round($tr['ca']),
                        'situacao_aluno' => $a['situacao'],
                        'resultado_aluno' => $a['resultado'],
                        'reprova_directamente' => $a['reprova_directamente'],
                        'deficiencias_continuas' => count($a['continuas_deficientes']),
                    ]);
                }
            }
        }

        return $linhas->sortBy(['turma_nome', 'numero_ordem', 'disciplina_nome'])->values();
    }

    /**
     * Resumo por turma (um registo por aluno).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function resumoPorTurma(Turma $turma, ?string $anoLectivo = null): Collection
    {
        return $this->analisarTurma($turma, $anoLectivo);
    }

    /**
     * @param  array<string, mixed>  $aluno  linha da pauta T3
     * @param  array<int, array{disciplina_id:int, nome:string, codigo:?string, classe_fim:int, blindada:bool}>  $meta
     * @param  array<int, int>  $blindadasIds
     * @return array<string, mixed>
     */
    public function analisarAluno(
        array $aluno,
        int $cursoId,
        int $classeNumero,
        array $meta,
        array $blindadasIds,
        int $cursoClasseMax = 12,
    ): array {
        $base = [
            'aluno_id' => $aluno['aluno']->id ?? ($aluno['aluno_id'] ?? 0),
            'numero_ordem' => $aluno['numero_ordem'] ?? null,
            'nome' => $aluno['nome'] ?? '',
            'reprova_directamente' => false,
            'motivos' => [],
            'terminais_recuperacao' => [],
            'continuas_deficientes' => [],
            'blindadas_negativas' => [],
            'disciplinas_recuperacao' => [],
            'observacao' => '',
        ];

        // Classificar com as notas já lançadas (igual ao Excel) — células vazias não bloqueiam.
        $avaliacaoCompleta = $this->avaliacaoCompleta($aluno, $meta, $classeNumero);

        $negativas = [];
        $terminais = [];
        $continuas = [];
        $blindadasNeg = [];
        $totalOficiais = 0;

        foreach ($aluno['disciplinas'] ?? [] as $discId => $d) {
            $discId = (int) $discId;
            $info = $meta[$discId] ?? null;

            if ($info === null) {
                continue;
            }

            if (! empty($info['sem_professor'])) {
                continue;
            }

            $nota = \App\Support\PautaResultadoTransicao::notaOficialDisciplina(
                $d,
                $info,
                $classeNumero,
            );
            if ($nota === null) {
                continue;
            }

            $totalOficiais++;

            if ($nota >= self::APROVACAO_MINIMA) {
                continue;
            }

            $item = [
                'disciplina_id' => $discId,
                'disciplina_nome' => $info['codigo'] ?: $info['nome'],
                'ca' => $nota,
            ];

            $negativas[] = $item;

            if (in_array($discId, $blindadasIds, true)) {
                $blindadasNeg[] = $item;
            }

            if (\App\Support\PautaResultadoTransicao::disciplinaTerminal($info, $classeNumero)) {
                $terminais[] = $item;
            } elseif (\App\Support\PautaResultadoTransicao::disciplinaContinua($info, $classeNumero)) {
                $continuas[] = $item;
            }
        }

        if ($this->incluirTerminaisAnteriores($classeNumero)) {
            foreach ($aluno['terminais_anteriores'] ?? [] as $term) {
                $discId = (int) ($term['disciplina_id'] ?? 0);
                $cfd = $aluno['cfd_terminais'][$discId] ?? null;
                if ($cfd === null || $cfd === '' || (float) $cfd >= self::APROVACAO_MINIMA) {
                    continue;
                }

                $item = [
                    'disciplina_id' => $discId,
                    'disciplina_nome' => $term['codigo'] ?? mb_strtoupper(mb_substr($term['nome'] ?? '', 0, 5)),
                    'ca' => (float) $cfd,
                ];

                $negativas[] = $item;
                $terminais[] = $item;
            }
        }

        // Sem nenhuma nota oficial do ano nem negativa de carry-over → pendente
        if ($totalOficiais === 0 && $negativas === []) {
            return array_merge($base, [
                'situacao' => 'sem_avaliacao',
                'resultado' => '',
                'avaliacao_completa' => $avaliacaoCompleta,
            ]);
        }

        $totalNeg = count($negativas);
        $countTerm = count($terminais);
        $countCont = count($continuas);

        if ($classeNumero === 12) {
            $resolvido = \App\Support\PautaResultadoTransicao::resolverResultado12(
                $totalNeg,
                $negativas,
            );
        } else {
            $resolvido = \App\Support\PautaResultadoTransicao::resolverResultado(
                $totalNeg,
                $terminais,
                $continuas,
            );
        }

        $terminaisParaExame = \App\Support\PautaRegrasTransicaoMinisterio::limitarExamesTerminais(
            $terminais,
            $classeNumero,
        );

        $observacao = $resolvido['observacao'];
        if ($classeNumero === 12
            && $cursoClasseMax >= 13
            && ! \App\Support\PautaRegrasTransicaoMinisterio::papCondicaoReunida($totalNeg)
        ) {
            $observacao = trim($observacao.' | PAP: condição não reunida');
        }

        $reprovaDirectamente = $resolvido['reprova_directamente'];
        $motivos = [];
        if ($reprovaDirectamente) {
            if ($classeNumero === 12) {
                $maxRec = \App\Support\PautaResultadoTransicao::maxNegativasRecurso12();
                $motivos[] = 'Mais de '.$maxRec.' disciplina(s) negativa(s) ('.$totalNeg.') — N/APTO';
            } elseif ($countCont >= \App\Support\PautaResultadoTransicao::MAX_NEGATIVAS_CONTINUIDADE) {
                $motivos[] = '3 ou mais disciplinas de continuidade negativas ('.$countCont.') — não transita';
            } elseif ($totalNeg > \App\Support\PautaResultadoTransicao::maxNegativasRecuperacao()) {
                $maxRec = \App\Support\PautaResultadoTransicao::maxNegativasRecuperacao();
                $motivos[] = 'Mais de '.$maxRec.' disciplina(s) negativa(s) ('.$totalNeg.') — não transita';
            }
        }

        return array_merge($base, [
            'situacao' => $resolvido['situacao'],
            'resultado' => $resolvido['resultado'],
            'reprova_directamente' => $reprovaDirectamente,
            'motivos' => $motivos,
            'terminais_recuperacao' => $terminaisParaExame,
            'continuas_deficientes' => $continuas,
            'blindadas_negativas' => $blindadasNeg,
            'disciplinas_recuperacao' => $negativas,
            'observacao' => $observacao,
            'avaliacao_completa' => $avaliacaoCompleta,
        ]);
    }

    /**
     * @deprecated Use PautaRegrasTransicaoMinisterio::incluirCfdTerminaisAnteriores()
     */
    private function incluirTerminaisAnteriores(int $classeNumero): bool
    {
        return \App\Support\PautaRegrasTransicaoMinisterio::incluirCfdTerminaisAnteriores($classeNumero);
    }

    /**
     * Todas as disciplinas da pauta do ano actual têm de ter nota oficial (CA ou CFD).
     *
     * @param  array<int, array{disciplina_id:int, nome:string, codigo:?string, classe_fim:int, blindada:bool}>  $meta
     */
    private function avaliacaoCompleta(array $aluno, array $meta, int $classeNumero): bool
    {
        if ($meta === []) {
            return false;
        }

        foreach ($meta as $discId => $info) {
            if (! empty($info['sem_professor'])) {
                continue;
            }

            $d = $aluno['disciplinas'][$discId] ?? $aluno['disciplinas'][(int) $discId] ?? [];
            if (\App\Support\PautaResultadoTransicao::notaOficialDisciplina($d, $info, $classeNumero) === null) {
                return false;
            }
        }

        if ($this->incluirTerminaisAnteriores($classeNumero)) {
            foreach ($aluno['terminais_anteriores'] ?? [] as $term) {
                $discId = (int) ($term['disciplina_id'] ?? 0);
                $cfd = $aluno['cfd_terminais'][$discId] ?? null;
                if ($cfd === null || $cfd === '') {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $dadosDisciplina
     * @param  array{usa_cfd?:bool, ...}  $info
     * @deprecated Use PautaResultadoTransicao::notaOficialDisciplina()
     */
    private function notaOficial(array $dadosDisciplina, array $info): ?float
    {
        return \App\Support\PautaCalculos::notaResultadoDisciplina(
            $dadosDisciplina,
            (bool) ($info['usa_cfd'] ?? false)
        );
    }

    private function disciplinaTerminaNestaClasse(int $classeFim, int $classeNumero): bool
    {
        return $classeFim === $classeNumero;
    }

    /**
     * @param  array<int, int>  $disciplinasIds
     * @param  array<int, array<string, mixed>>  $metaPauta  meta enriquecida da pauta (opcional)
     * @return array<int, array{disciplina_id:int, nome:string, codigo:?string, classe_fim:int, blindada:bool, terminal:bool, continua:bool, usa_cfd:bool}>
     */
    private function metaDisciplinas(
        int $cursoId,
        array $disciplinasIds,
        int $classeNumero = 0,
        array $metaPauta = [],
    ): array {
        if ($metaPauta !== [] && $classeNumero > 0) {
            return $metaPauta;
        }

        $rows = DB::table('curso_disciplina')
            ->join('disciplinas', 'disciplinas.id', '=', 'curso_disciplina.disciplina_id')
            ->where('curso_disciplina.curso_id', $cursoId)
            ->whereIn('disciplinas.id', $disciplinasIds)
            ->select(
                'disciplinas.id as disciplina_id',
                'disciplinas.nome',
                'disciplinas.codigo',
                'curso_disciplina.classe_fim',
                'curso_disciplina.blindada',
            )
            ->get();

        $meta = [];
        foreach ($rows as $row) {
            $classeFim = (int) $row->classe_fim;
            $meta[(int) $row->disciplina_id] = [
                'disciplina_id' => (int) $row->disciplina_id,
                'nome' => $row->nome,
                'codigo' => $row->codigo,
                'classe_fim' => $classeFim,
                'blindada' => (bool) $row->blindada,
                'terminal' => $classeNumero > 0 && $classeFim === $classeNumero,
                'continua' => $classeNumero > 0 && $classeFim > $classeNumero,
                'usa_cfd' => $classeNumero > 0 && $classeFim === $classeNumero,
            ];
        }

        return $meta;
    }

    /**
     * @return array<int, int>
     */
    private function idsDisciplinasBlindadas(int $cursoId): array
    {
        return DB::table('curso_disciplina')
            ->where('curso_id', $cursoId)
            ->where('blindada', true)
            ->pluck('disciplina_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function classeNumero(Turma $turma): int
    {
        return (int) preg_replace('/\D/', '', $turma->classe?->codigo ?? $turma->classe?->nome ?? '');
    }
}
