<?php

namespace App\Support;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Policies\NotaPolicy;
use Closure;

class NotaLancamentoRules
{
    /**
     * Ano lectivo diferente do actual (notas tipicamente arquivadas na transição).
     */
    public static function anoLectivoEhHistorico(?int $anoLectivoId): bool
    {
        $anoActualId = Configuracao::getAnoLectivoActualId();

        return $anoLectivoId !== null
            && $anoActualId !== null
            && (int) $anoLectivoId !== (int) $anoActualId;
    }

    /**
     * Turma de ano lectivo diferente do actual (elenco histórico / pós-transição).
     */
    public static function turmaEhAnoHistorico(int $turmaId, ?int $anoLectivoId = null): bool
    {
        $anoTurmaId = $anoLectivoId ?? Turma::whereKey($turmaId)->value('ano_lectivo_id');

        return self::anoLectivoEhHistorico($anoTurmaId !== null ? (int) $anoTurmaId : null);
    }

    /**
     * Ano actual: só notas activas. Ano anterior: inclui arquivadas (fecho de transição).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Nota>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Nota>
     */
    public static function aplicarVisibilidadeArquivo($query, ?int $anoLectivoId, bool $preferirActivas = false)
    {
        if (! self::anoLectivoEhHistorico($anoLectivoId)) {
            return $query->whereNull('archived_at');
        }

        if ($preferirActivas) {
            $query->orderByRaw('archived_at IS NULL DESC');
        }

        return $query;
    }

    /**
     * Alunos da turma para lançar/consultar notas.
     * Ano actual: só activos. Ano anterior: inclui inactivos (registos pós-transição com notas históricas).
     *
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Aluno>
     */
    public static function queryAlunosDaTurmaParaNotas(int $turmaId, ?int $anoLectivoId = null)
    {
        $query = Aluno::query()
            ->where('turma_id', $turmaId)
            ->ordenado();

        if (! self::turmaEhAnoHistorico($turmaId, $anoLectivoId)) {
            $query->activo();
        }

        return $query;
    }

    public static function alunoPertenceTurma(int $alunoId, int $turmaId): bool
    {
        $query = Aluno::where('id', $alunoId)
            ->where('turma_id', $turmaId);

        // Em anos anteriores o elenco histórico fica activo=false após a transição.
        if (! self::turmaEhAnoHistorico($turmaId)) {
            $query->where('activo', true);
        }

        return $query->exists();
    }

    public static function disciplinaPertenceTurma(int $disciplinaId, int $turmaId): bool
    {
        $turma = Turma::find($turmaId);

        if (! $turma?->curso_id || ! $turma->classe_id) {
            return false;
        }

        return Disciplina::where('id', $disciplinaId)
            ->where('activo', true)
            ->whereHas('cursos', fn ($q) => $q->where('cursos.id', $turma->curso_id))
            ->whereHas('classes', fn ($q) => $q->where('classes.id', $turma->classe_id))
            ->exists();
    }

