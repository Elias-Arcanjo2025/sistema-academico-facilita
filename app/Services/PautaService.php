<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\Turma;
use App\Support\BoletimColunas;
use App\Support\BoletimNotasBuilder;
use App\Support\NotaArredondamento;
use App\Support\NotaLancamentoRules;
use App\Support\PautaRegrasTransicaoMinisterio;
use App\Services\RegimePauta\RegimePautaInterface;
use App\Services\RegimePauta\RegimePolitecnico;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PautaService
{
    /**
     * Obtém o regime de cálculo associado a uma turma (via curso).
     * Fallback seguro: regime politécnico (comportamento histórico do sistema).
     */
    public function regimeDaTurma(Turma $turma): RegimePautaInterface
    {
        $turma->loadMissing('curso');
        return $turma->curso?->regime() ?? app(RegimePolitecnico::class);
    }

    /**
     * Obtém o regime de cálculo de uma turma pelo ID.
     */
    protected function regimeDaTurmaId(int $turmaId): RegimePautaInterface
    {
        $cursoId = DB::table('turmas')->where('id', $turmaId)->value('curso_id');
        $curso = $cursoId ? Curso::find($cursoId) : null;
        return $curso?->regime() ?? app(RegimePolitecnico::class);
    }

    /**
     * Turma com elenco correcto para pauta/mini-pauta (ano anterior inclui inactivos).
     *
     * @param  list<string>  $withExtras
     */
    protected function carregarTurmaComAlunosParaPauta(int $turmaId, ?int $anoLectivoId, array $withExtras = []): Turma
    {
        $with = array_values(array_unique(array_merge(['curso', 'classe'], $withExtras)));
        $turma = Turma::with($with)->findOrFail($turmaId);
        $anoId = $anoLectivoId ?? (int) $turma->ano_lectivo_id;

        $turma->setRelation(
            'alunos',
            NotaLancamentoRules::queryAlunosDaTurmaParaNotas($turmaId, $anoId)->get()
        );

        return $turma;
    }
    /**
     * Helper centralizado de terminalidade (Plano B — fonte única de verdade).
     *
     * Determina em que classe uma disciplina termina dentro de um curso e se
     * essa terminalidade é "antecipada" (gera CFD que precisa de ser carregada
     * para classes posteriores).
     *
     * Fonte: curso_disciplina.classe_fim (vs. cursos.classe_max).
     *
     * @return ?int  N.º da classe terminal (ex.: 10, 11, 12) — null se não terminal/sem config.
     */
    public static function classeFimDisciplinaNoCurso(int $cursoId, int $disciplinaId): ?int
    {
        $classeFim = DB::table('curso_disciplina')
            ->where('curso_id', $cursoId)
            ->where('disciplina_id', $disciplinaId)
            ->value('classe_fim');

        return $classeFim ? (int) $classeFim : null;
    }

    /**
     * Devolve TRUE se a disciplina é terminal num curso ANTES de uma classe X.
     * Ou seja, gerou CFD numa classe anterior e essa CFD deve aparecer como carry-over.
     *
     *   ehTerminalAntesDe(curso, disc, 11) = true se classe_fim ∈ {10}
     *   ehTerminalAntesDe(curso, disc, 12) = true se classe_fim ∈ {10, 11}
     */
    public static function ehTerminalAntesDe(int $cursoId, int $disciplinaId, int $classeRef): bool
    {
        $classeMax = DB::table('cursos')->where('id', $cursoId)->value('classe_max') ?? 12;
        $classeFim = self::classeFimDisciplinaNoCurso($cursoId, $disciplinaId);

        if ($classeFim === null) {
            return false;
        }

        return $classeFim < $classeRef && $classeFim < (int) $classeMax;
    }

    /**
     * Devolve TRUE se a disciplina termina exactamente na classe X dentro do curso
     * E essa classe é anterior à última do curso (ou seja, gera CFD própria).
     *
     *   ehTerminalNaClasse(curso, disc, 10) = true só se classe_fim = 10 e classe_max > 10
     */
    public static function ehTerminalNaClasse(int $cursoId, int $disciplinaId, int $classeRef): bool
    {
        $classeMax = DB::table('cursos')->where('id', $cursoId)->value('classe_max') ?? 12;
        $classeFim = self::classeFimDisciplinaNoCurso($cursoId, $disciplinaId);

        return $classeFim === $classeRef && $classeRef < (int) $classeMax;
    }

    /**
     * Disciplinas terminais em classes anteriores (colunas carry-over à esquerda da pauta T3).
     *
     * @return \Illuminate\Support\Collection<int, object{disciplina_id:int, nome:string, codigo:?string, classe_numero:int}>
     */
    public static function disciplinasTerminaisAnteriores(int $cursoId, int $classeNumero): \Illuminate\Support\Collection
    {
        if ($classeNumero < 11) {
            return collect();
        }

        $cursoClasseMax = (int) (DB::table('cursos')->where('id', $cursoId)->value('classe_max') ?? 12);

        return DB::table('curso_disciplina')
            ->join('disciplinas', 'disciplinas.id', '=', 'curso_disciplina.disciplina_id')
            ->where('curso_disciplina.curso_id', $cursoId)
            ->where('disciplinas.activo', true)
            ->whereNotNull('curso_disciplina.classe_fim')
            ->where('curso_disciplina.classe_fim', '<', $classeNumero)
            ->where('curso_disciplina.classe_fim', '<', $cursoClasseMax)
            ->select(
                'disciplinas.id as disciplina_id',
                'disciplinas.nome',
                'disciplinas.codigo',
                'curso_disciplina.classe_inicio',
                'curso_disciplina.classe_fim as classe_numero'
            )
            ->orderBy('classe_numero')
            ->orderBy('disciplinas.nome')
            ->get();
    }

    /**
     * Disciplinas contínuas cuja CFD aparece na coluna da disciplina na pauta T3
     * (ex.: Portuguesa 10.ª–12.ª na 11.ª; Matemática na 12.ª).
     *
     * @return \Illuminate\Support\Collection<int, object{disciplina_id:int, nome:string, codigo:?string, classe_inicio:int, classe_fim:int}>
     */
    public static function disciplinasContinuasParaCfd(int $cursoId, int $classeNumero): \Illuminate\Support\Collection
    {
        if ($classeNumero < 11) {
            return collect();
        }

        $curso = \App\Models\Curso::find($cursoId);
        if (! $curso) {
            return collect();
        }

        $regime = $curso->regime();
        $classeMax = (int) ($curso->classe_max ?? 12);

        $pivots = DB::table('curso_disciplina')
            ->join('disciplinas', 'disciplinas.id', '=', 'curso_disciplina.disciplina_id')
            ->where('curso_disciplina.curso_id', $cursoId)
            ->where('disciplinas.activo', true)
            ->whereNotNull('curso_disciplina.classe_fim')
            ->select(
                'disciplinas.id as disciplina_id',
                'disciplinas.nome',
                'disciplinas.codigo',
                'curso_disciplina.classe_inicio',
                'curso_disciplina.classe_fim'
            )
            ->orderBy('disciplinas.nome')
            ->get();

        return $pivots->filter(function ($row) use ($cursoId, $classeNumero, $regime, $classeMax) {
            $discId = (int) $row->disciplina_id;

            if (self::ehTerminalAntesDe($cursoId, $discId, $classeNumero)) {
                return false;
            }

            $disciplina = \App\Models\Disciplina::find($discId);
            if (! $disciplina) {
                return false;
            }

            $cols = $regime->colunasDisciplina(
                3,
                $classeNumero,
                self::flagsColunasDisciplina(
                    $disciplina,
                    $row,
                    $classeNumero,
                    $classeMax,
                    self::ehTerminalNaClasse($cursoId, $discId, $classeNumero),
                    false,
                    false
                )
            );

            $temCfdNaDisciplina = collect($cols)->contains(fn ($c) => ($c['key'] ?? '') === 'cfd');
            if (! $temCfdNaDisciplina) {
                return false;
            }

            // Contínuas: ainda não terminaram nesta classe (não são terminais antecipadas já tratadas).
            $fim = (int) $row->classe_fim;

            return $fim > $classeNumero || ($classeNumero === 12 && $fim >= $classeMax);
        })->values();
    }

    /**
     * Disciplinas activas na classe actual que precisam de CA de anos anteriores
     * (colunas CA-10ª / CA-11ª na pauta T3).
     *
     * Inclui disciplinas que terminam na classe actual (ex.: LP 10.ª–11.ª na 11.ª),
     * além das que continuam para a 12.ª.
     *
     * @return \Illuminate\Support\Collection<int, object{
     *   disciplina_id:int,
     *   nome:string,
     *   codigo:?string,
     *   classe_inicio:int,
     *   classe_fim:int,
     *   tipos_ca:array<int, string>
     * }>
     */
    public static function disciplinasParaCasAnteriores(int $cursoId, int $classeNumero): Collection
    {
        if ($classeNumero < 11) {
            return collect();
        }

        $classeMax = (int) (DB::table('cursos')->where('id', $cursoId)->value('classe_max') ?? 12);

        $pivots = DB::table('curso_disciplina')
            ->join('disciplinas', 'disciplinas.id', '=', 'curso_disciplina.disciplina_id')
            ->where('curso_disciplina.curso_id', $cursoId)
            ->where('disciplinas.activo', true)
            ->whereNotNull('curso_disciplina.classe_fim')
            ->where('curso_disciplina.classe_inicio', '<=', $classeNumero)
            ->where('curso_disciplina.classe_fim', '>=', $classeNumero)
            ->select(
                'disciplinas.id as disciplina_id',
                'disciplinas.nome',
                'disciplinas.codigo',
                'curso_disciplina.classe_inicio',
                'curso_disciplina.classe_fim'
            )
            ->orderByRaw("
                CASE disciplinas.tipo
                    WHEN 'geral' THEN 1
                    WHEN 'cientifica' THEN 2
                    WHEN 'tecnica' THEN 3
                    WHEN 'projeto_estagio' THEN 4
                    ELSE 5
                END
            ")
            ->orderBy('disciplinas.ordem_apresentacao')
            ->orderBy('disciplinas.nome')
            ->get();

        $result = $pivots
            ->map(function ($row) use ($cursoId, $classeNumero, $classeMax) {
                $discId = (int) $row->disciplina_id;

                if (self::ehTerminalAntesDe($cursoId, $discId, $classeNumero)) {
                    return null;
                }

                $inicio = (int) $row->classe_inicio;
                $fim = (int) $row->classe_fim;
                $tiposCa = self::tiposCaHistoricosParaDisciplina($classeNumero, $inicio, $fim, $classeMax);

                if ($tiposCa === []) {
                    return null;
                }

                $row->tipos_ca = $tiposCa;

                return $row;
            })
            ->filter()
            ->values();

        // Disciplinas que terminaram antes da classe actual mas abrangem vários anos (ex.: LP 10.ª–11.ª na 12.ª).
        $idsJa = $result->pluck('disciplina_id')->all();

        foreach (self::disciplinasTerminaisAnteriores($cursoId, $classeNumero) as $row) {
            $discId = (int) $row->disciplina_id;

            if (in_array($discId, $idsJa, true)) {
                continue;
            }

            $pivot = DB::table('curso_disciplina')
                ->where('curso_id', $cursoId)
                ->where('disciplina_id', $discId)
                ->first();

            if (! $pivot) {
                continue;
            }

            $inicio = (int) $pivot->classe_inicio;
            $fim = (int) $pivot->classe_fim;
            $classeTerm = (int) $row->classe_numero;

            if (self::cfdIgualCaDoAno($inicio, $fim, $classeTerm, $classeMax)) {
                continue;
            }

            $tiposCa = self::tiposCaHistoricosParaDisciplina($classeNumero, $inicio, $fim, $classeMax);

            if ($tiposCa === []) {
                continue;
            }

            $row->classe_inicio = $inicio;
            $row->classe_fim = $fim;
            $row->tipos_ca = $tiposCa;
            $result->push($row);
            $idsJa[] = $discId;
        }

        return $result->values();
    }

    /**
     * Disciplinas terminais solo-classe de anos anteriores — lançar CFD directamente (ex.: TIC 10.ª–10.ª, FAI 11.ª–11.ª).
     *
     * @return \Illuminate\Support\Collection<int, object{
     *   disciplina_id:int,
     *   nome:string,
     *   codigo:?string,
     *   classe_inicio:int,
     *   classe_fim:int,
     *   classe_terminal:int
     * }>
     */
    public static function disciplinasParaCfdsAnteriores(int $cursoId, int $classeNumero): Collection
    {
        if ($classeNumero < 11) {
            return collect();
        }

        $classeMax = (int) (DB::table('cursos')->where('id', $cursoId)->value('classe_max') ?? 12);

        return self::disciplinasTerminaisAnteriores($cursoId, $classeNumero)
            ->map(function ($row) use ($cursoId, $classeMax) {
                $pivot = DB::table('curso_disciplina')
                    ->where('curso_id', $cursoId)
                    ->where('disciplina_id', $row->disciplina_id)
                    ->first();

                if (! $pivot) {
                    return null;
                }

                $inicio = (int) $pivot->classe_inicio;
                $fim = (int) $pivot->classe_fim;
                $classeTerm = (int) $row->classe_numero;

                if (! self::cfdIgualCaDoAno($inicio, $fim, $classeTerm, $classeMax)) {
                    return null;
                }

                $row->classe_inicio = $inicio;
                $row->classe_fim = $fim;
                $row->classe_terminal = $classeTerm;

                return $row;
            })
            ->filter()
            ->values();
    }

    /**
     * Tipos de CA histórica a lançar manualmente (exclui solo-classe — essas usam CFD).
     *
     * @return array<int, string>
     */
    public static function tiposCaHistoricosParaDisciplina(
        int $classeTurma,
        int $classeInicio,
        int $classeFim,
        int $classeMax
    ): array {
        $tiposCa = [];

        if ($classeTurma >= 11 && $classeInicio <= 10 && ! self::cfdIgualCaDoAno($classeInicio, $classeFim, 10, $classeMax)) {
            $tiposCa[] = Nota::TIPO_CA10;
        }

        if ($classeTurma >= 12 && $classeInicio <= 11 && $classeFim >= 11 && ! self::cfdIgualCaDoAno($classeInicio, $classeFim, 11, $classeMax)) {
            $tiposCa[] = Nota::TIPO_CA11;
        }

        return $tiposCa;
    }

    /**
     * Ano lectivo em que o aluno estava na classe N (para persistir CA histórica).
     */
    public static function anoLectivoIdParaCaHistorico(int $classeTurma, int $classeCa, int $anoLectivoIdTurma): ?int
    {
        $anosAtras = $classeTurma - $classeCa;

        if ($anosAtras <= 0) {
            return null;
        }

        return self::anoLectivoIdAnosAtras($anoLectivoIdTurma, $anosAtras);
    }

    public static function anoLectivoIdAnosAtras(int $anoLectivoId, int $anosAtras): ?int
    {
        if ($anosAtras <= 0) {
            return $anoLectivoId;
        }

        $nome = AnoLectivo::getNomeById($anoLectivoId);
        if (! $nome || ! preg_match('/^(\d{4})-(\d{4})$/', $nome, $m)) {
            return null;
        }

        $start = (int) $m[1] - $anosAtras;
        $nomeAlvo = $start.'-'.($start + 1);

        return AnoLectivo::resolverIdPorNome($nomeAlvo);
    }

    /**
     * Carrega CAs de anos anteriores [aluno_id][disciplina_id]['ca10'|'ca11'] => valor.
     *
     * @param  \Illuminate\Support\Collection<int, object{disciplina_id:int, tipos_ca?:array<int, string>}>  $disciplinas
     * @return array<int, array<int, array<string, float>>>
     */
    public static function carregarCasAnterioresPorDisciplinas(
        Collection $disciplinas,
        Collection $alunosIds,
        int $classeTurma,
        int $anoLectivoIdTurma
    ): array {
        if ($disciplinas->isEmpty() || $alunosIds->isEmpty()) {
            return [];
        }

        $discIds = $disciplinas->pluck('disciplina_id')->all();
        $cas = [];

        $tiposPorAno = [];
        foreach ($disciplinas as $row) {
            foreach ($row->tipos_ca ?? [] as $tipo) {
                $classeCa = (int) substr($tipo, 2);
                $anoId = self::anoLectivoIdParaCaHistorico($classeTurma, $classeCa, $anoLectivoIdTurma);
                if ($anoId) {
                    $tiposPorAno[$anoId][] = $tipo;
                }
            }
        }

        foreach ($tiposPorAno as $anoId => $tipos) {
            $tipos = array_unique($tipos);
            $notas = Nota::whereIn('aluno_id', $alunosIds)
                ->whereIn('disciplina_id', $discIds)
                ->whereIn('tipo', $tipos)
                ->where('ano_lectivo_id', $anoId)
                ->get();

            foreach ($notas as $n) {
                $key = strtolower($n->tipo);
                $cas[$n->aluno_id][$n->disciplina_id][$key] = (float) $n->valor;
            }
        }

        $classesCa = [];
        foreach ($disciplinas as $row) {
            foreach ($row->tipos_ca ?? [] as $tipo) {
                $classesCa[] = (int) substr($tipo, 2);
            }
        }
        $classesCa = array_values(array_unique($classesCa));

        self::preencherCasComCfdFallback(
            $cas,
            $alunosIds,
            $discIds,
            $classeTurma,
            $anoLectivoIdTurma,
            $classesCa
        );

        return $cas;
    }

    /**
     * Carrega CFDs de disciplinas terminais solo-classe [aluno_id][disciplina_id] => valor.
     *
     * @param  \Illuminate\Support\Collection<int, object{disciplina_id:int, classe_terminal:int}>  $disciplinas
     * @return array<int, array<int, float>>
     */
    public static function carregarCfdsAnterioresPorDisciplinas(
        Collection $disciplinas,
        Collection $alunosIds,
        int $classeTurma,
        int $anoLectivoIdTurma
    ): array {
        if ($disciplinas->isEmpty() || $alunosIds->isEmpty()) {
            return [];
        }

        $cfds = [];
        $discIds = $disciplinas->pluck('disciplina_id')->all();

        $anosPorClasse = [];
        foreach ($disciplinas as $row) {
            $classeTerm = (int) ($row->classe_terminal ?? $row->classe_numero ?? 0);
            $anoId = self::anoLectivoIdParaCaHistorico($classeTurma, $classeTerm, $anoLectivoIdTurma);
            if ($anoId) {
                $anosPorClasse[$classeTerm] = $anoId;
            }
        }

        foreach ($anosPorClasse as $anoId) {
            $notas = Nota::whereIn('aluno_id', $alunosIds)
                ->whereIn('disciplina_id', $discIds)
                ->where('tipo', Nota::TIPO_CFD)
                ->where('ano_lectivo_id', $anoId)
                ->get();

            foreach ($notas as $n) {
                $cfds[$n->aluno_id][$n->disciplina_id] = (float) $n->valor;
            }
        }

        $notasAnoActual = Nota::whereIn('aluno_id', $alunosIds)
            ->whereIn('disciplina_id', $discIds)
            ->where('tipo', Nota::TIPO_CFD)
            ->where('ano_lectivo_id', $anoLectivoIdTurma)
            ->get();

        foreach ($notasAnoActual as $n) {
            if (! isset($cfds[$n->aluno_id][$n->disciplina_id])) {
                $cfds[$n->aluno_id][$n->disciplina_id] = (float) $n->valor;
            }
        }

        foreach ($disciplinas as $row) {
            $classeTerm = (int) ($row->classe_terminal ?? $row->classe_numero ?? 0);
            if ($classeTerm <= 0) {
                continue;
            }

            $tipoCa = self::tipoCaParaClasse($classeTerm);
            $anoId = $anosPorClasse[$classeTerm] ?? null;

            if (! $anoId) {
                continue;
            }

            $casLegado = Nota::whereIn('aluno_id', $alunosIds)
                ->where('disciplina_id', (int) $row->disciplina_id)
                ->where('tipo', $tipoCa)
                ->where('ano_lectivo_id', $anoId)
                ->get();

            foreach ($casLegado as $n) {
                if (! isset($cfds[$n->aluno_id][$n->disciplina_id])) {
                    $cfds[$n->aluno_id][$n->disciplina_id] = (float) $n->valor;
                }
            }
        }

        return $cfds;
    }

    /**
     * Preenche slots CA em falta com CFD do mesmo ano lectivo (dados já lançados online).
     * A CA gravada tem sempre precedência sobre a CFD.
     *
     * @param  array<int, array<int, array<string, float>>>  $caMap
     * @param  array<int, int>  $disciplinasIds
     * @param  array<int, int>  $classesCa  Ex.: [10] na 11.ª; [10, 11] na 12.ª
     */
    public static function preencherCasComCfdFallback(
        array &$caMap,
        Collection|array $alunosIds,
        array $disciplinasIds,
        int $classeTurma,
        int $anoLectivoIdTurma,
        array $classesCa
    ): void {
        $alunosIds = collect($alunosIds);

        if ($alunosIds->isEmpty() || $disciplinasIds === []) {
            return;
        }

        foreach ($classesCa as $classeCa) {
            $tipoCa = strtolower(self::tipoCaParaClasse($classeCa));
            $anoId = self::anoLectivoIdParaCaHistorico($classeTurma, $classeCa, $anoLectivoIdTurma);
            if (! $anoId) {
                continue;
            }

            $cfds = Nota::whereIn('aluno_id', $alunosIds)
                ->whereIn('disciplina_id', $disciplinasIds)
                ->where('tipo', Nota::TIPO_CFD)
                ->where('ano_lectivo_id', $anoId)
                ->get();

            foreach ($cfds as $n) {
                if (! isset($caMap[$n->aluno_id][$n->disciplina_id][$tipoCa])) {
                    $caMap[$n->aluno_id][$n->disciplina_id][$tipoCa] = (float) $n->valor;
                }
            }

            // CFDs de terminais anteriores lançadas no ano actual (fluxo legado)
            $cfdsAnoActual = Nota::whereIn('aluno_id', $alunosIds)
                ->whereIn('disciplina_id', $disciplinasIds)
                ->where('tipo', Nota::TIPO_CFD)
                ->where('ano_lectivo_id', $anoLectivoIdTurma)
                ->get();

            foreach ($cfdsAnoActual as $n) {
                if (! isset($caMap[$n->aluno_id][$n->disciplina_id][$tipoCa])) {
                    $caMap[$n->aluno_id][$n->disciplina_id][$tipoCa] = (float) $n->valor;
                }
            }
        }
    }

    /**
     * Carrega CFDs manuais/calculadas [aluno_id][disciplina_id] => valor.
     *
     * @param  \Illuminate\Support\Collection<int, object{disciplina_id:int}>  $disciplinas
     * @return array<int, array<int, float>>
     */
    public static function carregarCfdsPorDisciplinas(
        \Illuminate\Support\Collection $disciplinas,
        \Illuminate\Support\Collection $alunosIds,
        int $anoLectivoId
    ): array {
        if ($disciplinas->isEmpty() || $alunosIds->isEmpty()) {
            return [];
        }

        $discIds = $disciplinas->pluck('disciplina_id')->all();
        $cfds = [];

        $notas = Nota::whereIn('aluno_id', $alunosIds)
            ->whereIn('disciplina_id', $discIds)
            ->where('tipo', Nota::TIPO_CFD)
            ->orderBy('ano_lectivo_id', 'desc')
            ->get();

        foreach ($notas as $n) {
            if (! isset($cfds[$n->aluno_id][$n->disciplina_id])
                || $n->ano_lectivo_id === $anoLectivoId
            ) {
                $cfds[$n->aluno_id][$n->disciplina_id] = (float) $n->valor;
            }
        }

        return $cfds;
    }

    /**
     * Flags para o regime determinar o esquema de colunas de uma disciplina.
     *
     * @return array<string, mixed>
     */
    public static function flagsColunasDisciplina(
        \App\Models\Disciplina $disciplina,
        ?object $pivot,
        int $classeNumero,
        int $classeMax,
        bool $terminalClasseAtual,
        bool $recursoDisciplina,
        bool $temRecurso,
        bool $recuperacaoDisciplina = false,
        bool $temRecuperacao = false,
    ): array {
        $inicio = $pivot?->classe_inicio ? (int) $pivot->classe_inicio : $classeNumero;
        $fim = $pivot?->classe_fim ? (int) $pivot->classe_fim : $classeNumero;

        return [
            'recurso' => $temRecurso,
            'recurso_disciplina' => $recursoDisciplina,
            'recuperacao' => $temRecuperacao,
            'recuperacao_disciplina' => $recuperacaoDisciplina,
            'terminal_classe_atual' => $terminalClasseAtual,
            'classe_inicio' => $inicio,
            'classe_fim' => $fim,
            'classe_max' => $classeMax,
            'faltas_fundidas' => $classeNumero === 12,
            'usa_mtp' => RegimePolitecnico::usaMtpTrimestre1($disciplina->nome, $disciplina->codigo),
            'projeto_tecnologico' => RegimePolitecnico::ehProjetoTecnologico($disciplina->nome, $disciplina->codigo),
        ];
    }

    /**
     * CFD conforme intervalo da disciplina no curso (contínua → terminal).
     *
     * - início = fim → CFD = CA desse ano
     * - 10.ª–11.ª → (CA10+CA11)/2
     * - 10.ª–12.ª → (CA10+CA11+CA12)/3
     * - 11.ª–12.ª → (CA11+CA12)/2
     */
    public static function calcularCfdDeCasPorIntervalo(
        int $classeInicio,
        int $classeFim,
        ?float $ca10 = null,
        ?float $ca11 = null,
        ?float $ca12 = null,
    ): ?float {
        if ($classeInicio === $classeFim) {
            $ca = match ($classeFim) {
                10 => $ca10,
                11 => $ca11,
                12 => $ca12,
                default => null,
            };

            return $ca !== null ? round($ca) : null;
        }

        return match (true) {
            $classeInicio <= 10 && $classeFim === 11 => PautaRegrasTransicaoMinisterio::calcularCfdPorMedia($ca11, $ca10),
            $classeInicio <= 10 && $classeFim === 12 => PautaRegrasTransicaoMinisterio::calcularCfdPorMedia($ca12, $ca10, $ca11),
            $classeInicio === 11 && $classeFim === 12 => PautaRegrasTransicaoMinisterio::calcularCfdPorMedia($ca12, $ca11),
            default => null,
        };
    }

    /**
     * Preenche CFD carry-over em falta a partir das CAs históricas (multi-ano e solo).
     *
     * @param  array<int, array<int, float>>  $cfdTerminaisHistorico
     * @param  \Illuminate\Support\Collection<int, object{disciplina_id:int, classe_numero:int, classe_inicio?:int}>  $disciplinasTerminais
     * @param  array<int>  $alunosIds
     */
    public static function preencherCfdTerminaisFromCas(
        array &$cfdTerminaisHistorico,
        Collection $disciplinasTerminais,
        $alunosIds,
        int $classeTurma,
        int $anoLectivoIdTurma,
    ): void {
        if ($disciplinasTerminais->isEmpty() || empty($alunosIds)) {
            return;
        }

        $casPorAlunoDisc = self::carregarCasHistoricasTerminais(
            $disciplinasTerminais,
            collect($alunosIds),
            $classeTurma,
            $anoLectivoIdTurma,
        );

        foreach ($disciplinasTerminais as $dt) {
            $discId = (int) (is_array($dt) ? $dt['disciplina_id'] : $dt->disciplina_id);
            $fim = (int) (is_array($dt)
                ? ($dt['classe_numero'] ?? $dt['classe_fim'] ?? 0)
                : ($dt->classe_numero ?? $dt->classe_fim ?? 0));
            $inicio = (int) (is_array($dt)
                ? ($dt['classe_inicio'] ?? $fim)
                : ($dt->classe_inicio ?? $fim));

            foreach ($alunosIds as $alunoId) {
                $alunoId = (int) $alunoId;
                if (isset($cfdTerminaisHistorico[$alunoId][$discId])) {
                    continue;
                }

                $cas = $casPorAlunoDisc[$alunoId][$discId] ?? [];
                $cfd = self::calcularCfdDeCasPorIntervalo(
                    $inicio,
                    $fim,
                    isset($cas['ca10']) ? (float) $cas['ca10'] : null,
                    isset($cas['ca11']) ? (float) $cas['ca11'] : null,
                    isset($cas['ca12']) ? (float) $cas['ca12'] : null,
                );

                if ($cfd !== null) {
                    $cfdTerminaisHistorico[$alunoId][$discId] = $cfd;
                }
            }
        }
    }

    /**
     * CAs históricas das disciplinas terminais anteriores [aluno][disc][ca10|ca11|ca12].
     *
     * @param  \Illuminate\Support\Collection<int, object|array>  $disciplinasTerminais
     * @return array<int, array<int, array<string, float>>>
     */
    public static function carregarCasHistoricasTerminais(
        Collection $disciplinasTerminais,
        Collection $alunosIds,
        int $classeTurma,
        int $anoLectivoIdTurma,
    ): array {
        if ($disciplinasTerminais->isEmpty() || $alunosIds->isEmpty()) {
            return [];
        }

        $discIds = $disciplinasTerminais->map(fn ($dt) => (int) (is_array($dt) ? $dt['disciplina_id'] : $dt->disciplina_id))->all();
        $cas = [];

        $tiposPorAno = [];
        foreach ($disciplinasTerminais as $dt) {
            $inicio = (int) (is_array($dt) ? ($dt['classe_inicio'] ?? $dt['classe_numero'] ?? 0) : ($dt->classe_inicio ?? $dt->classe_numero ?? 0));
            $fim = (int) (is_array($dt) ? ($dt['classe_numero'] ?? $dt['classe_fim'] ?? 0) : ($dt->classe_numero ?? $dt->classe_fim ?? 0));

            foreach (self::tiposCaHistoricosParaDisciplina($classeTurma, $inicio, $fim, 12) as $tipo) {
                $classeCa = (int) substr($tipo, 2);
                $anoId = self::anoLectivoIdParaCaHistorico($classeTurma, $classeCa, $anoLectivoIdTurma);
                if ($anoId) {
                    $tiposPorAno[$anoId][] = $tipo;
                }
            }

            // Solo-classe: CA do ano terminal (mesmo se tiposCaHistoricos estiver vazio)
            if ($inicio === $fim && in_array($fim, [10, 11], true)) {
                $tipo = self::tipoCaParaClasse($fim);
                $anoId = self::anoLectivoIdParaCaHistorico($classeTurma, $fim, $anoLectivoIdTurma);
                if ($anoId) {
                    $tiposPorAno[$anoId][] = $tipo;
                }
            }
        }

        foreach ($tiposPorAno as $anoId => $tipos) {
            $tipos = array_unique($tipos);
            $notas = Nota::whereIn('aluno_id', $alunosIds)
                ->whereIn('disciplina_id', $discIds)
                ->whereIn('tipo', $tipos)
                ->where('ano_lectivo_id', $anoId)
                ->get();

            foreach ($notas as $n) {
                $key = strtolower((string) $n->tipo);
                if (! isset($cas[$n->aluno_id][$n->disciplina_id][$key])) {
                    $cas[$n->aluno_id][$n->disciplina_id][$key] = (float) $n->valor;
                }
            }
        }

        // Fallback: CAs lançadas noutro ano lectivo (mesmos tipos), sem sobrescrever
        if ($tiposPorAno !== []) {
            $todosTipos = array_values(array_unique(array_merge(...array_values($tiposPorAno))));
            $notasExtra = Nota::whereIn('aluno_id', $alunosIds)
                ->whereIn('disciplina_id', $discIds)
                ->whereIn('tipo', $todosTipos)
                ->orderBy('ano_lectivo_id', 'desc')
                ->get();

            foreach ($notasExtra as $n) {
                $key = strtolower((string) $n->tipo);
                if (! isset($cas[$n->aluno_id][$n->disciplina_id][$key])) {
                    $cas[$n->aluno_id][$n->disciplina_id][$key] = (float) $n->valor;
                }
            }
        }

        return $cas;
    }

    /**
     * Disciplina existe só numa classe (início = fim): CFD = CA desse ano.
     *
     * Ex.: TIC 10.ª–10.ª, DT 11.ª–11.ª, OGI 12.ª–12.ª.
     *
     * Quando a disciplina abrange vários anos (ex.: LP 10.ª–11.ª, MAT 10.ª–12.ª),
     * a CFD é média das CAs — ver calcularCfdPreview / calcularEGuardarCFD.
     */
    public static function cfdIgualCaDoAno(
        int $classeInicio,
        int $classeFim,
        int $classeActual,
        int $classeMax
    ): bool {
        if ($classeActual !== $classeFim) {
            return false;
        }

        return $classeInicio === $classeFim;
    }

    public static function tipoCaParaClasse(int $classe): string
    {
        return match ($classe) {
            10 => Nota::TIPO_CA10,
            11 => Nota::TIPO_CA11,
            12 => Nota::TIPO_CA12,
            default => throw new \InvalidArgumentException("Classe inválida: {$classe}"),
        };
    }

    /**
     * Calcula a média trimestral (mini-pauta) para um aluno numa disciplina.
     *
     * Só calcula quando TODAS as componentes do regime existem
     * (T3 politécnico: MAC+PP; T1/T2: MAC+PP+PT). Evita MT prematura com zeros.
     *
     * Prioriza notas pós-conselho (ajustadas em conselho) sobre pré-conselho.
     */
    public function calcularMediaTrimestral(
        int $alunoId,
        int $disciplinaId,
        int $turmaId,
        int $trimestre,
        ?string $anoLectivo = null
    ): ?float {
        $anoLectivo = $anoLectivo ?? $this->getAnoLectivoActivo();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);
        $regime = $this->regimeDaTurmaId($turmaId);
        $tiposNecessarios = $regime->componentesMediaTrimestral($trimestre);
        $divisor = $regime->divisorMediaTrimestral($trimestre);

        // Para cada tipo (MAC, PP, PT conforme o regime), prefere pós-conselho
        $notas = [];

        foreach ($tiposNecessarios as $tipo) {
            $valor = Nota::where('aluno_id', $alunoId)
                ->where('disciplina_id', $disciplinaId)
                ->where('turma_id', $turmaId)
                ->where('trimestre', $trimestre)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('tipo', $tipo)
                ->where('estado_conselho', Nota::ESTADO_POS_CONSELHO)
                ->value('valor');

            if ($valor === null) {
                $valor = Nota::where('aluno_id', $alunoId)
                    ->where('disciplina_id', $disciplinaId)
                    ->where('turma_id', $turmaId)
                    ->where('trimestre', $trimestre)
                    ->where('ano_lectivo_id', $anoLectivoId)
                    ->where('tipo', $tipo)
                    ->where('estado_conselho', Nota::ESTADO_PRE_CONSELHO)
                    ->value('valor');
            }

            if ($valor !== null) {
                $notas[$tipo] = (float) $valor;
            }
        }

        // Só calcula MT com TODAS as componentes (evita MT prematura com zeros).
        if (count($notas) < count($tiposNecessarios)) {
            return null;
        }

        $media = array_sum($notas) / $divisor;

        // Garantir que MT está entre 0 e 20
        $media = max(0, min(20, $media));

        // Arredondar por defeito (<0,5) ou por excesso (≥0,5)
        return NotaArredondamento::media($media);
    }

    /**
     * MT persistida ou calculada a partir de MAC/PP/PT no índice em memória.
     * Replica calcularMediaTrimestral() sem consultas à BD (p. ex. após importação Excel).
     */
    protected function mtEfectivaFromIdx(
        array $notasIdx,
        int $alunoId,
        int $disciplinaId,
        int $trimestre,
        RegimePautaInterface $regime,
        bool $ignorarMtPersistida = false,
    ): ?float {
        if (! $ignorarMtPersistida) {
            $mtPersistida = $notasIdx[$alunoId][$disciplinaId][$trimestre][Nota::TIPO_MT] ?? null;
            if ($mtPersistida !== null) {
                return NotaArredondamento::media((float) $mtPersistida);
            }
        }

        $tipos = $regime->componentesMediaTrimestral($trimestre);
        $divisor = $regime->divisorMediaTrimestral($trimestre);
        $notas = [];

        foreach ($tipos as $tipo) {
            $valor = $notasIdx[$alunoId][$disciplinaId][$trimestre][$tipo] ?? null;
            if ($valor !== null) {
                $notas[$tipo] = (float) $valor;
            }
        }

        // Alinhado a calcularMediaTrimestral: só com componentes completas.
        if (count($notas) < count($tipos)) {
            return null;
        }

        $media = max(0, min(20, array_sum($notas) / $divisor));

        return NotaArredondamento::media($media);
    }

    /**
     * Indexa notas para a pauta conforme o momento (pré ou pós conselho).
     *
     * @param  \Illuminate\Support\Collection<int, Nota>  $todasNotas
     * @return array<int, array<int, array<int|null, array<string, float>>>>
     */
    protected function construirNotasIdxPauta(
        \Illuminate\Support\Collection $todasNotas,
        string $momentoConselho,
        bool $ignorarMtPersistida,
        int $trimestrePauta,
    ): array {
        $grupos = [];

        foreach ($todasNotas as $n) {
            $grupos[$n->aluno_id][$n->disciplina_id][$n->trimestre][$n->tipo][] = $n;
        }

        $notasIdx = [];
        $tiposConselho = [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT];

        foreach ($grupos as $alunoId => $porDisciplina) {
            foreach ($porDisciplina as $disciplinaId => $porTrimestre) {
                foreach ($porTrimestre as $trimestreNota => $porTipo) {
                    $trimestreNotaNorm = ($trimestreNota === null || $trimestreNota === '')
                        ? null
                        : (int) $trimestreNota;

                    $momentoCelula = $this->momentoConselhoParaTrimestre(
                        $trimestreNotaNorm,
                        $trimestrePauta,
                        $momentoConselho,
                    );
                    $ignorarMtCelula = $ignorarMtPersistida
                        && $trimestreNotaNorm !== null
                        && $trimestreNotaNorm === $trimestrePauta;

                    foreach ($porTipo as $tipo => $notasDoTipo) {
                        if ($ignorarMtCelula && $tipo === Nota::TIPO_MT) {
                            continue;
                        }

                        $colectao = collect($notasDoTipo);

                        if (in_array($tipo, $tiposConselho, true)) {
                            $seleccionada = Nota::seleccionarNotaPorMomento($colectao, $momentoCelula);
                        } else {
                            $seleccionada = Nota::seleccionarNotaPorMomento($colectao, $momentoCelula)
                                ?? Nota::seleccionarNotaEfectiva($colectao);
                        }

                        if ($seleccionada !== null) {
                            $notasIdx[$alunoId][$disciplinaId][$trimestreNota][$tipo] = (float) $seleccionada->valor;
                        }
                    }
                }
            }
        }

        return $notasIdx;
    }

    protected function momentoConselhoParaTrimestre(
        ?int $trimestreNota,
        int $trimestrePauta,
        string $momentoPauta,
    ): string {
        if ($trimestreNota === null) {
            return \App\Support\PautaMomentoConselho::POS;
        }

        if ((int) $trimestreNota === $trimestrePauta) {
            return $momentoPauta;
        }

        return \App\Support\PautaMomentoConselho::POS;
    }

    /**
     * Calcula e guarda a média trimestral como nota do tipo MT
     */
    public function calcularEGuardarMediaTrimestral(
        int $alunoId,
        int $disciplinaId,
        int $turmaId,
        int $trimestre,
        int $professorId,
        ?string $anoLectivo = null
    ): ?Nota {
        $anoLectivo = $anoLectivo ?? $this->getAnoLectivoActivo();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);

        $media = $this->calcularMediaTrimestral(
            $alunoId,
            $disciplinaId,
            $turmaId,
            $trimestre,
            $anoLectivo
        );

        if ($media === null) {
            $notaMT = Nota::where('aluno_id', $alunoId)
                ->where('disciplina_id', $disciplinaId)
                ->where('turma_id', $turmaId)
                ->where('trimestre', $trimestre)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('tipo', Nota::TIPO_MT)
                ->whereNull('archived_at')
                ->preConselhoLancamento()
                ->first();

            if ($notaMT) {
                $notaMT->archive('Sem componentes de avaliação — MT removida', $professorId);
            }

            return null;
        }

        // Verificar se já existe uma MT para este aluno/disciplina/trimestre
        $notaMT = Nota::where('aluno_id', $alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('trimestre', $trimestre)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('tipo', Nota::TIPO_MT)
            ->first();

        if ($notaMT) {
            $valorAnterior = $notaMT->valor;

            $notaMT->update([
                'valor' => $media,
                'professor_id' => $professorId,
                'observacoes' => sprintf(
                    'Recalculada em %s por %s (anterior: %.1f)',
                    now()->format('d/m/Y H:i'),
                    auth()->user()->nome ?? 'Sistema',
                    $valorAnterior
                ),
            ]);
        } else {
            $notaMT = Nota::create([
                'aluno_id' => $alunoId,
                'disciplina_id' => $disciplinaId,
                'turma_id' => $turmaId,
                'professor_id' => $professorId,
                'tipo' => Nota::TIPO_MT,
                'valor' => $media,
                'trimestre' => $trimestre,
                'ano_lectivo' => $anoLectivo,
                'ano_lectivo_id' => $anoLectivoId,
                'observacoes' => sprintf(
                    'Calculada automaticamente em %s por %s',
                    now()->format('d/m/Y H:i'),
                    auth()->user()->nome ?? 'Sistema'
                ),
            ]);
        }

        return $notaMT;
    }

    /**
     * Calcula a média anual (pauta final) para um aluno numa disciplina
     */
    public function calcularMediaAnual(
        int $alunoId,
        int $disciplinaId,
        int $turmaId,
        ?string $anoLectivo = null
    ): ?float {
        $anoLectivo = $anoLectivo ?? $this->getAnoLectivoActivo();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);

        // Buscar as médias trimestrais
        $mediasTrimestres = Nota::where('aluno_id', $alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('tipo', Nota::TIPO_MT)
            ->pluck('valor', 'trimestre');

        // Precisa de todos os 3 trimestres
        if ($mediasTrimestres->count() < 3) {
            return null;
        }

        // Média aritmética: (MT1 + MT2 + MT3) / 3
        $media = $mediasTrimestres->avg();

        return NotaArredondamento::media((float) $media);
    }

    /**
     * Calcula e guarda a nota final
     */
    public function calcularEGuardarNotaFinal(
        int $alunoId,
        int $disciplinaId,
        int $turmaId,
        int $professorId,
        ?string $anoLectivo = null
    ): ?Nota {
        $anoLectivo = $anoLectivo ?? $this->getAnoLectivoActivo();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);

        $mediaAnual = $this->calcularMediaAnual(
            $alunoId,
            $disciplinaId,
            $turmaId,
            $anoLectivo
        );

        if ($mediaAnual === null) {
            return null;
        }

        // Verificar se já existe uma nota final
        $notaFinal = Nota::where('aluno_id', $alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('tipo', Nota::TIPO_FINAL)
            ->first();

        if ($notaFinal) {
            $notaFinal->update([
                'valor' => $mediaAnual,
                'professor_id' => $professorId,
            ]);
        } else {
            $notaFinal = Nota::create([
                'aluno_id' => $alunoId,
                'disciplina_id' => $disciplinaId,
                'turma_id' => $turmaId,
                'professor_id' => $professorId,
                'tipo' => Nota::TIPO_FINAL,
                'valor' => $mediaAnual,
                'trimestre' => null,
                'ano_lectivo' => $anoLectivo,
                'ano_lectivo_id' => $anoLectivoId,
                'observacoes' => 'Calculada automaticamente',
            ]);
        }

        return $notaFinal;
    }

    /**
     * Recalcula todas as médias de um aluno numa disciplina
     */
    public function recalcularMediasAlunoDisciplina(
        int $alunoId,
        int $disciplinaId,
        int $turmaId,
        int $professorId,
        ?string $anoLectivo = null
    ): array {
        $anoLectivo = $anoLectivo ?? $this->getAnoLectivoActivo();
        $resultado = [
            'medias_trimestrais' => [],
            'nota_final' => null,
        ];

        // Recalcular cada trimestre
        for ($trimestre = 1; $trimestre <= 3; $trimestre++) {
            $mt = $this->calcularEGuardarMediaTrimestral(
                $alunoId,
                $disciplinaId,
                $turmaId,
                $trimestre,
                $professorId,
                $anoLectivo
            );

            if ($mt) {
                $resultado['medias_trimestrais'][$trimestre] = $mt->valor;
            }
        }

        // Se tiver todas as MTs, calcula a nota final
        if (count($resultado['medias_trimestrais']) === 3) {
            $notaFinal = $this->calcularEGuardarNotaFinal(
                $alunoId,
                $disciplinaId,
                $turmaId,
                $professorId,
                $anoLectivo
            );

            if ($notaFinal) {
                $resultado['nota_final'] = $notaFinal->valor;
            }
        }

        return $resultado;
    }

    /**
     * Gera a mini-pauta de uma turma/disciplina/trimestre
     */
    public function gerarMiniPauta(
        int $turmaId,
        int $disciplinaId,
        int $trimestre,
        ?string $anoLectivo = null
    ): Collection {
        $anoLectivo = $anoLectivo ?? $this->getAnoLectivoActivo();
        $anoLectivoId = (int) \App\Models\AnoLectivo::getIdByNome($anoLectivo);

        $turma = $this->carregarTurmaComAlunosParaPauta($turmaId, $anoLectivoId);

        $disciplina = Disciplina::findOrFail($disciplinaId);
        $regime = $turma->curso->regime();
        $classeNumero = (int) ($turma->classe->codigo ?? 10);

        $notasQuery = Nota::query()
            ->whereIn('aluno_id', $turma->alunos->pluck('id'))
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('ano_lectivo_id', $anoLectivoId);
        NotaLancamentoRules::aplicarVisibilidadeArquivo($notasQuery, $anoLectivoId, true);

        $notasPorAluno = $notasQuery->get()->groupBy('aluno_id');

        $miniPauta = collect();

        foreach ($turma->alunos as $aluno) {
            $valores = BoletimNotasBuilder::paraAluno($aluno, $anoLectivo)
                ->comNotas($notasPorAluno->get($aluno->id, collect()))
                ->linhaTrimestre($disciplina, $trimestre, $regime, $classeNumero);

            $chaveResultado = BoletimColunas::chaveResultado($trimestre, $regime);
            $valorResultado = $valores[$chaveResultado] ?? null;

            $ehDesistente = $this->ehDesistenteMiniPauta(
                $aluno,
                $valores['mac'],
                $valores['pp'],
                $valores['pt'],
                $turmaId,
                $trimestre
            );

            $situacao = $ehDesistente
                ? 'desistente'
                : $this->determinarSituacao($valorResultado);

            $linha = [
                'aluno' => $aluno,
                'numero_ordem' => $aluno->numero_ordem,
                'nome' => $aluno->nome,
                'genero' => $aluno->genero,
                'valores' => $valores,
                'valor_resultado' => $valorResultado,
                'situacao' => $situacao,
            ];

            foreach ($valores as $chave => $valor) {
                $linha[$chave] = $valor;
            }

            $miniPauta->push($linha);
        }

        return $miniPauta;
    }

    /**
     * Colunas e chave de resultado da mini-pauta (alinhadas ao boletim).
     *
     * @return array{colunas: array<int, array{key: string, header: string}>, chave_resultado: string}
     */
    public function metaMiniPauta(int $turmaId, int $trimestre): array
    {
        $turma = Turma::with(['curso', 'classe'])->findOrFail($turmaId);
        $regime = $turma->curso->regime();
        $classeNumero = (int) ($turma->classe->codigo ?? 10);

        return [
            'colunas' => BoletimColunas::paraTrimestre($trimestre, $regime, $classeNumero),
            'chave_resultado' => BoletimColunas::chaveResultado($trimestre, $regime),
        ];
    }

    /**
     * Gera a pauta final de uma turma/disciplina
     */
    public function gerarPautaFinalDisciplina(
        int $turmaId,
        int $disciplinaId,
        ?string $anoLectivo = null
    ): Collection {
        $anoLectivo = $anoLectivo ?? $this->getAnoLectivoActivo();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);

        $turma = $this->carregarTurmaComAlunosParaPauta($turmaId, $anoLectivoId ? (int) $anoLectivoId : null);

        $fallbackPt3ComoPg = ! $turma->curso?->regime()->usaProvaTrimestral(3);

        $pautaFinal = collect();

        // Buscar classe da turma para determinar CA
        $turmaComClasse = Turma::with('classe')->findOrFail($turmaId);
        $classeAtual = $turmaComClasse->classe?->codigo ?? null;

        // Otimização N+1: Buscar todas as notas de uma vez
        $alunosIds = $turma->alunos->pluck('id');
        $todasNotas = Nota::whereIn('aluno_id', $alunosIds)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->get()
            ->groupBy('aluno_id');

        foreach ($turma->alunos as $aluno) {
            $notasDoAluno = $todasNotas->get($aluno->id, collect());
            $notas = $notasDoAluno->keyBy(function ($nota) {
                if ($nota->tipo === Nota::TIPO_MT) {
                    return 'mt' . $nota->trimestre;
                }
                return $nota->tipo;
            });

            // Determinar qual CA exibir baseado na classe
            $tipoCA = null;
            if ($classeAtual == 10) {
                $tipoCA = Nota::TIPO_CA10;
            } elseif ($classeAtual == 11) {
                $tipoCA = Nota::TIPO_CA11;
            } elseif ($classeAtual == 12) {
                $tipoCA = Nota::TIPO_CA12;
            }

            // Auto-desistência ao nível da turma (>3 disciplinas sem qualquer
            // nota lançada no ano lectivo) ou marcação manual.
            $ehDesistente = $this->ehDesistentePauta($aluno, $turmaId, $anoLectivo, null);

            $cfdRaw = $notas->get(Nota::TIPO_CFD)?->valor;
            $finaisParaCfd = [
                'cfd' => $cfdRaw,
                'recurso' => $notas->get(Nota::TIPO_RECURSO)?->valor,
                'melhoria' => $notas->get(Nota::TIPO_MELHORIA)?->valor,
                'extraordinario' => $notas->get(Nota::TIPO_EXTRAORDINARIO)?->valor,
            ];
            $cfdEfectiva = $cfdRaw !== null
                ? \App\Support\PautaCalculos::cfdEfectivaDisciplina($finaisParaCfd, (float) $cfdRaw)
                : null;

            $situacao = $ehDesistente
                ? 'desistente'
                : $this->determinarSituacao($cfdEfectiva ?? $notas->get(Nota::TIPO_FINAL)?->valor);

            $pautaFinal->push([
                'aluno' => $aluno,
                'numero_ordem' => $aluno->numero_ordem,
                'nome' => $aluno->nome,
                'mt1' => $notas->get('mt1')?->valor,
                'mt2' => $notas->get('mt2')?->valor,
                'mt3' => $notas->get('mt3')?->valor,
                'mft2' => $notas->get(Nota::TIPO_MFT2)?->valor,
                'cf' => $notas->get(Nota::TIPO_CF)?->valor,
                'pg' => $fallbackPt3ComoPg
                    ? Nota::valorProvaGlobal($aluno->id, $disciplinaId, $turmaId, $anoLectivoId)
                    : $notas->get(Nota::TIPO_PG)?->valor,
                'ca' => $tipoCA ? $notas->get($tipoCA)?->valor : null,
                'ca_tipo' => $tipoCA,
                'cfd' => $cfdEfectiva,
                'nota_final' => $notas->get(Nota::TIPO_FINAL)?->valor,
                'situacao' => $situacao,
                'desistente' => $ehDesistente,
            ]);
        }

        return $pautaFinal;
    }

    /**
     * Gera a pauta final completa de uma turma (todas as disciplinas)
     */
    public function gerarPautaFinalTurma(
        int $turmaId,
        ?string $anoLectivo = null
    ): array {
        $anoLectivo = $anoLectivo ?? $this->getAnoLectivoActivo();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);

        $turma = $this->carregarTurmaComAlunosParaPauta(
            $turmaId,
            $anoLectivoId ? (int) $anoLectivoId : null,
            ['curso.disciplinas']
        );

        $disciplinas = $turma->curso->disciplinas;

        // Otimização N+1: Buscar todas as notas finais de uma vez
        $alunosIds = $turma->alunos->pluck('id');
        $disciplinasIds = $disciplinas->pluck('id');
        $todasNotas = Nota::whereIn('aluno_id', $alunosIds)
            ->whereIn('disciplina_id', $disciplinasIds)
            ->where('turma_id', $turmaId)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('tipo', Nota::TIPO_FINAL)
            ->get()
            ->groupBy(function ($nota) {
                return $nota->aluno_id . '_' . $nota->disciplina_id;
            });

        $pautaGeral = [];

        foreach ($turma->alunos as $aluno) {
            $ehDesistente = $this->ehDesistentePauta($aluno, $turmaId, $anoLectivo, null);

            $alunoData = [
                'aluno' => $aluno,
                'numero_ordem' => $aluno->numero_ordem,
                'nome' => $aluno->nome,
                'disciplinas' => [],
                'media_geral' => null,
                'situacao_geral' => $ehDesistente ? 'desistente' : null,
                'desistente' => $ehDesistente,
            ];

            $notasFinais = [];

            foreach ($disciplinas as $disciplina) {
                $chave = $aluno->id . '_' . $disciplina->id;
                $notaFinal = $todasNotas->get($chave)?->first();
                $valor = $notaFinal?->valor;

                $alunoData['disciplinas'][$disciplina->id] = [
                    'disciplina' => $disciplina,
                    'nota_final' => $valor,
                    'situacao' => $this->determinarSituacao($valor),
                ];

                if ($valor !== null) {
                    $notasFinais[] = $valor;
                }
            }

            // Calcular média geral só para quem NÃO é desistente
            if (!$ehDesistente && count($notasFinais) === $disciplinas->count() && count($notasFinais) > 0) {
                $alunoData['media_geral'] = round(array_sum($notasFinais) / count($notasFinais), 2);
                $alunoData['situacao_geral'] = $this->determinarSituacaoFinal($alunoData['disciplinas']);
            }

            $pautaGeral[] = $alunoData;
        }

        return [
            'turma' => $turma,
            'disciplinas' => $disciplinas,
            'ano_lectivo' => $anoLectivo,
            'alunos' => $pautaGeral,
        ];
    }

    /**
     * Gera a pauta final completa detalhada (com todas as notas de todos os trimestres)
     * Formato: Para cada disciplina mostra MAC, PP, PT, MT dos 3 trimestres
     */
    public function gerarPautaFinalCompletaDetalhada(
        int $turmaId,
        ?string $anoLectivo = null
    ): array {
        $anoLectivo = $anoLectivo ?? $this->getAnoLectivoActivo();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);
        $anoLectivoIdInt = $anoLectivoId ? (int) $anoLectivoId : null;

        $turma = $this->carregarTurmaComAlunosParaPauta($turmaId, $anoLectivoIdInt);

        $regime = $turma->curso?->regime();
        $classeNumero = (int) preg_replace('/\D/', '', $turma->classe?->codigo ?? $turma->classe?->nome ?? '');
        $tipoCa = match ($classeNumero) {
            10 => Nota::TIPO_CA10,
            11 => Nota::TIPO_CA11,
            12 => Nota::TIPO_CA12,
            default => null,
        };
        $usaPtT3 = $regime?->usaProvaTrimestral(3) ?? true;

        $disciplinas = Disciplina::whereHas('cursos', function ($query) use ($turma) {
                $query->where('cursos.id', $turma->curso_id);
            })
            ->whereHas('classes', function ($query) use ($turma) {
                $query->where('classes.id', $turma->classe_id);
            })
            ->where('activo', true)
            ->ordenadaPorTipo()
            ->get();

        $notasQuery = Nota::query()
            ->whereIn('aluno_id', $turma->alunos->pluck('id'))
            ->where('turma_id', $turmaId)
            ->where('ano_lectivo_id', $anoLectivoId);
        NotaLancamentoRules::aplicarVisibilidadeArquivo($notasQuery, $anoLectivoIdInt, true);

        $notasPorAluno = $notasQuery->get()->groupBy('aluno_id');

        $pautaCompleta = [];

        foreach ($turma->alunos as $aluno) {
            $ehDesistente = $this->ehDesistentePauta($aluno, $turmaId, $anoLectivo, null);

            $alunoData = [
                'aluno' => $aluno,
                'numero_ordem' => $aluno->numero_ordem,
                'numero_processo' => $aluno->numero_processo,
                'nome' => $aluno->nome,
                'genero' => $aluno->genero,
                'disciplinas' => [],
                'observacoes' => $ehDesistente ? 'DESISTENTE' : '',
                'resultado' => $ehDesistente ? 'DESISTENTE' : '',
                'desistente' => $ehDesistente,
            ];

            $todasNotas = $notasPorAluno
                ->get($aluno->id, collect())
                ->groupBy('disciplina_id');

            $negativas = 0;
            $comNotaFinal = 0;

            foreach ($disciplinas as $disciplina) {
                $notasDisciplina = $todasNotas->get($disciplina->id, collect());

                $dadosDisciplina = [
                    'disciplina' => $disciplina,
                    'trimestres' => [],
                    'finais' => [],
                ];

                for ($trimestre = 1; $trimestre <= 3; $trimestre++) {
                    $notasTrimestre = $notasDisciplina->where('trimestre', $trimestre)->keyBy('tipo');
                    $mtRaw = $notasTrimestre->get(Nota::TIPO_MT)?->valor;
                    $rec = $notasTrimestre->get(Nota::TIPO_RECUPERACAO)?->valor;

                    $dadosDisciplina['trimestres'][$trimestre] = [
                        'mac' => $notasTrimestre->get(Nota::TIPO_MAC)?->valor,
                        'pp' => $notasTrimestre->get(Nota::TIPO_PP)?->valor,
                        'pt' => $trimestre === 3 && ! $usaPtT3
                            ? null
                            : $notasTrimestre->get(Nota::TIPO_PT)?->valor,
                        'mt' => \App\Services\RecuperacaoService::mtEfectiva(
                            $mtRaw !== null ? (float) $mtRaw : null,
                            $rec !== null ? (float) $rec : null,
                        ),
                    ];
                }

                $notaFinal = function (string $tipo) use ($notasDisciplina): ?float {
                    $nota = $notasDisciplina->first(fn ($n) => $n->tipo === $tipo && (int) $n->trimestre === 3)
                        ?? $notasDisciplina->first(fn ($n) => $n->tipo === $tipo && $n->trimestre === null);

                    return $nota !== null ? (float) $nota->valor : null;
                };

                $recuperacaoAnual = $notasDisciplina
                    ->where('tipo', Nota::TIPO_RECUPERACAO)
                    ->whereNull('trimestre')
                    ->first();

                $finais = [
                    'mft2' => $notaFinal(Nota::TIPO_MFT2),
                    'cf' => $notaFinal(Nota::TIPO_CF),
                    'pg' => $notaFinal(Nota::TIPO_PG),
                    'ca' => $tipoCa ? $notaFinal($tipoCa) : null,
                    'cfd' => $notaFinal(Nota::TIPO_CFD),
                    'recuperacao' => $recuperacaoAnual?->valor,
                    'recurso' => $notaFinal(Nota::TIPO_RECURSO),
                    'melhoria' => $notaFinal(Nota::TIPO_MELHORIA),
                    'extraordinario' => $notaFinal(Nota::TIPO_EXTRAORDINARIO),
                ];

                if ($finais['cfd'] !== null) {
                    $finais['cfd'] = \App\Support\PautaCalculos::cfdEfectivaDisciplina($finais, (float) $finais['cfd']);
                } elseif ($finais['ca'] !== null) {
                    $finais['ca'] = \App\Support\PautaCalculos::cfdEfectivaDisciplina($finais, (float) $finais['ca']);
                }

                $dadosDisciplina['finais'] = $finais;
                $notaFinal = $finais['cfd'] ?? $finais['ca'];
                $dadosDisciplina['nota_final'] = $notaFinal;
                $dadosDisciplina['situacao'] = $this->determinarSituacao($notaFinal);

                if ($notaFinal !== null) {
                    $comNotaFinal++;
                    if ((float) $notaFinal < 10) {
                        $negativas++;
                    }
                }

                $alunoData['disciplinas'][$disciplina->id] = $dadosDisciplina;
            }

            if (! $ehDesistente && $comNotaFinal > 0) {
                if ($comNotaFinal === $disciplinas->count()) {
                    $alunoData['resultado'] = $negativas <= 2 ? 'Aprovado' : 'Reprovado';
                } else {
                    $alunoData['resultado'] = 'Pendente';
                }
            }

            $pautaCompleta[] = $alunoData;
        }

        $colsT3 = $usaPtT3 ? 4 : 3;
        $colsPorDisciplina = 4 + 4 + $colsT3 + 5;

        return [
            'turma' => $turma,
            'disciplinas' => $disciplinas,
            'ano_lectivo' => $anoLectivo,
            'alunos' => $pautaCompleta,
            'classe_numero' => $classeNumero,
            'usa_pt_t3' => $usaPtT3,
            'cols_por_disciplina' => $colsPorDisciplina,
        ];
    }

    /**
     * Conta as faltas (J+I) de um aluno numa disciplina num trimestre.
     */
    protected function contarFaltasTrimestre(
        int $alunoId,
        int $disciplinaId,
        int $trimestre,
        string $anoLectivo
    ): int {
        $anoId = AnoLectivo::getIdByNome($anoLectivo);
        $contagem = app(PresencaService::class)->contarFaltasAlunoDisciplina(
            $alunoId,
            $disciplinaId,
            $trimestre,
            $anoId
        );

        return $contagem['j'] + $contagem['i'];
    }

    /**
     * @return array{j: int, i: int}
     */
    protected function contarFaltasJITrimestre(
        int $alunoId,
        int $disciplinaId,
        int $trimestre,
        ?int $anoLectivoId = null,
    ): array {
        return app(PresencaService::class)->contarFaltasAlunoDisciplina(
            $alunoId,
            $disciplinaId,
            $trimestre,
            $anoLectivoId
        );
    }

    /**
     * Gera a pauta trimestral completa (1 trimestre, TODAS disciplinas).
     *
     * Estrutura semelhante à pauta oficial em Excel: por cada aluno, devolve
     * para cada disciplina as colunas FALTAS (J/I), MAC, PP, PT e MT, e ainda
     * o resultado APTO/NÃO APTO baseado na regra: NÃO APTO se mais de 2
     * disciplinas tiverem MT < 10 (estritamente >2 negativas).
     *
     * @return array{
     *   turma: Turma,
     *   disciplinas: \Illuminate\Database\Eloquent\Collection<int, Disciplina>,
     *   trimestre: int,
     *   ano_lectivo: string,
     *   alunos: array<int, array<string, mixed>>
     * }
     */
    public function gerarPautaTrimestralCompleta(
        int $turmaId,
        int $trimestre,
        ?string $anoLectivo = null,
        ?string $momentoConselho = null,
    ): array {
        $anoLectivo = $anoLectivo ?? $this->getAnoLectivoActivo();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);
        $anoLectivoIdInt = $anoLectivoId ? (int) $anoLectivoId : null;
        $momentoConselho = \App\Support\PautaMomentoConselho::normalizar($momentoConselho);
        $ignorarMtPersistida = \App\Support\PautaMomentoConselho::isPre($momentoConselho);

        $turma = $this->carregarTurmaComAlunosParaPauta($turmaId, $anoLectivoIdInt);

        $disciplinas = Disciplina::whereHas('cursos', function ($query) use ($turma) {
                $query->where('cursos.id', $turma->curso_id);
            })
            ->whereHas('classes', function ($query) use ($turma) {
                $query->where('classes.id', $turma->classe_id);
            })
            ->where('activo', true)
            ->ordenadaPorTipo()
            ->get();

        // Número da classe (10, 11, 12) — usado para variar layout no T3
        $classeNumero = (int) preg_replace('/\D/', '', $turma->classe?->codigo ?? $turma->classe?->nome ?? '');

        // Regime de cálculo do curso (politécnico ou administração)
        $regime = $this->regimeDaTurma($turma);

        $alunosIds = $turma->alunos->pluck('id')->all();
        $disciplinasIds = $disciplinas->pluck('id')->all();

        // ===== Carregar TODAS as notas necessárias num único query =====
        // Tipos definidos pelo regime (otimização: evita N+1)
        $tiposNecessarios = $regime->tiposNotaUsados($trimestre, $classeNumero);

        $todasNotasQuery = Nota::whereIn('aluno_id', $alunosIds)
            ->whereIn('disciplina_id', $disciplinasIds)
            ->where('turma_id', $turmaId)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereIn('tipo', $tiposNecessarios);
        NotaLancamentoRules::aplicarVisibilidadeArquivo($todasNotasQuery, $anoLectivoIdInt, true);

        $todasNotas = $todasNotasQuery->get();

        // Indexar: [aluno_id][disciplina_id][trimestre][tipo] = valor
        // Respeita momento da pauta (pré-conselho vs pós-conselho).
        $notasIdx = $this->construirNotasIdxPauta($todasNotas, $momentoConselho, $ignorarMtPersistida, $trimestre);

        // Helper: obter nota indexada (T3 ou trimestre null como fallback).
        $getNota = function (int $alunoId, int $discId, int $tri, string $tipo) use ($notasIdx, $regime): ?float {
            $valor = $notasIdx[$alunoId][$discId][$tri][$tipo]
                ?? $notasIdx[$alunoId][$discId][null][$tipo]
                ?? null;

            if ($tipo === Nota::TIPO_PG && $valor === null && ! $regime->usaProvaTrimestral(3)) {
                $valor = $notasIdx[$alunoId][$discId][3][Nota::TIPO_PT] ?? null;
            }

            return $valor;
        };

        // ===== T3 11ª/12ª: carregar CA dos anos lectivos anteriores =====
        // Apenas para regimes que precisam de carry-overs (politécnico). Em
        // administração os trimestres/anos são independentes — não há colunas CA-Nª.
        $caHistorico = []; // [aluno_id][disciplina_id]['ca10' => x, 'ca11' => y]
        if ($trimestre === 3 && $classeNumero >= 11 && $regime->carregaCAsAnteriores()) {
            $tiposCaAnt = $classeNumero === 11
                ? [Nota::TIPO_CA10]
                : [Nota::TIPO_CA10, Nota::TIPO_CA11];

            $notasAnt = Nota::whereIn('aluno_id', $alunosIds)
                ->whereIn('disciplina_id', $disciplinasIds)
                ->whereIn('tipo', $tiposCaAnt)
                ->where('ano_lectivo_id', '!=', $anoLectivoId)
                ->orderBy('ano_lectivo_id', 'desc')
                ->get();

            foreach ($notasAnt as $n) {
                $key = strtolower($n->tipo); // 'ca10', 'ca11'
                if (!isset($caHistorico[$n->aluno_id][$n->disciplina_id][$key])) {
                    $caHistorico[$n->aluno_id][$n->disciplina_id][$key] = (float) $n->valor;
                }
            }

            $classesCa = $classeNumero === 11 ? [10] : [10, 11];
            self::preencherCasComCfdFallback(
                $caHistorico,
                $alunosIds,
                $disciplinasIds,
                $classeNumero,
                $anoLectivoId,
                $classesCa
            );
        }

        // ===== T3 11ª/12ª: identificar DISCIPLINAS TERMINAIS de classes anteriores
        // do curso da turma e carregar CFDs dos alunos (carry-over único por aluno) =====
        // Plano B: fonte = curso_disciplina.classe_fim (filtrado pelo curso da turma).
        $disciplinasTerminais = collect(); // [['disciplina_id', 'nome', 'classe_numero']]
        $cfdTerminaisHistorico = [];       // [aluno_id][disciplina_id] = valor da CFD
        if ($trimestre === 3 && $classeNumero >= 11) {
            $cursoIdTurma = $turma->curso_id;

            $disciplinasTerminais = PautaService::disciplinasTerminaisAnteriores($cursoIdTurma, $classeNumero)
                ->map(fn ($r) => [
                    'disciplina_id' => $r->disciplina_id,
                    'nome' => $r->nome,
                    'codigo' => $r->codigo,
                    'classe_numero' => (int) $r->classe_numero,
                    'classe_inicio' => (int) ($r->classe_inicio ?? $r->classe_numero),
                ])
                ->values();

            if ($disciplinasTerminais->isNotEmpty()) {
                // Carregar CFDs do ano anterior dessas disciplinas terminais
                $cfdsAnt = Nota::whereIn('aluno_id', $alunosIds)
                    ->whereIn('disciplina_id', $disciplinasTerminais->pluck('disciplina_id'))
                    ->where('tipo', Nota::TIPO_CFD)
                    ->where('ano_lectivo_id', '!=', $anoLectivoId)
                    ->orderBy('ano_lectivo_id', 'desc')
                    ->get();

                foreach ($cfdsAnt as $n) {
                    if (!isset($cfdTerminaisHistorico[$n->aluno_id][$n->disciplina_id])) {
                        $cfdTerminaisHistorico[$n->aluno_id][$n->disciplina_id] = (float) $n->valor;
                    }
                }

                // Também carregar CFDs lançadas manualmente no ANO ACTUAL (para escolas sem histórico)
                // — armazenadas na tabela `notas` com tipo CFD, ano actual, disciplina terminal anterior
                $cfdsManuais = Nota::whereIn('aluno_id', $alunosIds)
                    ->whereIn('disciplina_id', $disciplinasTerminais->pluck('disciplina_id'))
                    ->where('tipo', Nota::TIPO_CFD)
                    ->where('ano_lectivo_id', $anoLectivoId)
                    ->get();

                foreach ($cfdsManuais as $n) {
                    // Manual sobrepõe histórico (utilizador pode corrigir)
                    $cfdTerminaisHistorico[$n->aluno_id][$n->disciplina_id] = (float) $n->valor;
                }

                // Fallback solo-classe: CA-N = CFD quando CFD em falta
                $cursoClasseMax = (int) (DB::table('cursos')->where('id', $cursoIdTurma)->value('classe_max') ?? 12);

                foreach ($disciplinasTerminais as $dt) {
                    $classeTerm = (int) $dt['classe_numero'];
                    $anosAtras = $classeNumero - $classeTerm;
                    if ($anosAtras <= 0) {
                        continue;
                    }

                    $inicio = (int) ($dt['classe_inicio'] ?? $classeTerm);
                    $fim = $classeTerm;

                    if (! self::cfdIgualCaDoAno($inicio, $fim, $classeTerm, $cursoClasseMax)) {
                        continue;
                    }

                    $anoTermId = self::anoLectivoIdAnosAtras($anoLectivoId, $anosAtras);
                    if (! $anoTermId) {
                        continue;
                    }

                    $casTerm = Nota::whereIn('aluno_id', $alunosIds)
                        ->where('disciplina_id', $dt['disciplina_id'])
                        ->where('tipo', self::tipoCaParaClasse($classeTerm))
                        ->where('ano_lectivo_id', $anoTermId)
                        ->get();

                    foreach ($casTerm as $n) {
                        // CA-N tem precedência sobre CFD (TIC 10.ª: CA10 = CFD)
                        $cfdTerminaisHistorico[$n->aluno_id][$n->disciplina_id] = (float) $n->valor;
                    }
                }

                // Multi-ano (ex.: LP 10.ª–11.ª): CFD = média das CAs lançadas
                self::preencherCfdTerminaisFromCas(
                    $cfdTerminaisHistorico,
                    $disciplinasTerminais,
                    $alunosIds,
                    $classeNumero,
                    $anoLectivoId,
                );
            }
        }

        // Pré-cálculo: para cada aluno, contar quantas disciplinas têm
        // pelo menos uma nota (MAC/PP/PT) lançada no trimestre. Usado para
        // detectar auto-desistentes (>3 disciplinas sem qualquer nota).
        $disciplinasComNotaPorAluno = [];
        foreach ($notasIdx as $alunoId => $porDisciplina) {
            foreach ($porDisciplina as $disciplinaId => $porTrimestre) {
                $tem = false;
                $trimestresParaVerificar = $trimestre === 3 ? [1, 2, 3] : [$trimestre];
                foreach ($trimestresParaVerificar as $tri) {
                    foreach ([Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT] as $tp) {
                        if (isset($porTrimestre[$tri][$tp])) {
                            $tem = true;
                            break 2;
                        }
                    }
                }
                if ($tem) {
                    $disciplinasComNotaPorAluno[$alunoId][$disciplinaId] = true;
                }
            }
        }
        $totalDisciplinasCount = $disciplinas->count();

        $cursoClasseMax = (int) ($turma->curso->classe_max ?? 12);
        $pivotPorDisciplina = DB::table('curso_disciplina')
            ->where('curso_id', $turma->curso_id)
            ->whereIn('disciplina_id', $disciplinasIds)
            ->get()
            ->keyBy('disciplina_id');

        $usaRegrasTransicaoT3 = $trimestre === 3
            && in_array($classeNumero, [10, 11, 12], true);

        $transicaoService = $usaRegrasTransicaoT3 ? app(RecuperacaoTransicaoService::class) : null;
        $blindadasTransicao = $usaRegrasTransicaoT3
            ? $transicaoService->blindadasParaCurso($turma->curso_id)
            : [];

        $disciplinasTerminaisAtualIds = [];
        if ($trimestre === 3 && $classeNumero < $cursoClasseMax) {
            $disciplinasTerminaisAtualIds = DB::table('curso_disciplina')
                ->where('curso_id', $turma->curso_id)
                ->where('classe_fim', $classeNumero)
                ->whereIn('disciplina_id', $disciplinasIds)
                ->pluck('disciplina_id')
                ->all();
        }
        $temTerminalClasseAtual = ! empty($disciplinasTerminaisAtualIds);

        $disciplinasComRecursoIds = [];
        $disciplinasComRecuperacaoIds = [];
        if ($trimestre === 3) {
            foreach ($todasNotas as $n) {
                if ($n->tipo === Nota::TIPO_RECURSO && (int) $n->trimestre === 3) {
                    $disciplinasComRecursoIds[(int) $n->disciplina_id] = true;
                }
                if ($n->tipo === Nota::TIPO_RECUPERACAO && ($n->trimestre === null || (int) $n->trimestre === 3)) {
                    $disciplinasComRecuperacaoIds[(int) $n->disciplina_id] = true;
                }
            }
        }
        $disciplinasComRecursoIds = array_keys($disciplinasComRecursoIds);
        $disciplinasComRecuperacaoIds = array_keys($disciplinasComRecuperacaoIds);
        $temRecurso = ! empty($disciplinasComRecursoIds);
        $temRecuperacao = ! empty($disciplinasComRecuperacaoIds);

        $colunasPorDisciplina = [];
        foreach ($disciplinas as $disc) {
            $pivot = $pivotPorDisciplina[$disc->id] ?? null;
            $colunasPorDisciplina[$disc->id] = $regime->colunasDisciplina(
                $trimestre,
                $classeNumero,
                self::flagsColunasDisciplina(
                    $disc,
                    $pivot,
                    $classeNumero,
                    $cursoClasseMax,
                    in_array($disc->id, $disciplinasTerminaisAtualIds, true),
                    in_array($disc->id, $disciplinasComRecursoIds, true),
                    $temRecurso,
                    in_array($disc->id, $disciplinasComRecuperacaoIds, true),
                    $temRecuperacao,
                )
            );
        }

        $metaDisciplinasTransicao = [];
        $metaTransicao = [];
        $disciplinasComProfessorIds = [];
        if ($usaRegrasTransicaoT3) {
            $disciplinasComProfessorIds = \App\Models\ProfessorDisciplinaTurma::query()
                ->where('turma_id', $turma->id)
                ->where('activo', true)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->pluck('disciplina_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();

            foreach ($disciplinas as $disc) {
                $pivot = $pivotPorDisciplina[$disc->id] ?? null;
                if (! $pivot) {
                    continue;
                }
                $fim = (int) $pivot->classe_fim;
                $rotulo = mb_strtoupper(trim(($disc->codigo ?? '').' '.$disc->nome));
                $metaDisciplinasTransicao[$disc->id] = [
                    'disciplina_id' => $disc->id,
                    'nome' => $disc->nome,
                    'codigo' => $disc->codigo ?: mb_strtoupper(mb_substr($disc->nome, 0, 5)),
                    'classe_fim' => $fim,
                    'blindada' => (bool) $pivot->blindada,
                    'terminal' => $fim === $classeNumero,
                    'continua' => $fim > $classeNumero,
                    'usa_cfd' => $fim === $classeNumero && in_array('cfd', array_column($colunasPorDisciplina[$disc->id] ?? [], 'key'), true),
                    'eh_lp' => (bool) preg_match('/\b(LP|PORT|PORTUGU)/iu', $rotulo),
                    'eh_mat' => (bool) preg_match('/\b(MAT|MATEMA)/iu', $rotulo),
                    'sem_professor' => ! in_array((int) $disc->id, $disciplinasComProfessorIds, true),
                ];
            }
            $metaTransicao = $metaDisciplinasTransicao;
        }

        $alunosData = [];
        $mapaFaltas = app(PresencaService::class)->mapaFaltasTurma(
            $turmaId,
            $trimestre,
            $anoLectivoId
        );

        foreach ($turma->alunos as $aluno) {
            $disciplinasAluno = [];
            $negativas = 0;
            $totalComNota = 0;
            $semNotaFinal = 0;
            $ehDesistenteManual = $aluno->situacao_academica === Aluno::SITUACAO_DESISTENTE;

            foreach ($disciplinas as $disciplina) {
                $faltasJI = $mapaFaltas[$aluno->id][$disciplina->id] ?? ['j' => 0, 'i' => 0];
                $base = [
                    'mac' => $notasIdx[$aluno->id][$disciplina->id][$trimestre][Nota::TIPO_MAC] ?? null,
                    'pp'  => $notasIdx[$aluno->id][$disciplina->id][$trimestre][Nota::TIPO_PP] ?? null,
                    'pt'  => $notasIdx[$aluno->id][$disciplina->id][$trimestre][Nota::TIPO_PT] ?? null,
                    'mt'  => $this->mtEfectivaFromIdx($notasIdx, $aluno->id, $disciplina->id, $trimestre, $regime, $ignorarMtPersistida),
                    'recuperacao' => $notasIdx[$aluno->id][$disciplina->id][$trimestre][Nota::TIPO_RECUPERACAO] ?? null,
                    'faltas_j' => $faltasJI['j'],
                    'faltas_i' => $faltasJI['i'],
                    'faltas' => 0,
                ];
                $base['faltas'] = $base['faltas_j'] + $base['faltas_i'];

                if ($trimestre >= 2) {
                    $base['mt1'] = $this->mtEfectivaFromIdx($notasIdx, $aluno->id, $disciplina->id, 1, $regime);
                    $base['mft2'] = $notasIdx[$aluno->id][$disciplina->id][null][Nota::TIPO_MFT2]
                        ?? $notasIdx[$aluno->id][$disciplina->id][$trimestre][Nota::TIPO_MFT2]
                        ?? null;
                    if ($base['mft2'] === null && $regime->precisaMFT2()) {
                        $mt1 = $base['mt1'];
                        $mt2 = $base['mt'];
                        if ($mt1 !== null && $mt2 !== null) {
                            $base['mft2'] = round(($mt1 + $mt2) / 2);
                        }
                    }
                }

                // T3: trazer MFT2 (do T2) + PG + CF + CA + CFD
                if ($trimestre === 3) {
                    // MFT2 vem do trimestre 2 (tipo MFT2). Fallback: MT do trim 2.
                    // Só aplicável a regimes que usam MFT2 (politécnico).
                    if ($regime->precisaMFT2()) {
                        $base['mft2'] = $notasIdx[$aluno->id][$disciplina->id][null][Nota::TIPO_MFT2]
                            ?? $notasIdx[$aluno->id][$disciplina->id][2][Nota::TIPO_MFT2]
                            ?? null;
                        if ($base['mft2'] === null) {
                            $mt1 = $this->mtEfectivaFromIdx($notasIdx, $aluno->id, $disciplina->id, 1, $regime);
                            $mt2 = $this->mtEfectivaFromIdx($notasIdx, $aluno->id, $disciplina->id, 2, $regime);
                            if ($mt1 !== null && $mt2 !== null) {
                                $base['mft2'] = round(($mt1 + $mt2) / 2);
                            }
                        }
                    } else {
                        $base['mft2'] = null;
                    }

                    $base['pg'] = $getNota($aluno->id, $disciplina->id, 3, Nota::TIPO_PG);
                    $base['cf'] = $getNota($aluno->id, $disciplina->id, 3, Nota::TIPO_CF);
                    $base['cfd'] = $getNota($aluno->id, $disciplina->id, 3, Nota::TIPO_CFD);
                    // RECURSO / MELHORIA / EXTRAORDINÁRIO (entrada manual, T3 ou trimestre null)
                    $base['recurso'] = $getNota($aluno->id, $disciplina->id, 3, Nota::TIPO_RECURSO);
                    $base['melhoria'] = $getNota($aluno->id, $disciplina->id, 3, Nota::TIPO_MELHORIA);
                    $base['extraordinario'] = $getNota($aluno->id, $disciplina->id, 3, Nota::TIPO_EXTRAORDINARIO);
                    // Recuperação anual (trimestre null); fallback a notas legadas no T3
                    $base['recuperacao'] = $notasIdx[$aluno->id][$disciplina->id][null][Nota::TIPO_RECUPERACAO]
                        ?? $notasIdx[$aluno->id][$disciplina->id][3][Nota::TIPO_RECUPERACAO]
                        ?? null;

                    // CA do ano lectivo actual (lançada manualmente ou via fórmula)
                    if ($classeNumero === 10) {
                        $base['ca'] = $getNota($aluno->id, $disciplina->id, 3, Nota::TIPO_CA10);
                    } elseif ($classeNumero === 11) {
                        $base['ca'] = $getNota($aluno->id, $disciplina->id, 3, Nota::TIPO_CA11);
                        $base['ca10'] = $caHistorico[$aluno->id][$disciplina->id]['ca10'] ?? null;
                    } elseif ($classeNumero === 12) {
                        $base['ca'] = $getNota($aluno->id, $disciplina->id, 3, Nota::TIPO_CA12);
                        $base['ca10'] = $caHistorico[$aluno->id][$disciplina->id]['ca10'] ?? null;
                        $base['ca11'] = $caHistorico[$aluno->id][$disciplina->id]['ca11'] ?? null;
                    }

                    // Cálculo lazy de CF/CA quando ainda não foram persistidos —
                    // útil sobretudo para administração, onde a pauta final agrega
                    // as MTs dos 3 trimestres directamente.
                    if ($base['cf'] === null) {
                        $mtEfectiva = function (int $tri) use ($notasIdx, $aluno, $disciplina, $regime): ?float {
                            $mt = $this->mtEfectivaFromIdx(
                                $notasIdx,
                                $aluno->id,
                                $disciplina->id,
                                $tri,
                                $regime
                            );
                            $rec = $notasIdx[$aluno->id][$disciplina->id][$tri][Nota::TIPO_RECUPERACAO] ?? null;

                            return \App\Services\RecuperacaoService::mtEfectiva(
                                $mt,
                                $rec !== null ? (float) $rec : null,
                            );
                        };

                        $cfCalc = $regime->calcularCF(
                            $mtEfectiva(1),
                            $mtEfectiva(2),
                            $mtEfectiva(3),
                            $base['mft2'] !== null ? (float) $base['mft2'] : null
                        );
                        if ($cfCalc !== null) {
                            $base['cf'] = $cfCalc;
                        }
                    }

                    // Recalcular CA a partir de CF+PG (CF já arredondado), como no Excel
                    if ($base['cf'] !== null && $base['pg'] !== null && $base['pg'] !== '') {
                        $base['ca'] = \App\Support\PautaCalculos::caFromCfPg($base['cf'], $base['pg']);
                    } elseif ($base['ca'] === null && $base['cf'] !== null) {
                        $base['ca'] = \App\Support\PautaCalculos::caFromCfPg($base['cf'], $base['pg']);
                    }

                    // Preferir CFD recalculada (como no Excel); fallback à CFD da BD
                    $pivotDisc = $pivotPorDisciplina[$disciplina->id] ?? null;
                    $cfdFlags = self::flagsColunasDisciplina(
                        $disciplina,
                        $pivotDisc,
                        $classeNumero,
                        $cursoClasseMax,
                        self::ehTerminalNaClasse($turma->curso_id, $disciplina->id, $classeNumero),
                        false,
                        false,
                    );
                    $cfdCalc = $regime->calcularCfdPreview($base, $classeNumero, $cfdFlags);
                    if ($cfdCalc !== null) {
                        $base['cfd'] = $cfdCalc;
                    }
                }

                $colsDiscRes = $colunasPorDisciplina[$disciplina->id] ?? [];
                $pivotDisc = $pivotPorDisciplina[$disciplina->id] ?? null;
                $classeFimDisc = (int) ($pivotDisc->classe_fim ?? $classeNumero);
                $ehTerminalDisc = $classeFimDisc === $classeNumero;
                $base['tem_coluna_cfd'] = in_array('cfd', array_column($colsDiscRes, 'key'), true);
                $base['eh_terminal'] = $ehTerminalDisc;
                $base['eh_continua'] = $classeFimDisc > $classeNumero;

                if ($trimestre === 3) {
                    if ($ehTerminalDisc && $base['tem_coluna_cfd']) {
                        $cfdOriginal = $base['cfd'] ?? null;
                        $temCfd = $cfdOriginal !== null && $cfdOriginal !== '';
                        $notaEfectiva = \App\Support\PautaCalculos::notaResultadoDisciplina($base, true);

                        if ($notaEfectiva !== null) {
                            if ($temCfd) {
                                $base['cfd_original'] = (float) $cfdOriginal;
                                $base['cfd'] = $notaEfectiva;
                                $detalhe = \App\Support\PautaCalculos::detalheSubstituicaoExame(
                                    $base,
                                    (float) $cfdOriginal,
                                    (float) $notaEfectiva,
                                );
                                if ($detalhe !== null) {
                                    $base['substituicao_exame'] = $detalhe;
                                }
                            } else {
                                // CFD vazia (ex.: CA < 7) mas há RECUP./RECURSO — preencher CFD efectiva
                                $caBase = \App\Support\PautaCalculos::caParaResultado($base['ca'] ?? null);
                                $detalhe = \App\Support\PautaCalculos::detalheSubstituicaoExame(
                                    $base,
                                    (float) ($caBase ?? $notaEfectiva),
                                    (float) $notaEfectiva,
                                );
                                if ($detalhe !== null) {
                                    if ($caBase !== null) {
                                        $base['cfd_original'] = $caBase;
                                    }
                                    $base['cfd'] = $notaEfectiva;
                                    $base['substituicao_exame'] = $detalhe;
                                }
                            }
                        }
                    } else {
                        $ca = \App\Support\PautaCalculos::caParaResultado($base['ca'] ?? null);
                        $notaEfectiva = $ca !== null
                            ? \App\Support\PautaCalculos::cfdEfectivaDisciplina($base, $ca)
                            : \App\Support\PautaCalculos::cfdEfectivaDisciplina($base);

                        if ($notaEfectiva !== null) {
                            $original = $ca ?? $notaEfectiva;
                            if ($ca !== null) {
                                $base['ca_original'] = $ca;
                                $base['ca'] = $notaEfectiva;
                            }
                            $detalhe = \App\Support\PautaCalculos::detalheSubstituicaoExame(
                                $base,
                                (float) $original,
                                (float) $notaEfectiva,
                            );
                            if ($detalhe !== null) {
                                if ($ca === null) {
                                    // Exame seco sem CA — reflectir na coluna de resultado (CA)
                                    $base['ca'] = $notaEfectiva;
                                }
                                $base['substituicao_exame'] = $detalhe;
                            }
                        }
                    }
                }

                $disciplinasAluno[$disciplina->id] = $base;

                if ($trimestre === 3 && ! \App\Support\PautaCalculos::disciplinaComNotaFinal($base)) {
                    $semNotaFinal++;
                }

                // Delegar ao regime a decisão de qual valor usar para Apto/Reprovado.
                //  Politécnico: T1/T2=MT; T3 10/11=CA; T3 12=CFD+substituições.
                //  Administração: T1/T2=MT; T3=CA+substituições (sem CFD por ano).
                $valorParaResultado = $regime->valorParaResultado($base, $trimestre, $classeNumero);

                if ($valorParaResultado !== null) {
                    $totalComNota++;
                    if ((float) $valorParaResultado < 10) {
                        $negativas++;
                    }
                }
            }

            if ($trimestre === 3) {
                $ehDesistente = $ehDesistenteManual
                    || $semNotaFinal > self::MAX_DISCIPLINAS_SEM_NOTA_FINAL_DESISTENTE;
            } else {
                $ehDesistente = $ehDesistenteManual
                    || $this->ehDesistentePautaComIndice(
                        $aluno,
                        $disciplinasComNotaPorAluno,
                        $totalDisciplinasCount
                    );
            }

            // Regra oficial: mais de 2 disciplinas negativas → NÃO APTO
            // T3 10.ª/11.ª politécnico: terminais vs contínuas + blindadas (LP/MAT)
            $cfdTerminaisAluno = [];
            foreach ($disciplinasTerminais as $dt) {
                $cfdTerminaisAluno[$dt['disciplina_id']] = $cfdTerminaisHistorico[$aluno->id][$dt['disciplina_id']] ?? null;
            }

            $analiseTransicao = null;
            if ($ehDesistente) {
                $resultado = 'DESISTENTE';
            } elseif ($usaRegrasTransicaoT3) {
                $analiseTransicao = $transicaoService->analisarAluno(
                    [
                        'aluno' => $aluno,
                        'numero_ordem' => $aluno->numero_ordem,
                        'nome' => $aluno->nome,
                        'disciplinas' => $disciplinasAluno,
                        'cfd_terminais' => $cfdTerminaisAluno,
                        'terminais_anteriores' => $disciplinasTerminais->all(),
                    ],
                    $turma->curso_id,
                    $classeNumero,
                    $metaTransicao,
                    $blindadasTransicao,
                    $cursoClasseMax,
                );
                $resultado = $analiseTransicao['resultado'];
            } elseif ($trimestre === 3) {
                $resultado = '';
            } else {
                $resultado = $totalComNota > 0
                    ? ($negativas > 2 ? 'NÃO APTO' : 'APTO')
                    : '';
            }

            // CFDs das disciplinas terminais anteriores (carry-over por aluno)
            $cfdTerminais = $cfdTerminaisAluno;

            // Coluna MÉDIA do aluno (regime administração no T3) — média
            // aritmética das CAs do ano nas disciplinas com CA disponível.
            $mediaAluno = null;
            if ($regime->temColunaMediaAluno($trimestre)) {
                $cas = collect($disciplinasAluno)
                    ->pluck('ca')
                    ->filter(fn ($v) => $v !== null && $v !== '');
                if ($cas->isNotEmpty()) {
                    $mediaAluno = round($cas->avg(), 1);
                }
            }

            $alunosData[] = [
                'aluno' => $aluno,
                'numero_ordem' => $aluno->numero_ordem,
                'numero_processo' => $aluno->numero_processo,
                'nome' => $aluno->nome,
                'genero' => $aluno->genero,
                'educacao_especial' => (bool) $aluno->educacao_especial,
                'necessidades_especificas' => $aluno->necessidades_especificas,
                'disciplinas' => $disciplinasAluno,
                'cfd_terminais' => $cfdTerminais,
                'observacao' => $ehDesistente
                    ? 'DESISTENTE'
                    : ($analiseTransicao['observacao'] ?? ''),
                'resultado' => $resultado,
                'negativas' => $negativas,
                'desistente' => $ehDesistente,
                'media_aluno' => $mediaAluno,
            ];
        }

        return [
            'turma' => $turma,
            'disciplinas' => $disciplinas,
            'disciplinas_terminais_anteriores' => $disciplinasTerminais->all(),
            'disciplinas_terminais_classe_atual_ids' => $disciplinasTerminaisAtualIds,
            'disciplinas_com_recurso_ids' => $disciplinasComRecursoIds,
            'disciplinas_com_recuperacao_ids' => $disciplinasComRecuperacaoIds,
            'trimestre' => $trimestre,
            'classe_numero' => $classeNumero,
            'tem_recurso' => $temRecurso,
            'tem_recuperacao' => $temRecuperacao,
            'tem_terminal_classe_atual' => $temTerminalClasseAtual,
            'colunas_por_disciplina' => $colunasPorDisciplina,
            'meta_disciplinas_transicao' => $metaDisciplinasTransicao,
            // Retro-compat: esquema com mais colunas (para fallbacks na view).
            'colunas_disc' => collect($colunasPorDisciplina)->sortByDesc(fn ($c) => count($c))->first()
                ?? $regime->colunasDisciplina($trimestre, $classeNumero, [
                    'recurso' => $temRecurso,
                    'terminal_classe_atual' => $temTerminalClasseAtual,
                ]),
            'tem_coluna_media_aluno' => $regime->temColunaMediaAluno($trimestre),
            'regime' => $regime->id(),
            'ano_lectivo' => $anoLectivo,
            'momento_conselho' => $momentoConselho,
            'momento_conselho_label' => \App\Support\PautaMomentoConselho::rotulo($momentoConselho),
            'alunos' => $alunosData,
        ];
    }

    /**
     * @deprecated Usar Curso->regime()->colunasDisciplina(...). Este método
     *   serve apenas como fallback (regime politécnico) para retro-compatibilidade
     *   com chamadores externos.
     */
    public static function esquemaColunasDisciplina(
        int $trimestre,
        int $classeNumero,
        bool $comRecurso = false,
        bool $comTerminalClasseAtual = false
    ): array {
        if ($trimestre === 1) {
            return [
                ['key' => 'faltas_j', 'header' => 'J', 'group' => 'FALTAS', 'tipo' => 'int'],
                ['key' => 'faltas_i', 'header' => 'I', 'group' => 'FALTAS', 'tipo' => 'int'],
                ['key' => 'mac', 'header' => 'MAC1', 'tipo' => 'nota'],
                ['key' => 'pp', 'header' => 'PP1', 'tipo' => 'nota'],
                ['key' => 'pt', 'header' => 'PT1', 'tipo' => 'nota'],
                ['key' => 'mt', 'header' => 'MT1', 'tipo' => 'formula', 'destaque' => true],
            ];
        }

        if ($trimestre === 2) {
            return [
                ['key' => 'mt1', 'header' => 'MT1', 'tipo' => 'nota'],
                ['key' => 'mac', 'header' => 'MAC2', 'tipo' => 'nota'],
                ['key' => 'pp', 'header' => 'PP2', 'tipo' => 'nota'],
                ['key' => 'pt', 'header' => 'PT2', 'tipo' => 'nota'],
                ['key' => 'mt', 'header' => 'MT2', 'tipo' => 'formula'],
                ['key' => 'mft2', 'header' => 'MFT2', 'tipo' => 'formula', 'destaque' => true],
            ];
        }

        if ($trimestre === 3) {
            if ($classeNumero <= 10) {
                $cols10 = [
                    ['key' => 'mft2', 'header' => 'MFT2', 'tipo' => 'nota'],
                    ['key' => 'mac', 'header' => 'MAC3', 'tipo' => 'nota'],
                    ['key' => 'pp', 'header' => 'PP3', 'tipo' => 'nota'],
                    ['key' => 'mt', 'header' => 'MT3', 'tipo' => 'formula', 'fundo_pale' => true],
                    ['key' => 'cf', 'header' => 'CF', 'tipo' => 'formula', 'fundo_pale' => true],
                    ['key' => 'pg', 'header' => 'PG', 'tipo' => 'nota'],
                    ['key' => 'ca', 'header' => 'CA-10ª', 'tipo' => 'formula', 'destaque' => true, 'fundo_pale' => true],
                ];
                if ($comTerminalClasseAtual) {
                    $cols10[] = ['key' => 'cfd', 'header' => 'CFD', 'tipo' => 'formula', 'fundo_cfd' => true];
                }
                return $cols10;
            }
            if ($classeNumero === 11) {
                $cols11 = [
                    ['key' => 'ca10', 'header' => 'CA-10ª', 'tipo' => 'nota'],
                    ['key' => 'mft2', 'header' => 'MFT2', 'tipo' => 'nota'],
                    ['key' => 'mac', 'header' => 'MAC3', 'tipo' => 'nota'],
                    ['key' => 'pp', 'header' => 'PP3', 'tipo' => 'nota'],
                    ['key' => 'mt', 'header' => 'MT3', 'tipo' => 'formula', 'fundo_pale' => true],
                    ['key' => 'cf', 'header' => 'CF', 'tipo' => 'formula', 'fundo_pale' => true],
                    ['key' => 'pg', 'header' => 'PG', 'tipo' => 'nota'],
                    ['key' => 'ca', 'header' => 'CA-11ª', 'tipo' => 'formula', 'destaque' => true, 'fundo_pale' => true],
                ];
                if ($comTerminalClasseAtual) {
                    $cols11[] = ['key' => 'cfd', 'header' => 'CFD', 'tipo' => 'formula', 'fundo_cfd' => true];
                }

                return $cols11;
            }
            // 12ª
            $cols12 = [
                ['key' => 'ca10', 'header' => 'CA-10ª', 'tipo' => 'nota'],
                ['key' => 'ca11', 'header' => 'CA-11ª', 'tipo' => 'nota'],
                ['key' => 'mft2', 'header' => 'MFT2', 'tipo' => 'nota'],
                ['key' => 'mac', 'header' => 'MAC3', 'tipo' => 'nota'],
                ['key' => 'pp', 'header' => 'PP3', 'tipo' => 'nota'],
                ['key' => 'mt', 'header' => 'MT3', 'tipo' => 'formula', 'fundo_pale' => true],
                ['key' => 'cf', 'header' => 'CF', 'tipo' => 'formula', 'fundo_pale' => true],
                ['key' => 'pg', 'header' => 'PG', 'tipo' => 'nota'],
                ['key' => 'ca', 'header' => 'CA-12ª', 'tipo' => 'formula', 'fundo_pale' => true],
            ];
            // RECURSO — informativo (não entra no cálculo). Aparece apenas se houver dados.
            if ($comRecurso) {
                $cols12[] = ['key' => 'recurso', 'header' => 'RECURSO', 'tipo' => 'nota'];
            }
            return $cols12;
        }

        // fallback
        return self::esquemaColunasDisciplina(1, $classeNumero);
    }

    /**
     * Calcula todas as médias trimestrais de uma turma/disciplina/trimestre
     */
    public function calcularMediasTrimestraisTurma(
        int $turmaId,
        int $disciplinaId,
        int $trimestre,
        int $professorId,
        ?string $anoLectivo = null,
        bool $recalcularFinais = false
    ): array {
        $anoLectivo = $anoLectivo ?? $this->getAnoLectivoActivo();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);

        $turma = Turma::with(['alunos' => function ($query) {
            $query->where('activo', true);
        }])->findOrFail($turmaId);

        $resultado = [
            'calculadas' => 0,
            'pendentes' => 0,
            'criadas' => 0,
            'atualizadas' => 0,
            'detalhes' => [],
            'pendentes_detalhes' => [],
        ];

        foreach ($turma->alunos as $aluno) {
            // Verificar se MT já existe ANTES de calcular
            $mtExistente = Nota::where('aluno_id', $aluno->id)
                ->where('disciplina_id', $disciplinaId)
                ->where('turma_id', $turmaId)
                ->where('trimestre', $trimestre)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('tipo', Nota::TIPO_MT)
                ->exists();

            $mt = $this->calcularEGuardarMediaTrimestral(
                $aluno->id,
                $disciplinaId,
                $turmaId,
                $trimestre,
                $professorId,
                $anoLectivo
            );

            if ($mt) {
                $resultado['calculadas']++;

                if ($mtExistente) {
                    $resultado['atualizadas']++;
                } else {
                    $resultado['criadas']++;
                }

                $resultado['detalhes'][$aluno->id] = $mt->valor;
            } else {
                $resultado['pendentes']++;
                $resultado['detalhes'][$aluno->id] = null;

                // Identificar quais notas faltam
                $notasTrimestre = Nota::where('aluno_id', $aluno->id)
                    ->where('disciplina_id', $disciplinaId)
                    ->where('turma_id', $turmaId)
                    ->where('trimestre', $trimestre)
                    ->where('ano_lectivo_id', $anoLectivoId)
                    ->whereIn('tipo', [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT])
                    ->pluck('tipo')
                    ->toArray();

                $faltantes = array_diff([Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT], $notasTrimestre);

                $resultado['pendentes_detalhes'][] = [
                    'aluno_nome' => $aluno->nome,
                    'notas_faltantes' => $faltantes,
                ];
            }
        }

        // Se solicitado, recalcular notas finais automaticamente
        if ($recalcularFinais && $resultado['calculadas'] > 0) {
            $resultadoFinais = $this->calcularNotasFinaisTurma(
                $turmaId,
                $disciplinaId,
                $professorId,
                $anoLectivo
            );

            $resultado['finais_recalculadas'] = $resultadoFinais['calculadas'];
        }

        return $resultado;
    }

    /**
     * Calcula todas as notas finais de uma turma/disciplina
     */
    public function calcularNotasFinaisTurma(
        int $turmaId,
        int $disciplinaId,
        int $professorId,
        ?string $anoLectivo = null
    ): array {
        $anoLectivo = $anoLectivo ?? $this->getAnoLectivoActivo();

        $turma = Turma::with(['alunos' => function ($query) {
            $query->where('activo', true);
        }])->findOrFail($turmaId);

        $resultado = [
            'calculadas' => 0,
            'pendentes' => 0,
            'detalhes' => [],
        ];

        foreach ($turma->alunos as $aluno) {
            $notaFinal = $this->calcularEGuardarNotaFinal(
                $aluno->id,
                $disciplinaId,
                $turmaId,
                $professorId,
                $anoLectivo
            );

            if ($notaFinal) {
                $resultado['calculadas']++;
                $resultado['detalhes'][$aluno->id] = $notaFinal->valor;
            } else {
                $resultado['pendentes']++;
                $resultado['detalhes'][$aluno->id] = null;
            }
        }

        return $resultado;
    }

    /**
     * Obtém estatísticas de uma mini-pauta
     */
    public function getEstatisticasMiniPauta(Collection $miniPauta): array
    {
        // Desistentes não entram no cálculo de aprovados/reprovados/média
        $desistentes = $miniPauta->where('situacao', 'desistente');
        $avaliados = $miniPauta->where('situacao', '!=', 'desistente');
        $mediasAvaliados = $avaliados
            ->map(fn ($linha) => $linha['valor_resultado'] ?? $linha['mt'] ?? null)
            ->filter(fn ($v) => $v !== null);

        return [
            'total_alunos' => $miniPauta->count(),
            'desistentes' => $desistentes->count(),
            'avaliados' => $avaliados->count(),
            'com_nota' => $mediasAvaliados->count(),
            'sem_nota' => $avaliados->count() - $mediasAvaliados->count(),
            'aprovados' => $mediasAvaliados->filter(fn($v) => $v >= 10)->count(),
            'reprovados' => $mediasAvaliados->filter(fn($v) => $v < 10)->count(),
            'media_turma' => $mediasAvaliados->isNotEmpty() ? round($mediasAvaliados->avg(), 2) : null,
            'nota_maxima' => $mediasAvaliados->max(),
            'nota_minima' => $mediasAvaliados->min(),
        ];
    }

    /**
     * Obtém estatísticas de uma pauta final
     */
    public function getEstatisticasPautaFinal(Collection $pautaFinal): array
    {
        $desistentes = $pautaFinal->where('desistente', true);
        $avaliados = $pautaFinal->where('desistente', '!=', true);
        $notasFinais = $avaliados->pluck('nota_final')->filter();

        return [
            'total_alunos' => $pautaFinal->count(),
            'desistentes' => $desistentes->count(),
            'avaliados' => $avaliados->count(),
            'com_nota' => $notasFinais->count(),
            'sem_nota' => $avaliados->count() - $notasFinais->count(),
            'aprovados' => $notasFinais->filter(fn($v) => $v >= 10)->count(),
            'reprovados' => $notasFinais->filter(fn($v) => $v < 10)->count(),
            'media_turma' => $notasFinais->isNotEmpty() ? round($notasFinais->avg(), 2) : null,
            'nota_maxima' => $notasFinais->max(),
            'nota_minima' => $notasFinais->min(),
        ];
    }

    /**
     * Determina a situação de uma nota
     */
    public function determinarSituacao(?float $nota): string
    {
        if ($nota === null) {
            return 'pendente';
        }

        $notaMinima = $this->getNotaMinimaAprovacao();

        return $nota >= $notaMinima ? 'aprovado' : 'reprovado';
    }

    /**
     * Determina a situação final do aluno (considerando todas as disciplinas)
     */
    public function determinarSituacaoFinal(array $disciplinas): string
    {
        $reprovacoes = 0;

        foreach ($disciplinas as $disc) {
            if ($disc['nota_final'] === null) {
                return 'pendente';
            }
            if ($disc['situacao'] === 'reprovado') {
                $reprovacoes++;
            }
        }

        // Por defeito: reprova se tiver mais de 2 disciplinas com negativa
        $maxReprovacoes = (int) $this->getConfiguracao('max_disciplinas_reprovacao', 2);

        return $reprovacoes <= $maxReprovacoes ? 'aprovado' : 'reprovado';
    }

    /**
     * Obtém o ano lectivo activo no formato 2025-2026
     */
    protected function getAnoLectivoActivo(): string
    {
        return Configuracao::getAnoLectivoActual();
    }

    /**
     * Obtém a nota mínima de aprovação
     */
    protected function getNotaMinimaAprovacao(): float
    {
        return (float) $this->getConfiguracao('nota_minima_aprovacao', 10);
    }

    /**
     * Obtém uma configuração do sistema
     */
    protected function getConfiguracao(string $chave, $valorPadrao = null)
    {
        $config = Configuracao::where('chave', $chave)->first();

        return $config ? $config->valor : $valorPadrao;
    }

    // ========================================================================
    // NOVOS MÉTODOS - REGRAS OFICIAIS DE PAUTAS (10ª-12ª Classes)
    // ========================================================================

    /**
     * Calcular e guardar MFT2 (Média Final dos 2 Trimestres)
     * Fórmula: MFT2 = (MT1 + MT2) / 2
     */
    public function calcularEGuardarMFT2(
        int $alunoId,
        int $disciplinaId,
        int $turmaId,
        int $professorId,
        ?string $anoLectivo = null
    ): ?Nota {
        // Regimes que não usam MFT2 (administração) silenciosamente ignoram.
        if (! $this->regimeDaTurmaId($turmaId)->precisaMFT2()) {
            return null;
        }

        $anoLectivo = $anoLectivo ?? $this->getAnoLectivoActivo();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);

        // Buscar MT1 e MT2
        $mts = Nota::where('aluno_id', $alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('tipo', Nota::TIPO_MT)
            ->whereIn('trimestre', [1, 2])
            ->pluck('valor', 'trimestre');

        if ($mts->count() < 2) {
            return null; // Faltam MTs
        }

        // MFT2 = (MT1 + MT2) / 2
        $mft2 = ($mts[1] + $mts[2]) / 2;

        // Criar ou atualizar MFT2
        return Nota::updateOrCreate(
            [
                'aluno_id' => $alunoId,
                'disciplina_id' => $disciplinaId,
                'turma_id' => $turmaId,
                'ano_lectivo_id' => $anoLectivoId,
                'tipo' => Nota::TIPO_MFT2,
                'trimestre' => null,
            ],
            [
                'valor' => round($mft2),
                'professor_id' => $professorId,
                'ano_lectivo' => $anoLectivo,
                'observacoes' => 'Calculada automaticamente',
            ]
        );
    }

    /**
     * Calcular e guardar CF (Classificação Final).
     *
     * Fórmula varia por regime:
     *  - Politécnico:    CF = (MFT2 + MT3) / 2
     *  - Administração:  CF = (MT1 + MT2 + MT3) / 3
     */
    public function calcularEGuardarCF(
        int $alunoId,
        int $disciplinaId,
        int $turmaId,
        int $professorId,
        ?string $anoLectivo = null
    ): ?Nota {
        $anoLectivo = $anoLectivo ?? $this->getAnoLectivoActivo();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);

        $regime = $this->regimeDaTurmaId($turmaId);

        // Carregar todas as componentes potencialmente necessárias
        $mts = Nota::where('aluno_id', $alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('tipo', Nota::TIPO_MT)
            ->pluck('valor', 'trimestre');

        $mft2 = Nota::where('aluno_id', $alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('tipo', Nota::TIPO_MFT2)
            ->value('valor');

        $cf = $regime->calcularCF(
            isset($mts[1]) ? (float) $mts[1] : null,
            isset($mts[2]) ? (float) $mts[2] : null,
            isset($mts[3]) ? (float) $mts[3] : null,
            $mft2 !== null ? (float) $mft2 : null
        );

        if ($cf === null) {
            return null;
        }

        return Nota::updateOrCreate(
            [
                'aluno_id' => $alunoId,
                'disciplina_id' => $disciplinaId,
                'turma_id' => $turmaId,
                'ano_lectivo_id' => $anoLectivoId,
                'tipo' => Nota::TIPO_CF,
                'trimestre' => null,
            ],
            [
                'valor' => NotaArredondamento::media($cf),
                'professor_id' => $professorId,
                'ano_lectivo' => $anoLectivo,
                'observacoes' => 'Calculada automaticamente',
            ]
        );
    }

    /**
     * Calcular e guardar CA (Classificação Anual)
     * Fórmula: CA = 0.6*CF + 0.4*PG
     */
    public function calcularEGuardarCA(
        int $alunoId,
        int $disciplinaId,
        int $turmaId,
        int $classe,
        int $professorId,
        ?string $anoLectivo = null
    ): ?Nota {
        $anoLectivo = $anoLectivo ?? $this->getAnoLectivoActivo();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);
        $fallbackPt3ComoPg = ! $this->regimeDaTurmaId($turmaId)->usaProvaTrimestral(3);

        // Buscar CF
        $cf = Nota::where('aluno_id', $alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('tipo', Nota::TIPO_CF)
            ->value('valor');

        // Buscar PG (com fallback PT3 no regime politécnico)
        $pg = Nota::valorProvaGlobal(
            $alunoId,
            $disciplinaId,
            $turmaId,
            $anoLectivoId,
            $fallbackPt3ComoPg,
        );

        if ($cf === null || $cf === '' || $pg === null || $pg === '') {
            return null;
        }

        // Excel: CA = ROUND(0.6*ROUND(CF)+0.4*PG, 0) — CF já deve estar à unidade
        $cf = NotaArredondamento::media((float) $cf);
        $ca = NotaArredondamento::media((0.6 * (float) $cf) + (0.4 * (float) $pg));

        $tipoCa = self::tipoCaParaClasse($classe);

        $notaCa = Nota::updateOrCreate(
            [
                'aluno_id' => $alunoId,
                'disciplina_id' => $disciplinaId,
                'turma_id' => $turmaId,
                'ano_lectivo_id' => $anoLectivoId,
                'tipo' => $tipoCa,
                'trimestre' => null,
            ],
            [
                'valor' => $ca,
                'professor_id' => $professorId,
                'ano_lectivo' => $anoLectivo,
                'observacoes' => 'Calculada automaticamente',
            ]
        );

        $turma = Turma::find($turmaId);
        $pivot = $turma ? DB::table('curso_disciplina')
            ->where('curso_id', $turma->curso_id)
            ->where('disciplina_id', $disciplinaId)
            ->first() : null;

        if ($pivot) {
            $classeMax = (int) (DB::table('cursos')->where('id', $turma->curso_id)->value('classe_max') ?? 12);
            if (self::cfdIgualCaDoAno((int) $pivot->classe_inicio, (int) $pivot->classe_fim, $classe, $classeMax)) {
                Nota::updateOrCreate(
                    [
                        'aluno_id' => $alunoId,
                        'disciplina_id' => $disciplinaId,
                        'turma_id' => $turmaId,
                        'ano_lectivo_id' => $anoLectivoId,
                        'tipo' => Nota::TIPO_CFD,
                        'trimestre' => null,
                    ],
                    [
                        'valor' => $ca,
                        'professor_id' => $professorId,
                        'ano_lectivo' => $anoLectivo,
                        'observacoes' => 'CFD = CA (disciplina terminal)',
                    ]
                );
            }
        }

        return $notaCa;
    }

    /**
     * Calcular e guardar CFD (Classificação Final da Disciplina)
     * Fórmula varia conforme classe_inicio e classe_fim da disciplina NO CURSO
     */
    public function calcularEGuardarCFD(
        int $alunoId,
        int $disciplinaId,
        int $turmaId,
        int $professorId,
        ?string $anoLectivo = null
    ): ?Nota {
        $anoLectivo = $anoLectivo ?? $this->getAnoLectivoActivo();
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);

        $turma = Turma::with('classe')->findOrFail($turmaId);
        $classeActual = (int) preg_replace('/\D/', '', $turma->classe->codigo ?? (string) $turma->classe->nome);
        $cursoId = $turma->curso_id;

        $cursoDisciplina = DB::table('curso_disciplina')
            ->where('curso_id', $cursoId)
            ->where('disciplina_id', $disciplinaId)
            ->first();

        if (! $cursoDisciplina) {
            throw new \Exception('Disciplina não associada a este curso.');
        }

        $classeInicio = (int) $cursoDisciplina->classe_inicio;
        $classeFim = (int) $cursoDisciplina->classe_fim;
        $classeMax = (int) (DB::table('cursos')->where('id', $cursoId)->value('classe_max') ?? 12);

        if ($classeActual !== $classeFim) {
            return null;
        }

        $tipoCa = self::tipoCaParaClasse($classeActual);

        if (self::cfdIgualCaDoAno($classeInicio, $classeFim, $classeActual, $classeMax)) {
            $ca = Nota::where('aluno_id', $alunoId)
                ->where('disciplina_id', $disciplinaId)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('tipo', $tipoCa)
                ->value('valor');

            if (! $ca) {
                return null;
            }

            $cfd = (float) $ca;
        } elseif ($classeActual === 11 && $classeInicio <= 10 && $classeFim === 11) {
            $anoAnterior = $this->getAnoLectivoAnterior($anoLectivo);

            $ca10 = Nota::where('aluno_id', $alunoId)
                ->where('disciplina_id', $disciplinaId)
                ->where('ano_lectivo_id', AnoLectivo::getIdByNome($anoAnterior))
                ->where('tipo', Nota::TIPO_CA10)
                ->value('valor');

            $ca11 = Nota::where('aluno_id', $alunoId)
                ->where('disciplina_id', $disciplinaId)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('tipo', Nota::TIPO_CA11)
                ->value('valor');

            if (! $ca10 || ! $ca11) {
                return null;
            }

            if (! \App\Support\PautaRegrasTransicaoMinisterio::podeCalcularCfdPorMedia((float) $ca11)) {
                return null;
            }

            $cfd = ($ca10 + $ca11) / 2;
        } elseif ($classeActual === 12 && $classeInicio === 10) {
            $anoAnterior = $this->getAnoLectivoAnterior($anoLectivo);
            $ano2Anterior = $this->getAnoLectivoAnterior($anoAnterior);

            $ca10 = Nota::where('aluno_id', $alunoId)
                ->where('disciplina_id', $disciplinaId)
                ->where('ano_lectivo_id', AnoLectivo::getIdByNome($ano2Anterior))
                ->where('tipo', Nota::TIPO_CA10)
                ->value('valor');

            $ca11 = Nota::where('aluno_id', $alunoId)
                ->where('disciplina_id', $disciplinaId)
                ->where('ano_lectivo_id', AnoLectivo::getIdByNome($anoAnterior))
                ->where('tipo', Nota::TIPO_CA11)
                ->value('valor');

            $ca12 = Nota::where('aluno_id', $alunoId)
                ->where('disciplina_id', $disciplinaId)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('tipo', Nota::TIPO_CA12)
                ->value('valor');

            if (! $ca10 || ! $ca11 || ! $ca12) {
                return null;
            }

            if (! \App\Support\PautaRegrasTransicaoMinisterio::podeCalcularCfdPorMedia((float) $ca12)) {
                return null;
            }

            $cfd = ($ca10 + $ca11 + $ca12) / 3;
        } elseif ($classeActual === 12 && $classeInicio === 11) {
            $anoAnterior = $this->getAnoLectivoAnterior($anoLectivo);

            $ca11 = Nota::where('aluno_id', $alunoId)
                ->where('disciplina_id', $disciplinaId)
                ->where('ano_lectivo_id', AnoLectivo::getIdByNome($anoAnterior))
                ->where('tipo', Nota::TIPO_CA11)
                ->value('valor');

            $ca12 = Nota::where('aluno_id', $alunoId)
                ->where('disciplina_id', $disciplinaId)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('tipo', Nota::TIPO_CA12)
                ->value('valor');

            if (! $ca11 || ! $ca12) {
                return null;
            }

            if (! \App\Support\PautaRegrasTransicaoMinisterio::podeCalcularCfdPorMedia((float) $ca12)) {
                return null;
            }

            $cfd = ($ca11 + $ca12) / 2;
        } else {
            return null;
        }

        return Nota::updateOrCreate(
            [
                'aluno_id' => $alunoId,
                'disciplina_id' => $disciplinaId,
                'turma_id' => $turmaId,
                'ano_lectivo_id' => $anoLectivoId,
                'tipo' => Nota::TIPO_CFD,
                'trimestre' => null,
            ],
            [
                'valor' => round($cfd),
                'professor_id' => $professorId,
                'ano_lectivo' => $anoLectivo,
                'observacoes' => 'Calculada automaticamente',
            ]
        );
    }

    /**
     * Helper para calcular ano lectivo anterior
     * Ex: "2025-2026" -> "2024-2025"
     */
    private function getAnoLectivoAnterior(string $anoLectivo): string
    {
        [$ano1, $ano2] = explode('-', $anoLectivo);
        return ($ano1 - 1) . '-' . ($ano2 - 1);
    }

    /**
     * Determina situação final do aluno baseado em todas as suas notas
     *
     * @param Aluno $aluno
     * @param string $anoLectivo
     * @return string|null 'aprovado', 'reprovado' ou null (pendente)
     */
    public function determinarSituacaoAluno(\App\Models\Aluno $aluno, string $anoLectivo): ?string
    {
        $notasFinais = $aluno->notas()
            ->where('ano_lectivo', $anoLectivo)
            ->whereIn('tipo', [Nota::TIPO_CFD, Nota::TIPO_FINAL])
            ->get();

        if ($notasFinais->isEmpty()) {
            return null; // Sem notas finais = pendente
        }

        $reprovacoes = 0;
        foreach ($notasFinais as $nota) {
            if ($nota->valor < $this->getNotaMinimaAprovacao()) {
                $reprovacoes++;
            }
        }

        $maxReprovacoes = (int) $this->getConfiguracao('max_disciplinas_reprovacao', 2);

        return $reprovacoes <= $maxReprovacoes ? 'aprovado' : 'reprovado';
    }

    // ========================================================================
    // DETECÇÃO DE DESISTENTES
    // ========================================================================

    /**
     * T3: mais de N disciplinas sem CF/CA/CFD → desistente na pauta.
     */
    public const MAX_DISCIPLINAS_SEM_NOTA_FINAL_DESISTENTE = 4;

    /**
     * Limite de disciplinas sem qualquer avaliação (MAC/PP/PT) acima do qual o
     * aluno passa a ser considerado auto-desistente na pauta (T1/T2).
     */
    public function getMaxDisciplinasSemNotaParaDesistencia(): int
    {
        return (int) $this->getConfiguracao('max_disciplinas_sem_nota_desistente', 3);
    }

    /**
     * Verifica se o aluno deve ser considerado desistente NA MINI-PAUTA
     * (visão por trimestre/disciplina). Regra: nenhuma das 3 provas (MAC, PP,
     * PT) foi lançada nesse trimestre e o aluno não foi marcado manualmente
     * como aprovado/reprovado/transferido.
     */
    public function ehDesistenteMiniPauta(
        ?Aluno $aluno,
        $mac,
        $pp,
        $pt,
        ?int $turmaId = null,
        ?int $trimestre = null,
    ): bool {
        if ($aluno && $aluno->situacao_academica === Aluno::SITUACAO_DESISTENTE) {
            return true;
        }

        if ($turmaId !== null && $trimestre !== null) {
            $componentes = $this->regimeDaTurmaId($turmaId)->componentesMediaTrimestral($trimestre);
            $valores = [
                Nota::TIPO_MAC => $mac,
                Nota::TIPO_PP => $pp,
                Nota::TIPO_PT => $pt,
            ];

            foreach ($componentes as $tipo) {
                if ($valores[$tipo] !== null) {
                    return false;
                }
            }

            return true;
        }

        return $mac === null && $pp === null && $pt === null;
    }

    /**
     * Verifica se o aluno deve ser considerado desistente NA PAUTA
     * (visão por turma + várias disciplinas). Regra: foi marcado manualmente
     * como desistente OU não tem qualquer nota lançada (MAC/PP/PT) em mais do
     * que N disciplinas (default 3).
     *
     * @param int|null $trimestre  Se fornecido, verifica apenas esse trimestre.
     *                             Se null, verifica em todo o ano lectivo.
     */
    public function ehDesistentePauta(
        Aluno $aluno,
        int $turmaId,
        ?string $anoLectivo = null,
        ?int $trimestre = null
    ): bool {
        if ($aluno->situacao_academica === Aluno::SITUACAO_DESISTENTE) {
            return true;
        }

        $anoLectivo = $anoLectivo ?? $this->getAnoLectivoActivo();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);

        // Buscar a turma para descobrir curso/classe e respectivas disciplinas
        $turma = Turma::with('classe')->find($turmaId);
        if (!$turma || !$turma->curso_id || !$turma->classe_id) {
            return false;
        }

        $disciplinasIds = Disciplina::whereHas('cursos', fn($q) => $q->where('cursos.id', $turma->curso_id))
            ->whereHas('classes', fn($q) => $q->where('classes.id', $turma->classe_id))
            ->where('activo', true)
            ->pluck('id');

        if ($disciplinasIds->isEmpty()) {
            return false;
        }

        $query = Nota::where('aluno_id', $aluno->id)
            ->where('turma_id', $turmaId)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereIn('disciplina_id', $disciplinasIds)
            ->whereIn('tipo', [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT]);

        if ($trimestre !== null) {
            $query->where('trimestre', $trimestre);
        }

        $disciplinasComNota = $query->distinct('disciplina_id')->count('disciplina_id');
        $totalDisciplinas = $disciplinasIds->count();
        $semNenhumaNota = $totalDisciplinas - $disciplinasComNota;

        return $semNenhumaNota > $this->getMaxDisciplinasSemNotaParaDesistencia();
    }

    /**
     * Versão optimizada: recebe um índice pré-calculado de notas
     * [aluno_id => [disciplina_id => true]] e o total de disciplinas.
     * Útil em loops de geração de pauta para evitar N+1.
     */
    public function ehDesistentePautaComIndice(
        Aluno $aluno,
        array $disciplinasComNotaPorAluno,
        int $totalDisciplinas
    ): bool {
        if ($aluno->situacao_academica === Aluno::SITUACAO_DESISTENTE) {
            return true;
        }

        $comNota = isset($disciplinasComNotaPorAluno[$aluno->id])
            ? count($disciplinasComNotaPorAluno[$aluno->id])
            : 0;

        $semNenhumaNota = $totalDisciplinas - $comNota;

        return $semNenhumaNota > $this->getMaxDisciplinasSemNotaParaDesistencia();
    }

    /**
     * Devolve os IDs dos alunos auto-desistentes num ano lectivo (e
     * opcionalmente trimestre). Auto-desistente = sem qualquer nota lançada
     * de MAC/PP/PT em mais do que N disciplinas (default 3).
     *
     * @param int[] $alunoIds
     * @return array<int,bool> [aluno_id => true]
     */
    public function getAutoDesistenteIds(array $alunoIds, int $anoLectivoId, ?int $trimestre = null): array
    {
        if (empty($alunoIds)) {
            return [];
        }

        $maxSemNota = $this->getMaxDisciplinasSemNotaParaDesistencia();

        // Mapear cada aluno → curso/classe da sua turma
        $alunosTurmas = DB::table('alunos')
            ->whereIn('alunos.id', $alunoIds)
            ->join('turmas', 'alunos.turma_id', '=', 'turmas.id')
            ->select('alunos.id as aluno_id', 'turmas.curso_id', 'turmas.classe_id')
            ->get();

        if ($alunosTurmas->isEmpty()) {
            return [];
        }

        // Total de disciplinas activas por (curso, classe), com cache
        $totaisDisciplinas = [];
        foreach ($alunosTurmas as $at) {
            $key = $at->curso_id . ':' . $at->classe_id;
            if (!isset($totaisDisciplinas[$key])) {
                $totaisDisciplinas[$key] = DB::table('disciplinas')
                    ->join('curso_disciplina', 'disciplinas.id', '=', 'curso_disciplina.disciplina_id')
                    ->join('classe_disciplina', 'disciplinas.id', '=', 'classe_disciplina.disciplina_id')
                    ->where('curso_disciplina.curso_id', $at->curso_id)
                    ->where('classe_disciplina.classe_id', $at->classe_id)
                    ->where('disciplinas.activo', true)
                    ->distinct('disciplinas.id')
                    ->count('disciplinas.id');
            }
        }

        // Disciplinas distintas com pelo menos uma nota (MAC/PP/PT) por aluno
        $query = DB::table('notas')
            ->whereIn('aluno_id', $alunoIds)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereIn('tipo', [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT]);

        if (! NotaLancamentoRules::anoLectivoEhHistorico($anoLectivoId ? (int) $anoLectivoId : null)) {
            $query->whereNull('archived_at');
        }

        if ($trimestre !== null) {
            $query->where('trimestre', $trimestre);
        }

        $disciplinasComNotaPorAluno = $query
            ->select('aluno_id', 'disciplina_id')
            ->distinct()
            ->get()
            ->groupBy('aluno_id')
            ->map(fn($rows) => $rows->count());

        $autoIds = [];
        foreach ($alunosTurmas as $at) {
            $total = $totaisDisciplinas[$at->curso_id . ':' . $at->classe_id] ?? 0;
            if ($total === 0) {
                continue;
            }
            $com = $disciplinasComNotaPorAluno->get($at->aluno_id, 0);
            if (($total - $com) > $maxSemNota) {
                $autoIds[$at->aluno_id] = true;
            }
        }

        return $autoIds;
    }
}
