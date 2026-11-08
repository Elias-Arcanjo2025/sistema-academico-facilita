<?php

namespace App\Http\Controllers;

use App\Events\NotaAlterada;
use App\Exports\NotasTemplateExport;
use App\Http\Requests\NotaAlteracaoRequest;
use App\Http\Requests\NotaLoteRequest;
use App\Http\Requests\NotaRequest;
use App\Imports\NotasImport;
use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Services\NotaService;
use App\Services\PautaService;
use App\Services\Pedagogico\DisciplinaCoordEscopoService;
use App\Services\RecuperacaoService;
use App\Services\RecuperacaoTransicaoService;
use App\Services\RecursoService;
use App\Services\TurmaEscopoService;
use App\Support\NotaLancamentoRules;
use App\Support\PautaResultadoTransicao;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class NotaController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private NotaService $notaService,
        private PautaService $pautaService,
        private RecuperacaoService $recuperacaoService,
        private RecursoService $recursoService,
        private TurmaEscopoService $turmaEscopo,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Nota::class);

        $user = auth()->user();
        $turmaId = $request->get('turma_id');
        $disciplinaId = $request->get('disciplina_id');
        $trimestre = $request->get('trimestre');
        $anoLectivoId = (int) $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());
        $anosLectivos = AnoLectivo::getParaSelect();
        $modoConsulta = Configuracao::isModoConsultaById($anoLectivoId);

        // União: professor + director de turma + coordenador de curso + coordenador de disciplina
        $turmaIds = $this->turmaEscopo->turmaIdsConsultaveis($user, $anoLectivoId);

        $turmas = $turmaIds->isEmpty()
            ? collect()
            : Turma::activo()
                ->where('ano_lectivo_id', $anoLectivoId)
                ->whereIn('id', $turmaIds)
                ->with('curso')
                ->orderBy('nome')
                ->get();

        $disciplinas = $turmaId
            ? $this->disciplinasConsultaIndex($user, (int) $turmaId, $turmas)
            : collect();

        if ($turmaId && ! $turmas->contains('id', (int) $turmaId)) {
            $turmaId = null;
            $disciplinaId = null;
        }

        if ($disciplinaId && ! $disciplinas->contains('id', (int) $disciplinaId)) {
            $disciplinaId = null;
        }

        $dadosAgrupados = collect();
        $turma = null;
        $disciplina = null;

        // Carregar turma e disciplina sempre que os IDs estiverem presentes
        if ($turmaId) {
            $turma = Turma::with('curso')->find($turmaId);
        }
        if ($disciplinaId) {
            $disciplina = Disciplina::find($disciplinaId);
        }

        if ($turmaId && $disciplinaId && $trimestre) {
            // Ano anterior: incluir inactivos (alunos transitados mantêm notas no registo antigo)
            $alunos = NotaLancamentoRules::queryAlunosDaTurmaParaNotas((int) $turmaId, $anoLectivoId)
                ->get();

            // Ano anterior: incluir arquivadas (notas fechadas na transição de ano)
            $notasQuery = Nota::where('turma_id', $turmaId)
                ->where('disciplina_id', $disciplinaId)
                ->where('trimestre', $trimestre)
                ->where('ano_lectivo_id', $anoLectivoId);
            NotaLancamentoRules::aplicarVisibilidadeArquivo($notasQuery, $anoLectivoId, true);

            $notas = Nota::agruparPorAlunoETipoEfectivo($notasQuery->get());

            // Estruturar dados agrupados por aluno
            $dadosAgrupados = $alunos->map(function ($aluno) use ($notas) {
                $notasAluno = $notas->get($aluno->id, collect());

                return [
                    'numero_ordem' => $aluno->numero_ordem,
                    'aluno_id' => $aluno->id,
                    'nome' => $aluno->nome,
                    'mac' => $notasAluno->get(Nota::TIPO_MAC)?->valor,
                    'pp' => $notasAluno->get(Nota::TIPO_PP)?->valor,
                    'pt' => $notasAluno->get(Nota::TIPO_PT)?->valor,
                    'mt' => $notasAluno->get(Nota::TIPO_MT)?->valor,
                    'nota_mac' => $notasAluno->get(Nota::TIPO_MAC),
                    'nota_pp' => $notasAluno->get(Nota::TIPO_PP),
                    'nota_pt' => $notasAluno->get(Nota::TIPO_PT),
                    'nota_mt' => $notasAluno->get(Nota::TIPO_MT),
                ];
            });
        }

        return view('notas.index', compact(
            'turmas',
            'disciplinas',
            'turmaId',
            'disciplinaId',
            'trimestre',
            'dadosAgrupados',
            'turma',
            'disciplina',
            'anoLectivoId',
            'anosLectivos',
            'modoConsulta',
        ));
    }

    /**
     * Disciplinas disponíveis na consulta: plano curricular da turma ou associações do professor.
     */
    private function disciplinasConsultaIndex($user, ?int $turmaId, $turmas)
    {
        if (! $turmaId) {
            return collect();
        }

        $turma = $turmas->firstWhere('id', (int) $turmaId) ?? Turma::find($turmaId);
        if (! $turma) {
            return collect();
        }

        if ($user->temVisaoInstitucional()
            || $user->hasRole('coordenador_curso')
            || $user->hasRole('director_turma')) {
            return Disciplina::paraTurma($turma)->ordenadaPorTipo()->get();
        }

        $disciplinas = collect();

        if ($user->hasRole('coordenador_disciplina')) {
            $coordIds = app(DisciplinaCoordEscopoService::class)
                ->disciplinaIds($user, $turma->ano_lectivo_id);

            if ($coordIds->isNotEmpty()) {
                $disciplinas = Disciplina::paraTurma($turma)
                    ->ordenadaPorTipo()
                    ->get()
                    ->filter(fn ($d) => $coordIds->contains($d->id));
            }
        }

        $doProfessor = ProfessorDisciplinaTurma::where('user_id', $user->id)
            ->where('turma_id', $turmaId)
            ->where('activo', true)
            ->where('ano_lectivo_id', $turma->ano_lectivo_id)
            ->with('disciplina')
            ->get()
            ->pluck('disciplina')
            ->filter(fn ($d) => $d !== null && $d->activo);

        return $disciplinas
            ->merge($doProfessor)
            ->unique('id')
            ->sortBy('nome')
            ->values();
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Nota::class);

        $user = auth()->user();
        $turmaId = $request->get('turma_id');
        $disciplinaId = $request->get('disciplina_id');

        // Obter associacoes do professor
        if ($user->temControloAcademicoInstitucional()) {
            $turmas = Turma::activo()->with('curso')->orderBy('nome')->get();
            $disciplinas = Disciplina::activo()->ordenadaPorTipo()->get();
        } else {
            $associacoes = ProfessorDisciplinaTurma::where('user_id', $user->id)
                ->where('activo', true)
                ->where('ano_lectivo_id', Configuracao::getAnoLectivoActualId())
                ->with(['turma.curso', 'disciplina'])
                ->get();

            $turmas = $associacoes->pluck('turma')->unique('id');
            $disciplinas = $associacoes->pluck('disciplina')->unique('id');
        }

        $alunos = collect();
        $notasExistentes = collect();

        if ($turmaId && $disciplinaId) {
            $alunos = Aluno::where('turma_id', $turmaId)
                ->activo()
                ->ordenado()
                ->get();

            // Obter notas ja lancadas
            $notasExistentes = Nota::where('turma_id', $turmaId)
                ->where('disciplina_id', $disciplinaId)
                ->where('ano_lectivo_id', Configuracao::getAnoLectivoActualId())
                ->get()
                ->groupBy(function ($nota) {
                    return $nota->aluno_id . '-' . $nota->tipo . '-' . $nota->trimestre;
                });
        }

        $tipos = Nota::getTiposActivos();

        return view('notas.create', compact(
            'turmas',
            'disciplinas',
            'alunos',
            'notasExistentes',
            'tipos',
            'turmaId',
            'disciplinaId'
        ));
    }

    public function store(NotaLoteRequest $request): RedirectResponse
    {
        $this->authorize('create', Nota::class);

        $user = auth()->user();
        $data = $request->validated();

        if (! $user->podeIgnorarTrimestreFechado()) {
            $trimestreVerificar = Nota::isTipoExameEspecial($data['tipo']) ? 3 : (int) $data['trimestre'];

            if (! Configuracao::trimestreAbertoParaLancamento($trimestreVerificar)) {
                return back()->with('error', 'O ' . $trimestreVerificar . '.º trimestre está fechado para lançamento de notas. Contacte a administração para mais informações.');
            }
        }

        // Verificar autorizacao para lancar nesta disciplina/turma
        if (! NotaLancamentoRules::utilizadorPodeLancarNaTurmaDisciplina($user, (int) $data['turma_id'], (int) $data['disciplina_id'])) {
            return back()->with('error', 'Não tem autorização para lançar notas nesta disciplina/turma.');
        }

        try {
            if ($data['tipo'] === Nota::TIPO_PG) {
                $resultado = $this->guardarProvaGlobalEmLote(
                    $data['notas'],
                    (int) $data['disciplina_id'],
                    (int) $data['turma_id'],
                    $user,
                );
            } elseif ($data['tipo'] === Nota::TIPO_RECUPERACAO) {
                $resultado = $this->guardarRecuperacaoEmLote(
                    $data['notas'],
                    (int) $data['disciplina_id'],
                    (int) $data['turma_id'],
                    $user,
                );
            } elseif ($data['tipo'] === Nota::TIPO_RECURSO) {
                $resultado = $this->guardarRecursoEmLote(
                    $data['notas'],
                    (int) $data['disciplina_id'],
                    (int) $data['turma_id'],
                    $user,
                );
            } else {
                $resultado = $this->notaService->lancarEmLote(
                    $data['notas'],
                    $data['disciplina_id'],
                    $data['turma_id'],
                    $data['tipo'],
                    $data['trimestre'],
                    $user
                );
            }

            $mensagem = match ($data['tipo']) {
                Nota::TIPO_PG => $this->mensagemResultadoProvaGlobal($resultado),
                Nota::TIPO_RECUPERACAO => $this->mensagemResultadoExameEspecial('recuperação', $resultado),
                Nota::TIPO_RECURSO => $this->mensagemResultadoExameEspecial('recurso', $resultado),
                default => $this->mensagemResultadoLote($resultado),
            };

            $redirectParams = [
                'turma_id' => $data['turma_id'],
                'disciplina_id' => $data['disciplina_id'],
                'trimestre' => $data['trimestre'],
                'tipo' => $data['tipo'],
            ];

            return redirect()->route('notas.lancar', $redirectParams)->with('success', $mensagem);
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Erro ao lançar notas: ' . $e->getMessage());
        }
    }

    /**
     * @param  array{sucesso:int, removidas?:int, bloqueadas?:int, erros?:array, rejeitadas?:int}  $resultado
     */
    private function mensagemResultadoLote(array $resultado): string
    {
        $mensagem = "Notas lançadas com sucesso: {$resultado['sucesso']} aluno(s).";

        if (! empty($resultado['removidas'])) {
            $mensagem .= " {$resultado['removidas']} nota(s) removida(s) (campo vazio).";
        }
        if (! empty($resultado['bloqueadas'])) {
            $mensagem .= " {$resultado['bloqueadas']} bloqueada(s) (pós-conselho).";
        }
        if (! empty($resultado['erros'])) {
            $errosMsg = collect($resultado['erros'])->map(fn ($e) => "{$e['aluno']}: {$e['erro']}")->implode('; ');
            $mensagem .= " Erros: {$errosMsg}";
        }

        return $mensagem;
    }

    /**
     * @param  array{sucesso:int, rejeitadas?:int}  $resultado
     */
    private function mensagemResultadoExameEspecial(string $nomeExame, array $resultado): string
    {
        $mensagem = "Notas de {$nomeExame} guardadas: {$resultado['sucesso']} aluno(s).";

        if (! empty($resultado['rejeitadas'])) {
            $mensagem .= " {$resultado['rejeitadas']} entrada(s) rejeitada(s) (aluno sem elegibilidade nesta disciplina).";
        }

        return $mensagem;
    }

    /**
     * @param  array<int|string, mixed>  $notas
     * @return array{sucesso:int, rejeitadas:int}
     */
    private function guardarRecuperacaoEmLote(
        array $notas,
        int $disciplinaId,
        int $turmaId,
        $user,
    ): array {
        $turma = Turma::with('anoLectivo')->findOrFail($turmaId);
        $anoLectivo = $turma->anoLectivo?->nome ?? $turma->ano_lectivo ?? Configuracao::getAnoLectivoActual();

        abort_unless(
            in_array($turma->numeroClasse(), RecuperacaoService::CLASSES_RECUPERACAO, true),
            422,
            'Recuperação só se aplica às turmas da 10.ª e 11.ª classes.',
        );

        $elegibilidade = $this->recuperacaoService
            ->calcularElegibilidadeAnual($turma, $anoLectivo)
            ->keyBy('aluno_id');

        $notasActivas = [];
        foreach ($this->recuperacaoService->recuperacoesAnuaisTurma($turma, $anoLectivo) as $nota) {
            if ((int) $nota->disciplina_id === $disciplinaId) {
                $notasActivas[$nota->aluno_id] = true;
            }
        }

        $sucesso = 0;
        $rejeitadas = 0;

        DB::transaction(function () use ($notas, $disciplinaId, $turmaId, $anoLectivo, $elegibilidade, $notasActivas, &$sucesso, &$rejeitadas) {
            foreach ($notas as $alunoId => $valor) {
                $alunoElig = $elegibilidade->get((int) $alunoId);
                $possuiNota = isset($notasActivas[(int) $alunoId]);
                $elegivelFormal = NotaLancamentoRules::alunoElegivelExameDisciplina($alunoElig, $disciplinaId);
                $podeLancar = NotaLancamentoRules::podeLancarExameAlunoDisciplina($alunoElig, $disciplinaId, $possuiNota);

                if (! $podeLancar && $valor !== null && $valor !== '') {
                    $rejeitadas++;
                    continue;
                }

                if (! $podeLancar && ($valor === null || $valor === '')) {
                    continue;
                }

                $foraElegibilidade = ! $elegivelFormal && ($valor !== null && $valor !== '');

                $this->recuperacaoService->gravarRecuperacao(
                    (int) $alunoId,
                    $disciplinaId,
                    $turmaId,
                    $valor === null || $valor === '' ? null : (float) $valor,
                    $anoLectivo,
                    $foraElegibilidade,
                );
                $sucesso++;
            }
        });

        return ['sucesso' => $sucesso, 'rejeitadas' => $rejeitadas];
    }

    /**
     * @param  array<int|string, mixed>  $notas
     * @return array{sucesso:int, rejeitadas:int}
     */
    private function guardarRecursoEmLote(
        array $notas,
        int $disciplinaId,
        int $turmaId,
        $user,
    ): array {
        $turma = Turma::with('anoLectivo')->findOrFail($turmaId);
        $anoLectivo = $turma->anoLectivo?->nome ?? $turma->ano_lectivo ?? Configuracao::getAnoLectivoActual();

        abort_unless($turma->numeroClasse() === 12, 422, 'Recurso só se aplica à 12.ª classe.');

        $elegibilidade = $this->recursoService
            ->calcularElegibilidade($turma, $anoLectivo)
            ->keyBy('aluno_id');

        $notasActivas = Nota::where('turma_id', $turmaId)
            ->where('disciplina_id', $disciplinaId)
            ->where('tipo', Nota::TIPO_RECURSO)
            ->where('trimestre', 3)
            ->where('ano_lectivo_id', $turma->ano_lectivo_id ?? AnoLectivo::getIdByNome($anoLectivo))
            ->whereNull('archived_at')
            ->pluck('aluno_id')
            ->flip()
            ->all();

        $sucesso = 0;
        $rejeitadas = 0;

        DB::transaction(function () use ($notas, $disciplinaId, $turmaId, $anoLectivo, $elegibilidade, $notasActivas, &$sucesso, &$rejeitadas) {
            foreach ($notas as $alunoId => $valor) {
                $alunoElig = $elegibilidade->get((int) $alunoId);
                $possuiNota = isset($notasActivas[(int) $alunoId]);
                $elegivelFormal = NotaLancamentoRules::alunoElegivelExameDisciplina($alunoElig, $disciplinaId);
                $podeLancar = NotaLancamentoRules::podeLancarExameAlunoDisciplina($alunoElig, $disciplinaId, $possuiNota);

                if (! $podeLancar && $valor !== null && $valor !== '') {
                    $rejeitadas++;
                    continue;
                }

                if (! $podeLancar && ($valor === null || $valor === '')) {
                    continue;
                }

                $foraElegibilidade = ! $elegivelFormal && ($valor !== null && $valor !== '');

                $this->recursoService->gravarRecurso(
                    (int) $alunoId,
                    $disciplinaId,
                    $turmaId,
                    $valor === null || $valor === '' ? null : (float) $valor,
                    $anoLectivo,
                    $foraElegibilidade,
                );
                $sucesso++;
            }
        });

        return ['sucesso' => $sucesso, 'rejeitadas' => $rejeitadas];
    }

    public function show(Nota $nota): View
    {
        $this->authorize('view', $nota);

        $nota->load(['aluno.turma.curso', 'disciplina', 'turma', 'professor']);

        // Historico de alteracoes
        $historico = \Spatie\Activitylog\Models\Activity::where('subject_type', Nota::class)
            ->where('subject_id', $nota->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('notas.show', compact('nota', 'historico'));
    }

    public function edit(Nota $nota): View
    {
        $this->authorize('update', $nota);

        $nota->load(['aluno', 'disciplina', 'turma']);

        return view('notas.edit', compact('nota'));
    }

    public function update(NotaAlteracaoRequest $request, Nota $nota): RedirectResponse
    {
        $this->authorize('update', $nota);

        $user = auth()->user();
        $data = $request->validated();

        if (!$user->podeIgnorarTrimestreFechado()) {
            if (!Configuracao::trimestreAbertoParaLancamento($nota->trimestre)) {
                return back()->with('error', 'O ' . $nota->trimestre . '.º trimestre está fechado para edição de notas. Contacte a administração para mais informações.');
            }
        }

        // Camada extra de segurança: verificar associação ativa para professores
        if (!$user->temControloAcademicoInstitucional() &&
            !$user->hasRole('director_turma') && !$user->hasRole('coordenador_curso')) {

            $temAssociacao = ProfessorDisciplinaTurma::where('user_id', $user->id)
                ->where('disciplina_id', $nota->disciplina_id)
                ->where('turma_id', $nota->turma_id)
                ->where('activo', true)
                ->where('ano_lectivo_id', Configuracao::getAnoLectivoActualId())
                ->exists();

            if (!$temAssociacao) {
                return back()->with('error', 'Não tem autorização para editar notas desta disciplina/turma. Sua associação pode estar inactiva.');
            }
        }

        try {
            $this->notaService->alterar(
                $nota,
                $data['valor'],
                $data['justificacao'],
                $user
            );

            return redirect()->route('notas.show', $nota)
                ->with('success', 'Nota alterada com sucesso.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Erro ao alterar nota: ' . $e->getMessage());
        }
    }

    public function destroy(Nota $nota): RedirectResponse
    {
        $this->authorize('delete', $nota);

        $turmaId = $nota->turma_id;
        $disciplinaId = $nota->disciplina_id;

        activity()
            ->causedBy(auth()->user())
            ->performedOn($nota)
            ->withProperties([
                'aluno_id' => $nota->aluno_id,
                'valor' => $nota->valor,
                'tipo' => $nota->tipo,
            ])
            ->log('Nota eliminada');

        $nota->delete();

        return redirect()->route('notas.index', [
            'turma_id' => $turmaId,
            'disciplina_id' => $disciplinaId,
        ])->with('success', 'Nota eliminada com sucesso.');
    }

    /**
     * Vista de lançamento por turma/disciplina.
     * modo=folha → folha completa (MAC/PP/PT|PG); caso contrário → por tipo (inclui exames).
     */
    public function lancar(Request $request): View
    {
        if ($request->query('modo') === 'folha') {
            return $this->buildLancamentoLoteView($request);
        }

        $this->authorize('create', Nota::class);

        $user = auth()->user();
        $trimestreActual = Configuracao::getTrimestreActual();

        // Filtro de ano lectivo
        $anoLectivoId = $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());
        $turmaId = $request->get('turma_id');
        $disciplinaId = $request->get('disciplina_id');
        $trimestre = (int) $request->get('trimestre', $trimestreActual);

        if (! $user->podeIgnorarTrimestreFechado() && ! Configuracao::trimestreAbertoParaLancamento($trimestre)) {
            $trimestre = $trimestreActual;
        }

        $tipo = $request->get('tipo', Nota::TIPO_MAC);

        if ($tipo === Nota::TIPO_PG && $trimestre !== 3) {
            $tipo = Nota::TIPO_MAC;
        }

        if ($tipo === Nota::TIPO_PT && $trimestre === 3) {
            $tipo = Nota::TIPO_MAC;
        }

        if (Nota::isTipoExameEspecial($tipo)) {
            $trimestre = 3;
        }

        $turma = $turmaId ? Turma::with('curso')->find($turmaId) : null;

        if ($tipo === Nota::TIPO_RECUPERACAO && (! $turma || ! in_array($turma->numeroClasse(), RecuperacaoService::CLASSES_RECUPERACAO, true))) {
            $tipo = Nota::TIPO_MAC;
        }

        if ($tipo === Nota::TIPO_RECURSO && (! $turma || $turma->numeroClasse() !== 12)) {
            $tipo = Nota::TIPO_MAC;
        }

        // Obter associacoes do professor filtradas por ano
        if ($user->temControloAcademicoInstitucional() || $user->hasRole('subdiretor_pedagogico')) {
            $associacoes = ProfessorDisciplinaTurma::where('activo', true)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->with(['turma.curso', 'disciplina'])
                ->get();
        } else {
            $associacoes = ProfessorDisciplinaTurma::where('user_id', $user->id)
                ->where('activo', true)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->with(['turma.curso', 'disciplina'])
                ->get();
        }

        $alunos = collect();
        $notasExistentes = [];
        $notasPosConselho = [];
        $elegibilidadeExame = [];
        $rotuloReferenciaExame = 'CA/CFD';
        $permiteLancamentoSemElegibilidade = NotaLancamentoRules::permiteLancamentoExameSemElegibilidade();
        $modoExameEspecial = Nota::isTipoExameEspecial($tipo);

        if ($turmaId && $disciplinaId) {
            $turma = Turma::with(['curso', 'anoLectivo'])->find($turmaId);
            $anoLectivo = $turma?->anoLectivo?->nome ?? $turma?->ano_lectivo ?? AnoLectivo::getNomeById($anoLectivoId);

            $alunos = NotaLancamentoRules::queryAlunosDaTurmaParaNotas((int) $turmaId, (int) $anoLectivoId)
                ->get();

            if ($tipo === Nota::TIPO_PG) {
                $fallbackPt3ComoPg = $turma && ! $turma->curso?->regime()->usaProvaTrimestral(3);

                foreach ($alunos as $aluno) {
                    $valor = Nota::valorProvaGlobal(
                        $aluno->id,
                        (int) $disciplinaId,
                        (int) $turmaId,
                        $anoLectivoId,
                        $fallbackPt3ComoPg,
                    );

                    if ($valor !== null) {
                        $notasExistentes[$aluno->id] = $valor;
                    }
                }
            } elseif ($modoExameEspecial && $turma) {
                $dadosExame = $this->carregarDadosExameEspecial(
                    $turma,
                    (int) $disciplinaId,
                    $tipo,
                    $anoLectivo,
                    (int) $anoLectivoId,
                    $alunos,
                );
                $notasExistentes = $dadosExame['notasExistentes'];
                $elegibilidadeExame = $dadosExame['elegibilidadeExame'];
                $rotuloReferenciaExame = $dadosExame['rotuloReferenciaColuna'];
                $permiteLancamentoSemElegibilidade = $dadosExame['permiteSemElegibilidade'];
            } else {
                // Pré-conselho: editável pelo professor (ano anterior inclui arquivadas)
                $notasPreQuery = Nota::where('turma_id', $turmaId)
                    ->where('disciplina_id', $disciplinaId)
                    ->where('tipo', $tipo)
                    ->where('trimestre', $trimestre)
                    ->where('ano_lectivo_id', $anoLectivoId)
                    ->preConselhoLancamento();
                NotaLancamentoRules::aplicarVisibilidadeArquivo($notasPreQuery, (int) $anoLectivoId, true);

                foreach ($notasPreQuery->get() as $nota) {
                    $notasExistentes[$nota->aluno_id] = $nota->valor;
                }

                // Pós-conselho sem pré activo: bloqueado (não removível pelo professor)
                $notasPosQuery = Nota::where('turma_id', $turmaId)
                    ->where('disciplina_id', $disciplinaId)
                    ->where('tipo', $tipo)
                    ->where('trimestre', $trimestre)
                    ->where('ano_lectivo_id', $anoLectivoId)
                    ->posConselho();
                NotaLancamentoRules::aplicarVisibilidadeArquivo($notasPosQuery, (int) $anoLectivoId, true);

                foreach ($notasPosQuery->get() as $nota) {
                    if (! isset($notasExistentes[$nota->aluno_id])) {
                        $notasPosConselho[$nota->aluno_id] = $nota->valor;
                    }
                }
            }
        }

        $tipos = NotaLancamentoRules::tiposLancamentoDisponiveis($turma);
        $turma = $turma ?? ($turmaId ? Turma::with('curso')->find($turmaId) : null);
        $disciplina = $disciplinaId ? Disciplina::find($disciplinaId) : null;
        $anoLectivo = AnoLectivo::getNomeById($anoLectivoId);
        $usaProvaTrimestral = $turma?->curso?->regime()->usaProvaTrimestral((int) $trimestre) ?? true;

        // Dados para o filtro
        $anosLectivos = AnoLectivo::getParaSelect();
        $modoConsulta = Configuracao::isModoConsultaById($anoLectivoId);

        // Verificar se o trimestre está fechado para lançamento
        $trimestreControlo = $modoExameEspecial ? 3 : $trimestre;
        $trimestreFechado = ! $user->podeIgnorarTrimestreFechado()
            && ! Configuracao::trimestreAbertoParaLancamento($trimestreControlo);

        // Verificar status de cada trimestre (para desabilitar no select)
        $trimestresStatus = [];
        for ($t = 1; $t <= 3; $t++) {
            $trimestresStatus[$t] = [
                'aberto' => Configuracao::trimestreAbertoParaLancamento($t),
                'actual' => $t === $trimestreActual,
            ];
        }

        $modoLancamento = 'tipo';

        return view('notas.lancar', compact(
            'associacoes',
            'alunos',
            'notasExistentes',
            'notasPosConselho',
            'elegibilidadeExame',
            'rotuloReferenciaExame',
            'permiteLancamentoSemElegibilidade',
            'modoExameEspecial',
            'tipos',
            'turmaId',
            'disciplinaId',
            'trimestre',
            'tipo',
            'turma',
            'disciplina',
            'anoLectivo',
            'anosLectivos',
            'anoLectivoId',
            'modoConsulta',
            'trimestreFechado',
            'trimestreActual',
            'trimestresStatus',
            'usaProvaTrimestral',
            'modoLancamento',
        ));
    }

    /**
     * API para obter disciplinas de uma turma (para o professor)
     */
    public function getDisciplinas(Request $request)
    {
        $turmaId = $request->get('turma_id');
        $user = auth()->user();

        if (!$turmaId) {
            return response()->json([]);
        }

        if ($user->temControloAcademicoInstitucional() || $user->hasRole('subdiretor_pedagogico')) {
            $turma = Turma::find($turmaId);
            if (!$turma || !$turma->classe_id) {
                return response()->json([]);
            }

            $disciplinas = Disciplina::paraTurma($turma)
                ->orderBy('nome')
                ->get(['id', 'nome', 'codigo']);
        } else {
            $disciplinas = ProfessorDisciplinaTurma::where('user_id', $user->id)
                ->where('turma_id', $turmaId)
                ->where('activo', true)
                ->where('ano_lectivo_id', Configuracao::getAnoLectivoActualId())
                ->with('disciplina')
                ->get()
                ->pluck('disciplina')
                ->map(fn($d) => ['id' => $d->id, 'nome' => $d->nome, 'codigo' => $d->codigo]);
        }

        return response()->json($disciplinas);
    }

    /**
     * Vista de lançamento de Prova Global (PG)
     * PG é uma nota anual (sem trimestre)
     */
    public function lancarPG(Request $request): View
    {
        $this->authorize('create', Nota::class);

        $user = auth()->user();
        // Filtro de ano lectivo
        $anoLectivoId = $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());
        $turmaId = $request->get('turma_id');
        $disciplinaId = $request->get('disciplina_id');

        // Obter associações do professor filtradas por ano
        if ($user->temControloAcademicoInstitucional() || $user->hasRole('subdiretor_pedagogico')) {
            $associacoes = ProfessorDisciplinaTurma::where('activo', true)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->with(['turma.curso', 'disciplina'])
                ->get();
        } else {
            $associacoes = ProfessorDisciplinaTurma::where('user_id', $user->id)
                ->where('activo', true)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->with(['turma.curso', 'disciplina'])
                ->get();
        }

        $alunos = collect();
        $notasExistentes = [];

        if ($turmaId && $disciplinaId) {
            $alunos = NotaLancamentoRules::queryAlunosDaTurmaParaNotas((int) $turmaId, (int) $anoLectivoId)
                ->get();

            // Obter notas PG existentes (com fallback PT3 no regime politécnico)
            $turmaComCurso = Turma::with('curso')->find($turmaId);
            $fallbackPt3ComoPg = $turmaComCurso && ! $turmaComCurso->curso?->regime()->usaProvaTrimestral(3);

            foreach ($alunos as $aluno) {
                $valor = Nota::valorProvaGlobal(
                    $aluno->id,
                    (int) $disciplinaId,
                    (int) $turmaId,
                    $anoLectivoId,
                    $fallbackPt3ComoPg,
                );

                if ($valor !== null) {
                    $notasExistentes[$aluno->id] = $valor;
                }
            }
        }

        $turma = $turmaId ? Turma::with('curso')->find($turmaId) : null;
        $disciplina = $disciplinaId ? Disciplina::find($disciplinaId) : null;
        $anoLectivo = AnoLectivo::getNomeById($anoLectivoId);

        // Dados para o filtro
        $anosLectivos = AnoLectivo::getParaSelect();
        $modoConsulta = Configuracao::isModoConsultaById($anoLectivoId);

        return view('notas.lancar-pg', compact(
            'associacoes',
            'alunos',
            'notasExistentes',
            'turmaId',
            'disciplinaId',
            'turma',
            'disciplina',
            'anoLectivo',
            'anosLectivos',
            'anoLectivoId',
            'modoConsulta'
        ));
    }

    /**
     * Guardar lançamento de Prova Global (PG)
     */
    public function storePG(Request $request): RedirectResponse
    {
        $this->authorize('create', Nota::class);

        $validated = $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
            'notas' => 'required|array',
            'notas.*.aluno_id' => 'required|exists:alunos,id',
            'notas.*.valor' => 'nullable|numeric|min:0|max:20',
        ]);

        $user = auth()->user();

        if (! NotaLancamentoRules::utilizadorPodeLancarNaTurmaDisciplina(
            $user,
            (int) $validated['turma_id'],
            (int) $validated['disciplina_id']
        )) {
            return back()->with('error', 'Não tem autorização para lançar Prova Global nesta disciplina/turma.');
        }

        $notas = [];
        foreach ($validated['notas'] as $notaData) {
            $notas[(int) $notaData['aluno_id']] = $notaData['valor'] ?? null;
        }

        $resultado = $this->guardarProvaGlobalEmLote(
            $notas,
            (int) $validated['disciplina_id'],
            (int) $validated['turma_id'],
            auth()->user(),
        );

        return back()->with('success', $this->mensagemResultadoProvaGlobal($resultado));
    }

    /**
     * URL legada: redirecciona para Lançar Notas em modo folha completa.
     */
    public function lancamentoLoteForm(Request $request): RedirectResponse
    {
        $this->authorize('notas.lancar');

        return redirect()->route('notas.lancar', array_merge(
            $request->query(),
            ['modo' => 'folha'],
        ));
    }

    /**
     * Folha completa (MAC + PP + PT/PG) — usada via notas.lancar?modo=folha.
     */
    protected function buildLancamentoLoteView(Request $request): View
    {
        $this->authorize('notas.lancar');

        $user = auth()->user();
        $trimestreActual = Configuracao::getTrimestreActual();

        $turmaId = $request->get('turma_id');
        $disciplinaId = $request->get('disciplina_id');
        $trimestre = $request->get('trimestre', $trimestreActual);
        $anoLectivoId = (int) $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());
        $anosLectivos = AnoLectivo::getParaSelect();
        $modoConsulta = Configuracao::isModoConsultaById($anoLectivoId);
        $modoLancamento = 'folha';

        $trimestresStatus = [];
        for ($t = 1; $t <= 3; $t++) {
            $trimestresStatus[$t] = [
                'aberto' => Configuracao::trimestreAbertoParaLancamento($t),
                'actual' => $t === $trimestreActual,
            ];
        }

        $trimestreFechado = !$user->podeIgnorarTrimestreFechado() && !Configuracao::trimestreAbertoParaLancamento($trimestre);

        $anoLectivoNome = AnoLectivo::getNomeById($anoLectivoId);

        // Filtrar turmas baseado no perfil do usuário
        if ($user->temVisaoInstitucional()) {
            $turmas = Turma::activo()
                ->where('ano_lectivo_id', $anoLectivoId)
                ->with('curso')
                ->orderBy('nome')
                ->get();
        } elseif ($user->hasRole('director_turma')) {
            $turmas = $user->turmasComoDirector()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo', $anoLectivoNome)
                ->where('turmas.activo', true)
                ->where('turmas.ano_lectivo_id', $anoLectivoId)
                ->with('curso')
                ->orderBy('turmas.nome')
                ->get();
        } else {
            // Professor (incluindo coordenadores que leccionam): turmas onde está associado
            $assocs = ProfessorDisciplinaTurma::where('user_id', $user->id)
                ->where('activo', true)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->with(['turma.curso'])
                ->get();
            $turmas = $assocs
                ->pluck('turma')
                ->filter(fn($t) => $t !== null && $t->activo)
                ->unique('id')
                ->sortBy('nome')
                ->values();

            // Fallback: coordenador puro sem associações de professor
            if ($turmas->isEmpty() && $user->hasRole('coordenador_curso')) {
                $cursosIds = $user->cursosComoCoordenador()
                    ->wherePivot('activo', true)
                    ->wherePivot('ano_lectivo_id', $anoLectivoId)
                    ->pluck('cursos.id');
                $turmas = Turma::activo()
                    ->where('ano_lectivo_id', $anoLectivoId)
                    ->whereIn('curso_id', $cursosIds)
                    ->with('curso')
                    ->orderBy('nome')
                    ->get();
            }
        }

        $disciplinas = collect();
        $alunos = collect();

        // Carregar disciplinas se turma foi selecionada
        if ($turmaId) {
            $turma = Turma::findOrFail($turmaId);
            $disciplinas = NotaLancamentoRules::disciplinasLancamentoNaTurma($user, $turma);
        }

        if ($turmaId && ! $turmas->contains('id', (int) $turmaId)) {
            $turmaId = null;
            $disciplinaId = null;
            $disciplinas = collect();
        }

        if ($disciplinaId && ! $disciplinas->contains('id', (int) $disciplinaId)) {
            $disciplinaId = null;
        }

        // Carregar alunos se turma E disciplina foram selecionadas
        if ($turmaId && $disciplinaId) {
            $turma = Turma::with('curso')->findOrFail($turmaId);
            $disciplina = Disciplina::findOrFail($disciplinaId);

            $alunos = NotaLancamentoRules::queryAlunosDaTurmaParaNotas((int) $turmaId, $anoLectivoId)
                ->get();

            // Ano anterior: incluir arquivadas (fecho na transição); activas têm prioridade
            $notasQuery = Nota::where('turma_id', $turmaId)
                ->where('disciplina_id', $disciplinaId)
                ->where('trimestre', $trimestre)
                ->where('ano_lectivo_id', $anoLectivoId);
            NotaLancamentoRules::aplicarVisibilidadeArquivo($notasQuery, $anoLectivoId, true);

            $notasExistentes = $notasQuery->get()->groupBy('aluno_id');

            foreach ($alunos as $aluno) {
                $notas = $notasExistentes->get($aluno->id, collect());
                $aluno->nota_mac = $notas->firstWhere('tipo', Nota::TIPO_MAC)?->valor;
                $aluno->nota_pp = $notas->firstWhere('tipo', Nota::TIPO_PP)?->valor;

                if ((int) $trimestre === 3) {
                    $aluno->nota_pg = Nota::valorProvaGlobal(
                        $aluno->id,
                        (int) $disciplinaId,
                        (int) $turmaId,
                        $anoLectivoId,
                        true,
                    );
                    $aluno->nota_pt = null;
                } else {
                    $aluno->nota_pt = $notas->firstWhere('tipo', Nota::TIPO_PT)?->valor;
                    $aluno->nota_pg = null;
                }
            }
        }

        $turmaSeleccionada = $turmaId ? Turma::with('curso')->find($turmaId) : null;

        return view('notas.lancamento-lote', compact(
            'turmas', 'disciplinas', 'alunos', 'turmaId', 'disciplinaId',
            'trimestre', 'trimestreActual', 'trimestresStatus', 'trimestreFechado',
            'turmaSeleccionada', 'anoLectivoId', 'anosLectivos', 'modoConsulta',
            'modoLancamento',
        ));
    }

    /**
     * Processar lançamento em lote
     */
    public function lancamentoLoteStore(Request $request): RedirectResponse
    {
        $this->authorize('notas.lancar');

        $validated = $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
            'trimestre' => 'required|integer|min:1|max:3',
            'notas' => 'required|array',
            'notas.*.aluno_id' => 'required|exists:alunos,id',
            'notas.*.mac' => 'nullable|numeric|min:0|max:20',
            'notas.*.pp' => 'nullable|numeric|min:0|max:20',
            'notas.*.pt' => 'nullable|numeric|min:0|max:20',
            'notas.*.pg' => 'nullable|numeric|min:0|max:20',
        ]);

        $user = auth()->user();
        if (!$user->podeIgnorarTrimestreFechado()) {
            if (!Configuracao::trimestreAbertoParaLancamento($validated['trimestre'])) {
                return back()->with('error', 'O ' . $validated['trimestre'] . '.º trimestre não está aberto para lançamento de notas. Apenas o trimestre actual está disponível.');
            }
        }

        if (! NotaLancamentoRules::disciplinaPertenceTurma((int) $validated['disciplina_id'], (int) $validated['turma_id'])) {
            return back()->with('error', 'A disciplina não faz parte do plano curricular desta turma.');
        }

        if (! NotaLancamentoRules::utilizadorPodeLancarNaTurmaDisciplina($user, (int) $validated['turma_id'], (int) $validated['disciplina_id'])) {
            return back()->with('error', 'Não tem permissão para lançar notas nesta disciplina/turma.');
        }

        $turmaLote = Turma::with('curso')->findOrFail($validated['turma_id']);
        $anoLectivoId = (int) $turmaLote->ano_lectivo_id;

        if (Configuracao::isModoConsultaById($anoLectivoId, $user)) {
            return back()->with('error', 'Ano lectivo anterior — lançamento de notas desactivado (modo consulta).');
        }

        $ehTerceiroTrimestre = (int) $validated['trimestre'] === 3;

        DB::beginTransaction();
        try {
            $stats = ['criadas' => 0, 'actualizadas' => 0, 'removidas' => 0, 'bloqueadas' => 0];

            $campos = [
                'mac' => [Nota::TIPO_MAC, (int) $validated['trimestre']],
                'pp' => [Nota::TIPO_PP, (int) $validated['trimestre']],
            ];
            if ($ehTerceiroTrimestre) {
                $campos['pg'] = [Nota::TIPO_PG, null];
            } else {
                $campos['pt'] = [Nota::TIPO_PT, (int) $validated['trimestre']];
            }

            foreach ($validated['notas'] as $notaData) {
                $alunoId = (int) $notaData['aluno_id'];

                foreach ($campos as $campo => [$tipo, $trimestreNota]) {
                    $accao = $this->notaService->gravarOuRemoverLancamento(
                        $notaData[$campo] ?? null,
                        $alunoId,
                        (int) $validated['disciplina_id'],
                        (int) $validated['turma_id'],
                        $tipo,
                        $trimestreNota,
                        $user,
                        $anoLectivoId,
                    );

                    match ($accao) {
                        'criada' => $stats['criadas']++,
                        'actualizada' => $stats['actualizadas']++,
                        'removida' => $stats['removidas']++,
                        'bloqueada' => $stats['bloqueadas']++,
                        default => null,
                    };
                }
            }

            activity()
                ->causedBy(auth()->user())
                ->withProperties([
                    'turma_id' => $validated['turma_id'],
                    'disciplina_id' => $validated['disciplina_id'],
                    'trimestre' => $validated['trimestre'],
                    ...$stats,
                ])
                ->log('Lançamento de notas em lote');

            DB::commit();

            $partes = [];
            if ($stats['criadas'] > 0) {
                $partes[] = "{$stats['criadas']} criadas";
            }
            if ($stats['actualizadas'] > 0) {
                $partes[] = "{$stats['actualizadas']} actualizadas";
            }
            if ($stats['removidas'] > 0) {
                $partes[] = "{$stats['removidas']} removidas (campo vazio)";
            }
            if ($stats['bloqueadas'] > 0) {
                $partes[] = "{$stats['bloqueadas']} bloqueadas (pós-conselho)";
            }

            $mensagem = $partes !== []
                ? 'Lançamento concluído: ' . implode(', ', $partes) . '.'
                : 'Nenhuma alteração efectuada.';

            return redirect()->route('notas.lancar', [
                'modo' => 'folha',
                'ano_lectivo_id' => $anoLectivoId,
                'turma_id' => $validated['turma_id'],
                'disciplina_id' => $validated['disciplina_id'],
                'trimestre' => $validated['trimestre'],
            ])->with('success', $mensagem);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erro no lançamento em lote: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Erro ao lançar notas: ' . $e->getMessage());
        }
    }

    /**
     * Formulário de importação via Excel
     */
    public function importacaoForm(): View
    {
        $this->authorize('notas.lancar');

        $user = auth()->user();
        $trimestreActual = Configuracao::getTrimestreActual();
        $anoLectivoId = Configuracao::getAnoLectivoActualId();
        $anoLectivoNome = AnoLectivo::getNomeById($anoLectivoId);

        $trimestresStatus = [];
        for ($t = 1; $t <= 3; $t++) {
            $trimestresStatus[$t] = [
                'aberto' => Configuracao::trimestreAbertoParaLancamento($t),
                'actual' => $t === $trimestreActual,
            ];
        }

        if ($user->temVisaoInstitucional()) {
            $turmas = Turma::activo()
                ->where('ano_lectivo_id', $anoLectivoId)
                ->with('curso')
                ->orderBy('nome')
                ->get();
        } elseif ($user->hasRole('coordenador_curso')) {
            $cursosIds = $user->cursosComoCoordenador()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo_id', $anoLectivoId)
                ->pluck('cursos.id');

            $turmas = Turma::activo()
                ->where('ano_lectivo_id', $anoLectivoId)
                ->whereIn('curso_id', $cursosIds)
                ->with('curso')
                ->orderBy('nome')
                ->get();
        } elseif ($user->hasRole('director_turma')) {
            $turmas = $user->turmasComoDirector()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo', $anoLectivoNome)
                ->where('turmas.activo', true)
                ->where('turmas.ano_lectivo_id', $anoLectivoId)
                ->with('curso')
                ->orderBy('turmas.nome')
                ->get();
        } else {
            $turmas = collect();
        }

        return view('notas.importacao', compact('turmas', 'trimestreActual', 'trimestresStatus'));
    }

    /**
     * Processar importação via Excel
     */
    public function importacaoStore(Request $request): RedirectResponse
    {
        $this->authorize('notas.lancar');

        $validated = $request->validate([
            'arquivo' => 'required|file|mimes:xlsx,xls|max:10240',
            'turma_id' => 'required|exists:turmas,id',
            'trimestre' => 'required|integer|min:1|max:3',
        ]);

        $user = auth()->user();
        if (!$user->podeIgnorarTrimestreFechado()) {
            if (!Configuracao::trimestreAbertoParaLancamento($validated['trimestre'])) {
                return back()->with('error', 'O ' . $validated['trimestre'] . '.º trimestre não está aberto para importação de notas. Apenas o trimestre actual está disponível.');
            }
        }

        $turmaId = $validated['turma_id'];
        $turma = Turma::findOrFail($turmaId);

        if (! NotaLancamentoRules::utilizadorPodeLancarNaTurma($user, $turmaId)) {
            return back()->with('error', 'Não tem permissão para importar notas para esta turma.');
        }

        DB::beginTransaction();
        try {
            $arquivo = $request->file('arquivo');
            $trimestre = $validated['trimestre'];
            $anoLectivoId = Configuracao::getAnoLectivoActualId();

            // Buscar disciplinas do curso e classe da turma (ordem do sistema)
            $disciplinas = $this->disciplinasOrdenadasDaTurma($turma);

            $importPreview = new NotasImport(
                $turmaId,
                $disciplinas,
                $trimestre,
                $anoLectivoId,
                auth()->id()
            );
            $rowsExcel = Excel::toCollection($importPreview, $arquivo)->first();
            $comparacaoExcel = app(\App\Services\ImportacaoComparadorService::class)->comparar(
                $rowsExcel,
                $turmaId,
                $disciplinas,
                $trimestre,
                $anoLectivoId,
            );

            // Processar importação
            $import = new NotasImport(
                $turmaId,
                $disciplinas,
                $trimestre,
                $anoLectivoId,
                auth()->id()
            );

            Excel::import($import, $arquivo);

            $anoLectivoNome = AnoLectivo::getNomeById($anoLectivoId);
            $mtsCalculadas = 0;
            foreach ($disciplinas as $disciplina) {
                $resultadoMt = $this->pautaService->calcularMediasTrimestraisTurma(
                    $turmaId,
                    $disciplina->id,
                    $trimestre,
                    auth()->id(),
                    $anoLectivoNome
                );
                $mtsCalculadas += $resultadoMt['calculadas'];
            }

            // Log de auditoria
            activity()
                ->causedBy(auth()->user())
                ->withProperties([
                    'turma_id' => $turmaId,
                    'trimestre' => $trimestre,
                    'criadas' => $import->criadas,
                    'atualizadas' => $import->atualizadas,
                    'mts_calculadas' => $mtsCalculadas,
                    'erros' => count($import->erros),
                ])
                ->log('Importação de notas via Excel');

            if (($comparacaoExcel['divergencias'] ?? 0) > 0) {
                activity()
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'turma_id' => $turmaId,
                        'divergencias' => $comparacaoExcel['divergencias'],
                        'amostra' => array_slice($comparacaoExcel['linhas'] ?? [], 0, 10),
                    ])
                    ->log('Importação Excel com divergências face ao Facilita');
            }

            DB::commit();

            // Preparar mensagens
            $mensagens = [];
            if ($import->criadas > 0) {
                $mensagens[] = "{$import->criadas} nota(s) criada(s)";
            }
            if ($import->atualizadas > 0) {
                $mensagens[] = "{$import->atualizadas} nota(s) atualizada(s)";
            }
            if ($mtsCalculadas > 0) {
                $mensagens[] = "{$mtsCalculadas} média(s) trimestral(is) calculada(s)";
            }
            if (count($import->erros) > 0) {
                $mensagens[] = count($import->erros) . " erro(s) encontrado(s)";
            }

            $mensagem = "Importação concluída: " . implode(', ', $mensagens);

            $redirect = back();
            if (($comparacaoExcel['divergencias'] ?? 0) > 0) {
                $redirect = $redirect
                    ->with('warning', $mensagem.' — '.$comparacaoExcel['divergencias'].' valor(es) no Excel diferem do Facilita.')
                    ->with('comparador_importacao', $comparacaoExcel);
            }

            if (count($import->erros) > 0) {
                return $redirect
                    ->with('warning', $mensagem)
                    ->with('erros_importacao', $import->erros);
            }

            if (($comparacaoExcel['divergencias'] ?? 0) > 0) {
                return $redirect;
            }

            return back()->with('success', $mensagem);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erro na importação: ' . $e->getMessage());
            return back()->with('error', 'Erro ao importar arquivo: ' . $e->getMessage());
        }
    }

    /**
     * Download template Excel
     */
    public function downloadTemplate(Request $request)
    {
        $this->authorize('notas.lancar');

        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'trimestre' => 'required|integer|min:1|max:3',
        ]);

        $turmaId = $request->get('turma_id');
        $trimestre = $request->get('trimestre');
        $anoLectivoId = Configuracao::getAnoLectivoActualId();

        // Buscar turma e disciplinas
        $turma = Turma::with('curso')->findOrFail($turmaId);

        $disciplinas = $this->disciplinasOrdenadasDaTurma($turma);

        if ($disciplinas->isEmpty()) {
            return back()->with('error', 'Nenhuma disciplina encontrada para esta turma.');
        }

        // Gerar arquivo Excel
        $filename = 'Notas_' . $turma->nome . '_' . $trimestre . 'Trim_' . date('Y-m-d') . '.xlsx';

        return Excel::download(
            new NotasTemplateExport($turma, $disciplinas, $trimestre, $anoLectivoId),
            $filename
        );
    }

    /**
     * @param  array<int, mixed>  $notas  [aluno_id => valor]
     * @return array{sucesso:int, removidas:int, bloqueadas:int, erros:array<int, array{aluno:string, erro:string}>}
     */
    private function guardarProvaGlobalEmLote(array $notas, int $disciplinaId, int $turmaId, $user): array
    {
        $anoLectivoId = Configuracao::getAnoLectivoActualId();
        $turmaPg = Turma::with('curso')->find($turmaId);
        $fallbackPt3ComoPg = $turmaPg && ! $turmaPg->curso?->regime()->usaProvaTrimestral(3);

        $resultado = [
            'sucesso' => 0,
            'removidas' => 0,
            'bloqueadas' => 0,
            'erros' => [],
        ];

        $alunoIds = Aluno::where('turma_id', $turmaId)->activo()->pluck('id');

        foreach ($alunoIds as $alunoId) {
            $valor = array_key_exists($alunoId, $notas) ? $notas[$alunoId] : null;

            try {
                $accao = $this->notaService->gravarOuRemoverLancamento(
                    $valor,
                    (int) $alunoId,
                    $disciplinaId,
                    $turmaId,
                    Nota::TIPO_PG,
                    null,
                    $user,
                    $anoLectivoId,
                );

                if (in_array($accao, ['criada', 'actualizada'], true)) {
                    $resultado['sucesso']++;
                } elseif ($accao === 'removida') {
                    $resultado['removidas']++;
                    if ($fallbackPt3ComoPg) {
                        $this->notaService->removerLancamentoProfessor(
                            (int) $alunoId,
                            $disciplinaId,
                            $turmaId,
                            Nota::TIPO_PT,
                            3,
                            $anoLectivoId,
                            $user,
                            'Campo PG vazio — PT3 legado removido',
                        );
                    }
                } elseif ($accao === 'bloqueada') {
                    $resultado['bloqueadas']++;
                }
            } catch (\Exception $e) {
                $aluno = Aluno::find($alunoId);
                $resultado['erros'][] = [
                    'aluno' => $aluno ? $aluno->nome : "ID: {$alunoId}",
                    'erro' => $e->getMessage(),
                ];
            }
        }

        return $resultado;
    }

    /**
     * Dados para lançamento de recuperação/recurso na turma inteira (CA/CFD + elegibilidade).
     *
     * @param  \Illuminate\Support\Collection<int, Aluno>  $alunos
     * @return array{
     *   notasExistentes: array<int, float>,
     *   elegibilidadeExame: array<int, array{elegivel:bool, pode_lancar:bool, motivo:?string, referencia:?float, rotulo_referencia:string}>,
     *   rotuloReferenciaColuna: string,
     * }
     */
    private function carregarDadosExameEspecial(
        Turma $turma,
        int $disciplinaId,
        string $tipo,
        string $anoLectivo,
        int $anoLectivoId,
        \Illuminate\Support\Collection $alunos,
    ): array {
        $classeNumero = $turma->numeroClasse();
        $notasExistentes = [];
        $elegibilidadeExame = [];

        if ($tipo === Nota::TIPO_RECUPERACAO) {
            $elegibilidade = $this->recuperacaoService
                ->calcularElegibilidadeAnual($turma, $anoLectivo)
                ->keyBy('aluno_id');

            foreach ($this->recuperacaoService->recuperacoesAnuaisTurma($turma, $anoLectivo) as $nota) {
                if ((int) $nota->disciplina_id === $disciplinaId) {
                    $notasExistentes[$nota->aluno_id] = (float) $nota->valor;
                }
            }
        } else {
            $elegibilidade = $this->recursoService
                ->calcularElegibilidade($turma, $anoLectivo)
                ->keyBy('aluno_id');

            $recursos = Nota::where('turma_id', $turma->id)
                ->where('disciplina_id', $disciplinaId)
                ->where('tipo', Nota::TIPO_RECURSO)
                ->where('trimestre', 3)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->whereNull('archived_at')
                ->get();

            foreach ($recursos as $nota) {
                $notasExistentes[$nota->aluno_id] = (float) $nota->valor;
            }
        }

        $pauta = $this->pautaService->gerarPautaTrimestralCompleta($turma->id, 3, $anoLectivo);
        $metaDisc = $pauta['meta_disciplinas_transicao'][$disciplinaId] ?? [];

        if ($metaDisc === []) {
            $metaDisc = app(RecuperacaoTransicaoService::class)->metaParaTurma(
                (int) $turma->curso_id,
                [$disciplinaId],
                $classeNumero,
            )[$disciplinaId] ?? [];
        }

        $terminal = PautaResultadoTransicao::disciplinaTerminal($metaDisc, $classeNumero);
        $rotuloReferenciaColuna = $terminal
            ? ($classeNumero === 12 ? 'CFD' : 'CA/CFD')
            : 'CA';

        $permiteSemElegibilidade = NotaLancamentoRules::permiteLancamentoExameSemElegibilidade();

        $pautaPorAluno = collect($pauta['alunos'])->keyBy(fn (array $a) => $a['aluno']->id);

        foreach ($alunos as $aluno) {
            $itemElig = $elegibilidade->get($aluno->id);
            $elegivelFormal = NotaLancamentoRules::alunoElegivelExameDisciplina($itemElig, $disciplinaId);
            $jaLancada = isset($notasExistentes[$aluno->id]);

            $dadosDisc = $pautaPorAluno->get($aluno->id)['disciplinas'][$disciplinaId] ?? [];
            $notaOficial = PautaResultadoTransicao::notaOficialDisciplina(
                $dadosDisc,
                $metaDisc,
                $classeNumero,
            );

            if ($notaOficial === null) {
                $refEleg = collect($itemElig['disciplinas_elegiveis'] ?? [])
                    ->firstWhere('disciplina_id', $disciplinaId);
                if ($refEleg) {
                    $notaOficial = isset($refEleg['cfd'])
                        ? (float) $refEleg['cfd']
                        : (isset($refEleg['nota_referencia']) ? (float) $refEleg['nota_referencia'] : null);
                }
            }

            $rotulo = 'CA';
            if ($terminal && ($dadosDisc['cfd'] ?? null) !== null && ($dadosDisc['cfd'] ?? '') !== '') {
                $rotulo = 'CFD';
            }

            $motivo = null;
            if (! $elegivelFormal && ! $jaLancada && ! $permiteSemElegibilidade) {
                if ($itemElig && ($itemElig['elegivel'] ?? false)) {
                    $motivo = 'Aluno elegível noutras disciplinas, mas não nesta.';
                } else {
                    $motivo = $itemElig['motivo'] ?? 'Não elegível para este exame nesta disciplina.';
                }
            } elseif (! $elegivelFormal && ! $jaLancada && $permiteSemElegibilidade) {
                $motivo = $itemElig['motivo'] ?? 'Fora da elegibilidade formal (lançamento permitido pela escola).';
            }

            $podeLancar = NotaLancamentoRules::podeLancarExameAlunoDisciplina($itemElig, $disciplinaId, $jaLancada);

            $elegibilidadeExame[$aluno->id] = [
                'elegivel' => $elegivelFormal || $jaLancada,
                'elegivel_formal' => $elegivelFormal,
                'pode_lancar' => $podeLancar,
                'fora_elegibilidade' => ! $elegivelFormal && $podeLancar,
                'motivo' => $motivo,
                'referencia' => $notaOficial,
                'rotulo_referencia' => $rotulo,
            ];
        }

        return compact('notasExistentes', 'elegibilidadeExame', 'rotuloReferenciaColuna', 'permiteSemElegibilidade');
    }

    /**
     * @param  array{sucesso:int, removidas:int, bloqueadas:int, erros:array}  $resultado
     */
    private function mensagemResultadoProvaGlobal(array $resultado): string
    {
        $partes = [];
        if ($resultado['sucesso'] > 0) {
            $partes[] = "{$resultado['sucesso']} lançada(s)";
        }
        if ($resultado['removidas'] > 0) {
            $partes[] = "{$resultado['removidas']} removida(s)";
        }
        if ($resultado['bloqueadas'] > 0) {
            $partes[] = "{$resultado['bloqueadas']} bloqueada(s) (pós-conselho)";
        }

        $mensagem = $partes !== []
            ? 'Prova Global: ' . implode(', ', $partes) . '.'
            : 'Nenhuma alteração efectuada.';

        if (! empty($resultado['erros'])) {
            $errosMsg = collect($resultado['erros'])->map(fn ($e) => "{$e['aluno']}: {$e['erro']}")->implode('; ');
            $mensagem .= " Erros: {$errosMsg}";
        }

        return $mensagem;
    }

    /**
     * Disciplinas da turma na ordem oficial (tipo → ordem_apresentacao → nome).
     *
     * @return \Illuminate\Support\Collection<int, Disciplina>
     */
    private function disciplinasOrdenadasDaTurma(Turma $turma): \Illuminate\Support\Collection
    {
        return Disciplina::whereHas('cursos', fn ($q) => $q->where('cursos.id', $turma->curso_id))
            ->whereHas('classes', fn ($q) => $q->where('classes.id', $turma->classe_id))
            ->activo()
            ->ordenadaPorTipo()
            ->get();
    }
}