    public static function ruleAlunoNaTurma(int $turmaId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($turmaId): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! self::alunoPertenceTurma((int) $value, $turmaId)) {
                $fail('O aluno seleccionado não pertence à turma indicada ou está inactivo.');
            }
        };
    }

    public static function ruleDisciplinaNaTurma(int $turmaId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($turmaId): void {
            if (! self::disciplinaPertenceTurma((int) $value, $turmaId)) {
                $fail('A disciplina não faz parte do plano curricular desta turma.');
            }
        };
    }

    public static function ruleAlunosLoteNaTurma(int $turmaId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($turmaId): void {
            if (! is_array($value)) {
                return;
            }

            foreach (array_keys($value) as $alunoId) {
                if (! is_numeric($alunoId)) {
                    continue;
                }

                if (! self::alunoPertenceTurma((int) $alunoId, $turmaId)) {
                    $fail('Um ou mais alunos do lote não pertencem à turma indicada.');

                    return;
                }
            }
        };
    }

    public static function utilizadorPodeLancar($user, int $disciplinaId, int $turmaId): bool
    {
        if (! $user->can('notas.lancar')) {
            return false;
        }

        if ($user->temControloAcademicoInstitucional()) {
            return true;
        }

        $anoLectivoId = Configuracao::getAnoLectivoActualId();

        return ProfessorDisciplinaTurma::where('user_id', $user->id)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->exists();
    }

    /**
     * Professor (associação activa), director da turma ou coordenador do curso.
     */
    public static function utilizadorPodeLancarNaTurmaDisciplina($user, int $turmaId, int $disciplinaId): bool
    {
        if ($user->temControloAcademicoInstitucional()) {
            return true;
        }

        if (self::utilizadorPodeLancar($user, $disciplinaId, $turmaId)) {
            return true;
        }

        $anoLectivoId = Configuracao::getAnoLectivoActualId();
        $anoLectivoNome = AnoLectivo::getNomeById($anoLectivoId);

        if ($user->hasRole('director_turma') && $user->can('notas.lancar')) {
            return $user->turmasComoDirector()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo', $anoLectivoNome)
                ->where('turmas.id', $turmaId)
                ->exists();
        }

        if ($user->hasRole('coordenador_curso')) {
            $cursosIds = $user->cursosComoCoordenador()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo_id', $anoLectivoId)
                ->pluck('cursos.id');

            return Turma::where('id', $turmaId)->whereIn('curso_id', $cursosIds)->exists();
        }

        return false;
    }

    public static function utilizadorPodeLancarNaTurma($user, int $turmaId): bool
    {
        if ($user->temControloAcademicoInstitucional()) {
            return true;
        }

        $anoLectivoId = Configuracao::getAnoLectivoActualId();

        if (ProfessorDisciplinaTurma::where('user_id', $user->id)
            ->where('turma_id', $turmaId)
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->exists()) {
            return true;
        }

        $anoLectivoNome = AnoLectivo::getNomeById($anoLectivoId);

        if ($user->hasRole('director_turma') && $user->can('notas.lancar')) {
            return $user->turmasComoDirector()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo', $anoLectivoNome)
                ->where('turmas.id', $turmaId)
                ->exists();
        }

        if ($user->hasRole('coordenador_curso')) {
            $turma = Turma::find($turmaId);
            if (! $turma) {
                return false;
            }

            $cursosIds = $user->cursosComoCoordenador()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo_id', $anoLectivoId)
                ->pluck('cursos.id');

            return $cursosIds->contains($turma->curso_id);
        }

        return false;
    }

    /**
     * Disciplinas que o utilizador pode lançar na turma seleccionada.
     *
     * @return \Illuminate\Support\Collection<int, Disciplina>
     */
    public static function disciplinasLancamentoNaTurma($user, Turma $turma): \Illuminate\Support\Collection
    {
        $query = Disciplina::query()
            ->whereHas('cursos', fn ($q) => $q->where('cursos.id', $turma->curso_id))
            ->whereHas('classes', fn ($q) => $q->where('classes.id', $turma->classe_id))
            ->activo()
            ->orderBy('nome');

        if ($user->temVisaoInstitucional() || $user->hasRole('subdiretor_pedagogico')) {
            return $query->get();
        }

        $anoLectivoId = $turma->ano_lectivo_id ?? Configuracao::getAnoLectivoActualId();
        $anoLectivoNome = AnoLectivo::getNomeById($anoLectivoId);

        $podeTodas = false;

        if ($user->hasRole('director_turma') && $user->can('notas.lancar')) {
            $podeTodas = $user->turmasComoDirector()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo', $anoLectivoNome)
                ->where('turmas.id', $turma->id)
                ->exists();
        } elseif ($user->hasRole('coordenador_curso')) {
            $cursosIds = $user->cursosComoCoordenador()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo_id', $anoLectivoId)
                ->pluck('cursos.id');
            $podeTodas = $cursosIds->contains($turma->curso_id);
        }

        if ($podeTodas) {
            return $query->get();
        }

        $discIds = ProfessorDisciplinaTurma::where('user_id', $user->id)
            ->where('turma_id', $turma->id)
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->pluck('disciplina_id');

        return $query->whereIn('id', $discIds)->get();
    }

    /**
     * Tipos de nota disponíveis no formulário de lançamento, conforme a classe da turma.
     * Melhoria de notas fica no módulo dos coordenadores de curso.
     *
     * @return array<string, string>
     */
    public static function tiposLancamentoDisponiveis(?Turma $turma): array
    {
        $rotulos = Nota::rotulosLancamento();
        $tipos = [
            Nota::TIPO_MAC => $rotulos[Nota::TIPO_MAC],
            Nota::TIPO_PP => $rotulos[Nota::TIPO_PP],
            Nota::TIPO_PT => $rotulos[Nota::TIPO_PT],
            Nota::TIPO_PG => $rotulos[Nota::TIPO_PG],
        ];

        if ($turma) {
            $classe = $turma->numeroClasse();

            if (in_array($classe, [10, 11], true)) {
                $tipos[Nota::TIPO_RECUPERACAO] = $rotulos[Nota::TIPO_RECUPERACAO];
            }

            if ($classe === 12) {
                $tipos[Nota::TIPO_RECURSO] = $rotulos[Nota::TIPO_RECURSO];
            }
        }

        return $tipos;
    }

    public static function tipoVisivelNoTrimestre(
        string $tipo,
        int $trimestre,
        bool $usaProvaTrimestral = true,
    ): bool {
        return match ($tipo) {
            Nota::TIPO_MAC, Nota::TIPO_PP => true,
            Nota::TIPO_PT => $trimestre !== 3 && $usaProvaTrimestral,
            Nota::TIPO_PG => $trimestre === 3,
            Nota::TIPO_RECUPERACAO, Nota::TIPO_RECURSO => $trimestre === 3,
            default => false,
        };
    }

    /**
     * @param  array{disciplina_id:int, nota_referencia:?float, disciplina_nome?:string}|array{disciplina_id:int, cfd:float}  $disciplinaElegivel
     */
    public static function alunoElegivelExameDisciplina(
        ?array $alunoElegibilidade,
        int $disciplinaId,
    ): bool {
        if (! $alunoElegibilidade || ! ($alunoElegibilidade['elegivel'] ?? false)) {
            return false;
        }

        return collect($alunoElegibilidade['disciplinas_elegiveis'] ?? [])
            ->contains(fn ($d) => (int) ($d['disciplina_id'] ?? 0) === $disciplinaId);
    }

    /**
     * Configuração da escola: permite lançar recuperação/recurso fora da elegibilidade formal.
     */
    public static function permiteLancamentoExameSemElegibilidade(): bool
    {
        return Configuracao::get('exames_permitir_lancamento_nao_elegivel', '1') === '1';
    }

    /**
     * Indica se o professor pode gravar nota de exame para o aluno/disciplina.
     */
    public static function podeLancarExameAlunoDisciplina(
        ?array $alunoElegibilidade,
        int $disciplinaId,
        bool $possuiNotaActiva = false,
    ): bool {
        if ($possuiNotaActiva || self::alunoElegivelExameDisciplina($alunoElegibilidade, $disciplinaId)) {
            return true;
        }

        return self::permiteLancamentoExameSemElegibilidade();
    }

    public static function utilizadorPodeVerElegibilidadeExames($user): bool
    {
        return $user->can('pautas.ver') || $user->can('notas.lancar');
    }

    /**
     * Professor sem acesso às pautas administrativas — vê só as suas disciplinas.
     */
    public static function visaoElegibilidadeRestritaProfessor($user): bool
    {
        return $user->can('notas.lancar') && ! $user->can('pautas.ver');
    }

    /**
     * @return \Illuminate\Support\Collection<int, Turma>
     */
    public static function turmasElegibilidadeParaUtilizador($user, string $anoLectivo): \Illuminate\Support\Collection
    {
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);

        $query = Turma::query()
            ->with('classe')
            ->where('activo', true)
            ->whereHas('anoLectivo', fn ($q) => $q->where('nome', $anoLectivo))
            ->orderBy('nome');

        if ($user->can('pautas.ver') || $user->temControloAcademicoInstitucional()) {
            return $query->get();
        }

        $turmaIds = ProfessorDisciplinaTurma::query()
            ->where('user_id', $user->id)
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->pluck('turma_id')
            ->unique()
            ->filter();

        return $query->whereIn('id', $turmaIds)->get();
    }

    public static function utilizadorPodeVerElegibilidadeTurmaDisciplina(
        $user,
        int $turmaId,
        ?int $disciplinaId = null,
    ): bool {
        if (! self::utilizadorPodeVerElegibilidadeExames($user)) {
            return false;
        }

        if ($user->can('pautas.ver') || $user->temControloAcademicoInstitucional()) {
            return true;
        }

        $turma = Turma::find($turmaId);
        if (! $turma) {
            return false;
        }

        $disciplinas = self::disciplinasLancamentoNaTurma($user, $turma);

        if ($disciplinaId === null) {
            return $disciplinas->isNotEmpty();
        }

        return $disciplinas->contains('id', $disciplinaId);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $linhas
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public static function filtrarLinhasElegibilidadePorDisciplina(
        \Illuminate\Support\Collection $linhas,
        ?int $disciplinaId,
    ): \Illuminate\Support\Collection {
        if ($disciplinaId === null) {
            return $linhas;
        }

        return $linhas
            ->filter(fn (array $l) => (int) ($l['disciplina_id'] ?? 0) === $disciplinaId)
            ->values();
    }

    public static function podeAcederModuloExames($user): bool
    {
        return $user->can('pautas.ver') || $user->can('notas.lancar');
    }

    /**
     * Menu lateral: módulos de exame por classe (10/11 recuperação, 12 recurso).
     * Gestão vê sempre; professor só se lecciona turmas dessas classes no ano actual.
     *
     * @param  array<int, int>  $classesNumero
     */
    /** @var array<string, bool> */
    private static array $menuExameMemo = [];

    public static function utilizadorTemMenuExamePorClasse(
        $user,
        array $classesNumero,
        ?string $anoLectivo = null,
    ): bool {
        $anoLectivo = $anoLectivo ?? Configuracao::getAnoLectivoActual();
        $memoKey = ($user->id ?? 0).'|'.implode(',', $classesNumero).'|'.($anoLectivo ?? '');

        if (array_key_exists($memoKey, self::$menuExameMemo)) {
            return self::$menuExameMemo[$memoKey];
        }

        if ($user->can('pautas.ver') || $user->temControloAcademicoInstitucional()) {
            return self::$menuExameMemo[$memoKey] = true;
        }

        if (! $user->can('notas.lancar')) {
            return self::$menuExameMemo[$memoKey] = false;
        }

        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);

        if (! $anoLectivoId) {
            return self::$menuExameMemo[$memoKey] = false;
        }

        return self::$menuExameMemo[$memoKey] = self::associacoesExameProfessor(
            $user,
            (int) $anoLectivoId,
            $classesNumero
        )->isNotEmpty();
    }

    /**
     * Associações activas do professor (ou todas, para gestão) filtradas por classes.
     *
     * @param  array<int, int>  $classesNumero  ex.: [12] para recurso, [10, 11] para recuperação
     * @return \Illuminate\Support\Collection<int, ProfessorDisciplinaTurma>
     */
    public static function associacoesExameProfessor(
        $user,
        int $anoLectivoId,
        array $classesNumero,
    ): \Illuminate\Support\Collection {
        $query = ProfessorDisciplinaTurma::query()
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->with(['turma.classe', 'turma.curso', 'disciplina'])
            ->whereHas('turma', function ($q) use ($classesNumero) {
                $q->where('activo', true)
                    ->whereHas('classe', function ($c) use ($classesNumero) {
                        $c->where(function ($q2) use ($classesNumero) {
                            foreach ($classesNumero as $n) {
                                $q2->orWhere('codigo', 'like', $n.'%')
                                    ->orWhere('nome', 'like', $n.'%');
                            }
                        });
                    });
            })
            ->orderBy('turma_id')
            ->orderBy('disciplina_id');

        if ($user->temControloAcademicoInstitucional() || $user->hasRole('subdiretor_pedagogico')) {
            return $query->get();
        }

        return $query->where('user_id', $user->id)->get();
    }

    /**
     * @return array<int, int>
     */
    public static function disciplinasProfessorNaTurma($user, Turma $turma): array
    {
        if ($user->can('pautas.ver') || $user->temControloAcademicoInstitucional()) {
            return $turma->curso?->disciplinas->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [];
        }

        $anoLectivoId = $turma->ano_lectivo_id ?? Configuracao::getAnoLectivoActualId();

        return ProfessorDisciplinaTurma::query()
            ->where('user_id', $user->id)
            ->where('turma_id', $turma->id)
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->pluck('disciplina_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
