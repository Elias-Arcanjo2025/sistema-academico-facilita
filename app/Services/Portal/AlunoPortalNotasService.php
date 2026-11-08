<?php

namespace App\Services\Portal;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Support\BoletimColunas;
use App\Support\BoletimNotasBuilder;
use App\Support\NotaLancamentoRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AlunoPortalNotasService
{
    /** @var array<int, string> */
    protected array $tiposAnuais = [
        Nota::TIPO_MFT2,
        Nota::TIPO_CF,
        Nota::TIPO_PG,
        Nota::TIPO_CA10,
        Nota::TIPO_CA11,
        Nota::TIPO_CA12,
        Nota::TIPO_CFD,
        Nota::TIPO_RECUPERACAO,
        Nota::TIPO_RECURSO,
        Nota::TIPO_MELHORIA,
        Nota::TIPO_EXTRAORDINARIO,
        Nota::TIPO_EQUIVALENCIA,
        Nota::TIPO_MF,
        Nota::TIPO_EXAME,
        Nota::TIPO_FINAL,
    ];

    /**
     * IDs do mesmo processo (registos por ano após transição).
     *
     * @return Collection<int, int>
     */
    public function alunoIdsDoProcesso(Aluno $aluno): Collection
    {
        if (! $aluno->numero_processo) {
            return collect([(int) $aluno->id]);
        }

        return Aluno::query()
            ->where('numero_processo', $aluno->numero_processo)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Registo do aluno no ano pedido (turma desse ano), senão o actual.
     */
    public function alunoNoAno(Aluno $aluno, int $anoLectivoId): Aluno
    {
        if (! $aluno->numero_processo) {
            return $aluno;
        }

        $noAno = Aluno::query()
            ->with(['turma.curso', 'turma.classe'])
            ->where('numero_processo', $aluno->numero_processo)
            ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
            ->orderByDesc('activo')
            ->first();

        return $noAno ?? $aluno;
    }

    /**
     * @return Collection<int, AnoLectivo>
     */
    public function anosLectivosDisponiveis(Aluno $aluno): Collection
    {
        // Inclui arquivadas — anos anteriores ficam consultáveis no portal
        $ids = Nota::query()
            ->whereIn('aluno_id', $this->alunoIdsDoProcesso($aluno))
            ->whereNotNull('ano_lectivo_id')
            ->distinct()
            ->pluck('ano_lectivo_id');

        $actualId = Configuracao::getAnoLectivoActualId();
        if ($actualId) {
            $ids = $ids->push($actualId);
        }

        return AnoLectivo::query()
            ->whereIn('id', $ids->unique()->filter())
            ->orderByDesc('nome')
            ->get();
    }

    /**
     * @return Collection<int, Disciplina>
     */
    public function disciplinasDisponiveis(Aluno $aluno, int $anoLectivoId): Collection
    {
        $query = Nota::query()
            ->whereIn('aluno_id', $this->alunoIdsDoProcesso($aluno))
            ->where('ano_lectivo_id', $anoLectivoId);
        NotaLancamentoRules::aplicarVisibilidadeArquivo($query, $anoLectivoId);

        $ids = $query->distinct()->pluck('disciplina_id');

        return Disciplina::query()
            ->whereIn('id', $ids)
            ->orderBy('nome')
            ->get();
    }

    /**
     * Resumo curto para WhatsApp / mensagens (MT do trimestre actual).
     */
    public function resumoRapido(Aluno $aluno): string
    {
        $anoId = (int) Configuracao::getAnoLectivoActualId();
        $trimestre = (int) (Configuracao::getTrimestreActual() ?? 1);

        $notas = $this->consultar($aluno, [
            'ano_lectivo_id' => $anoId,
            'periodo' => (string) $trimestre,
        ]);

        $mts = $notas
            ->where('tipo', Nota::TIPO_MT)
            ->where('trimestre', $trimestre)
            ->values();

        if ($mts->isEmpty()) {
            $mts = $notas->where('tipo', Nota::TIPO_MT)->values();
        }

        if ($mts->isEmpty()) {
            return "sem MT no {$trimestre}º trim. — consulte o portal";
        }

        $media = number_format((float) $mts->avg('valor'), 1, ',', '');
        $partes = $mts->take(5)->map(function (Nota $n) {
            $disc = Str::limit((string) ($n->disciplina?->nome ?? 'Disc.'), 16, '');

            return $disc.' '.number_format((float) $n->valor, 1, ',', '');
        });

        $extra = $mts->count() > 5 ? '…' : '';

        return "{$trimestre}ºT média {$media} | ".$partes->implode('; ').$extra;
    }

    /**
     * @param  array{ano_lectivo_id?:int, periodo?:string, disciplina_id?:string|int}  $filtros
     * @return Collection<int, Nota>
     */
    public function consultar(Aluno $aluno, array $filtros = []): Collection
    {
        $anoId = (int) ($filtros['ano_lectivo_id'] ?? Configuracao::getAnoLectivoActualId());
        $periodo = (string) ($filtros['periodo'] ?? 'todos');
        $disciplinaId = $filtros['disciplina_id'] ?? 'todas';

        $query = Nota::query()
            ->with('disciplina')
            ->whereIn('aluno_id', $this->alunoIdsDoProcesso($aluno))
            ->where('ano_lectivo_id', $anoId);
        NotaLancamentoRules::aplicarVisibilidadeArquivo($query, $anoId);

        $this->aplicarFiltroPeriodo($query, $periodo);

        if ($disciplinaId !== 'todas' && $disciplinaId !== '' && $disciplinaId !== null) {
            $query->where('disciplina_id', (int) $disciplinaId);
        }

        return $query
            ->orderBy('disciplina_id')
            ->orderBy('trimestre')
            ->orderBy('tipo')
            ->get();
    }

    /**
     * @return array{ano_lectivo_id:int, periodo:string, disciplina_id:string}
     */
    public function normalizarFiltros(array $input): array
    {
        $anoActual = Configuracao::getAnoLectivoActualId();

        return [
            'ano_lectivo_id' => (int) ($input['ano_lectivo_id'] ?? $anoActual),
            'periodo' => (string) ($input['periodo'] ?? (string) config('portal.aluno.periodo_padrao', 'todos')),
            'disciplina_id' => (string) ($input['disciplina_id'] ?? 'todas'),
        ];
    }

    protected function aplicarFiltroPeriodo(Builder $query, string $periodo): void
    {
        if ($periodo === 'todos') {
            return;
        }

        if ($periodo === 'anual') {
            $query->where(function (Builder $q) {
                $q->whereNull('trimestre')
                    ->orWhereIn('tipo', $this->tiposAnuais)
                    ->orWhereIn('tipo', [Nota::TIPO_RECUPERACAO, Nota::TIPO_RECURSO]);
            });

            return;
        }

        if (in_array($periodo, ['1', '2', '3'], true)) {
            $query->where('trimestre', (int) $periodo);
        }
    }

    /**
     * Agrupa notas numa linha por disciplina (MAC, PP, PT, MT em colunas).
     *
     * @return array{
     *     modo: string,
     *     colunas: array<int, string>,
     *     mostrar_trimestre: bool,
     *     linhas: array<int, array{disciplina: string, trimestre: ?int, celulas: array<string, ?float>}>
     * }
     */
    public function agruparParaTabela(Collection $notas, string $periodo, ?Aluno $aluno = null): array
    {
        if ($periodo === 'anual') {
            return $this->agruparTabelaAnual($notas);
        }

        if (in_array($periodo, ['1', '2', '3'], true) && $aluno) {
            $anoId = (int) ($notas->first()?->ano_lectivo_id ?? Configuracao::getAnoLectivoActualId());
            $alunoAno = $this->alunoNoAno($aluno, $anoId);
            if ($alunoAno->turma?->curso) {
                return $this->agruparTabelaBoletim($alunoAno, (int) $periodo, $anoId);
            }
        }

        $colunas = [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT, Nota::TIPO_MT];
        $mostrarTrimestre = $periodo === 'todos';

        $grupos = $mostrarTrimestre
            ? $notas->groupBy(fn (Nota $n) => $n->disciplina_id.'_'.($n->trimestre ?? 0))
            : $notas->groupBy('disciplina_id');

        $linhas = [];

        foreach ($grupos as $grupo) {
            /** @var Nota $primeira */
            $primeira = $grupo->first();
            $porTipo = $grupo->keyBy('tipo');

            $celulas = [];
            foreach ($colunas as $tipo) {
                $nota = $porTipo->get($tipo);
                $celulas[$tipo] = $nota !== null ? (float) $nota->valor : null;
            }

            $linhas[] = [
                'disciplina' => $primeira->disciplina?->nome ?? '—',
                'trimestre' => $mostrarTrimestre ? $primeira->trimestre : null,
                'celulas' => $celulas,
            ];
        }

        usort($linhas, function (array $a, array $b) {
            $cmp = strcasecmp($a['disciplina'], $b['disciplina']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return ($a['trimestre'] ?? 0) <=> ($b['trimestre'] ?? 0);
        });

        return [
            'modo' => 'trimestral',
            'colunas' => $colunas,
            'mostrar_trimestre' => $mostrarTrimestre,
            'linhas' => $linhas,
        ];
    }

    /**
     * @return array{
     *     modo: string,
     *     colunas: array<int, string>,
     *     mostrar_trimestre: bool,
     *     linhas: array<int, array{disciplina: string, trimestre: ?int, celulas: array<string, ?float>}>
     * }
     */
    protected function agruparTabelaAnual(Collection $notas): array
    {
        $grupos = $notas->groupBy('disciplina_id');
        $colunasPresentes = collect();

        foreach ($grupos as $grupo) {
            foreach ($grupo as $nota) {
                $colunasPresentes->push($nota->tipo);
            }
        }

        $ordem = $this->tiposAnuais;
        $colunas = $colunasPresentes->unique()
            ->sortBy(fn (string $t) => array_search($t, $ordem, true) !== false ? array_search($t, $ordem, true) : 99)
            ->values()
            ->all();

        if ($colunas === []) {
            $colunas = [Nota::TIPO_CFD];
        }

        $linhas = [];

        foreach ($grupos as $grupo) {
            $primeira = $grupo->first();
            $porTipo = $grupo->keyBy('tipo');
            $celulas = [];

            foreach ($colunas as $tipo) {
                $nota = $porTipo->get($tipo);
                $celulas[$this->rotuloColunaPortal($tipo)] = $nota !== null ? (float) $nota->valor : null;
            }

            $linhas[] = [
                'disciplina' => $primeira->disciplina?->nome ?? '—',
                'trimestre' => null,
                'celulas' => $celulas,
            ];
        }

        usort($linhas, fn (array $a, array $b) => strcasecmp($a['disciplina'], $b['disciplina']));

        return [
            'modo' => 'anual',
            'colunas' => array_map(fn (string $t) => $this->rotuloColunaPortal($t), $colunas),
            'colunas_chave' => $colunas,
            'mostrar_trimestre' => false,
            'linhas' => $linhas,
        ];
    }

    protected function rotuloColunaPortal(string $tipo): string
    {
        return match ($tipo) {
            Nota::TIPO_RECUPERACAO => 'RECUP.',
            Nota::TIPO_RECURSO => 'RECURSO',
            Nota::TIPO_CA10 => 'CA-10ª',
            Nota::TIPO_CA11 => 'CA-11ª',
            Nota::TIPO_CA12 => 'CA-12ª',
            default => Nota::getTiposActivos()[$tipo] ?? $tipo,
        };
    }

    /**
     * Tabela alinhada ao boletim oficial por trimestre (regime politécnico).
     *
     * @return array{
     *     modo: string,
     *     colunas: array<int, string>,
     *     mostrar_trimestre: bool,
     *     linhas: array<int, array{disciplina: string, trimestre: ?int, celulas: array<string, ?float>}>
     * }
     */
    protected function agruparTabelaBoletim(Aluno $aluno, int $trimestre, ?int $anoLectivoId = null): array
    {
        $turma = $aluno->turma;
        $regime = $turma->curso->regime();
        $classeNumero = (int) ($turma->classe->codigo ?? 10);
        $colunasDef = BoletimColunas::paraTrimestre($trimestre, $regime, $classeNumero);
        $anoLectivoId = (int) ($anoLectivoId ?? Configuracao::getAnoLectivoActualId());
        $anoNome = AnoLectivo::getNomeById($anoLectivoId) ?? Configuracao::getAnoLectivoActual();

        $notasQuery = Nota::query()
            ->where('aluno_id', $aluno->id)
            ->where('turma_id', $turma->id)
            ->where('ano_lectivo_id', $anoLectivoId);
        NotaLancamentoRules::aplicarVisibilidadeArquivo($notasQuery, $anoLectivoId);

        $builder = BoletimNotasBuilder::paraAluno($aluno, $anoNome)->comNotas($notasQuery->get());

        $disciplinas = Disciplina::whereHas('cursos', fn ($q) => $q->where('cursos.id', $turma->curso_id))
            ->whereHas('classes', fn ($q) => $q->where('classes.id', $turma->classe_id))
            ->where('activo', true)
            ->orderBy('nome')
            ->get();

        $linhas = [];

        foreach ($disciplinas as $disciplina) {
            $valores = $builder->linhaTrimestre($disciplina, $trimestre, $regime, $classeNumero);
            $celulas = [];

            foreach ($colunasDef as $coluna) {
                $celulas[$coluna['header']] = $valores[$coluna['key']] ?? null;
            }

            $linhas[] = [
                'disciplina' => $disciplina->nome,
                'trimestre' => null,
                'celulas' => $celulas,
            ];
        }

        return [
            'modo' => 'trimestral',
            'colunas' => array_column($colunasDef, 'header'),
            'mostrar_trimestre' => false,
            'linhas' => $linhas,
        ];
    }
}
