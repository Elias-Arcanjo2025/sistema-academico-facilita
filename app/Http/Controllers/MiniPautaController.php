<?php

namespace App\Http\Controllers;

use App\Imports\MiniPautaImport;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Disciplina;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Services\NotaService;
use App\Services\PautaService;
use App\Services\Pedagogico\DisciplinaCoordEscopoService;
use App\Services\TurmaEscopoService;
use App\Support\NotaLancamentoRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Controller para Mini-Pautas (Professores)
 *
 * Professores veem APENAS as mini-pautas das suas disciplinas/turmas.
 * Acesso controlado por permissão: 'mini-pautas.ver'
 */
class MiniPautaController extends Controller
{
    public function __construct(
        protected PautaService $pautaService,
        protected TurmaEscopoService $turmaEscopo,
        protected NotaService $notaService,
    ) {
        // Apenas usuários com permissão mini-pautas.ver
        $this->middleware('can:mini-pautas.ver');
    }

    /**
     * Página inicial de mini-pautas (professores)
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $turmaId = $request->input('turma_id');
        $disciplinaId = $request->input('disciplina_id');
        $trimestre = $request->input('trimestre');
        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo)
            ?? Configuracao::getAnoLectivoActualId();

        // União: professor + director + coordenador de curso + coordenador de disciplina
        $turmaIds = $this->turmaEscopo->turmaIdsConsultaveis($user, $anoLectivoId);

        $turmas = $turmaIds->isEmpty()
            ? collect()
            : Turma::activo()
                ->where('ano_lectivo_id', $anoLectivoId)
                ->whereIn('id', $turmaIds)
                ->with('curso')
                ->orderBy('nome')
                ->get();

        // Obter disciplinas da turma selecionada
        if ($turmaId) {
            if ($user->temVisaoInstitucional() || $user->hasAnyRole(['coordenador_curso', 'director_turma'])) {
                $turma = Turma::find($turmaId);
                $disciplinas = $turma && $turma->curso_id && $turma->classe_id
                    ? Disciplina::whereHas('cursos', function ($query) use ($turma) {
                            $query->where('cursos.id', $turma->curso_id);
                        })
                        ->whereHas('classes', function ($query) use ($turma) {
                            $query->where('classes.id', $turma->classe_id);
                        })
                        ->activo()
                        ->orderBy('nome')
                        ->get()
                    : collect();
            } elseif ($user->hasRole('coordenador_disciplina')) {
                $coordIds = app(DisciplinaCoordEscopoService::class)
                    ->disciplinaIds($user, $anoLectivoId);
                $doProfessor = ProfessorDisciplinaTurma::where('user_id', $user->id)
                    ->where('turma_id', $turmaId)
                    ->where('activo', true)
                    ->with('disciplina')
                    ->get()
                    ->pluck('disciplina')
                    ->filter(fn ($d) => $d !== null && $d->activo);
                $turma = Turma::find($turmaId);
                $doCoord = $turma && $turma->curso_id && $turma->classe_id && $coordIds->isNotEmpty()
                    ? Disciplina::whereHas('cursos', fn ($q) => $q->where('cursos.id', $turma->curso_id))
                        ->whereHas('classes', fn ($q) => $q->where('classes.id', $turma->classe_id))
                        ->activo()
                        ->whereIn('id', $coordIds)
                        ->orderBy('nome')
                        ->get()
                    : collect();
                $disciplinas = $doCoord->merge($doProfessor)->unique('id')->sortBy('nome')->values();
            } else {
                $disciplinas = ProfessorDisciplinaTurma::where('user_id', $user->id)
                    ->where('turma_id', $turmaId)
                    ->where('activo', true)
                    ->with('disciplina')
                    ->get()
                    ->pluck('disciplina')
                    ->filter(fn ($d) => $d !== null && $d->activo)
                    ->unique('id')
                    ->values();
            }
        } else {
            $disciplinas = collect();
        }

        $miniPauta = null;
        $estatisticas = null;
        $turma = null;
        $disciplina = null;
        $colunas = [];
        $chaveResultado = 'mt';
        $miniPautasAnuais = null;
        $ehAnual = $trimestre === 'anual';

        if ($turmaId && $disciplinaId && ($ehAnual || $trimestre)) {
            // Verificar se o professor tem acesso a esta turma/disciplina
            if (!$this->verificarAcessoProfessor($turmaId, $disciplinaId)) {
                abort(403, 'Não tem autorização para aceder a esta mini-pauta.');
            }

            $turma = Turma::with(['curso', 'classe'])->find($turmaId);
            $disciplina = Disciplina::find($disciplinaId);

            if ($ehAnual) {
                $miniPautasAnuais = [];
                foreach ([1, 2, 3] as $t) {
                    $bloco = $this->pautaService->gerarMiniPauta(
                        (int) $turmaId,
                        (int) $disciplinaId,
                        $t,
                        $anoLectivo
                    );
                    $meta = $this->pautaService->metaMiniPauta((int) $turmaId, $t);
                    $miniPautasAnuais[$t] = [
                        'miniPauta' => $bloco,
                        'estatisticas' => $this->pautaService->getEstatisticasMiniPauta($bloco),
                        'colunas' => $meta['colunas'],
                        'chaveResultado' => $meta['chave_resultado'],
                    ];
                }
            } else {
                $miniPauta = $this->pautaService->gerarMiniPauta(
                    $turmaId,
                    $disciplinaId,
                    (int) $trimestre,
                    $anoLectivo
                );

                $estatisticas = $this->pautaService->getEstatisticasMiniPauta($miniPauta);
                $meta = $this->pautaService->metaMiniPauta($turmaId, (int) $trimestre);
                $colunas = $meta['colunas'];
                $chaveResultado = $meta['chave_resultado'];
            }
        }

        // Buscar anos lectivos
        $anosLectivos = \App\Models\AnoLectivo::orderBy('nome', 'desc')->pluck('nome', 'id');

        if ($anosLectivos->isEmpty()) {
            $anosLectivos = collect([Configuracao::getAnoLectivoActual() => Configuracao::getAnoLectivoActual()]);
        }

        return view('mini-pautas.index', compact(
            'turmas',
            'disciplinas',
            'turmaId',
            'disciplinaId',
            'trimestre',
            'anoLectivo',
            'anosLectivos',
            'miniPauta',
            'estatisticas',
            'turma',
            'disciplina',
            'colunas',
            'chaveResultado',
            'ehAnual',
            'miniPautasAnuais'
        ));
    }

    /**
     * Visualizar mini-pauta trimestral ou anual
     */
    public function show(Request $request): View
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
            'trimestre' => ['required', 'regex:/^(1|2|3|anual)$/'],
            'ano_lectivo' => 'nullable|string|max:9',
        ]);

        $turmaId = $request->input('turma_id');
        $disciplinaId = $request->input('disciplina_id');
        $trimestre = $request->input('trimestre');
        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());
        $ehAnual = $trimestre === 'anual';

        // Verificar acesso
        if (!$this->verificarAcessoProfessor($turmaId, $disciplinaId)) {
            abort(403, 'Não tem autorização para aceder a esta mini-pauta.');
        }

        $turma = Turma::with(['curso', 'classe'])->findOrFail($turmaId);
        $disciplina = Disciplina::findOrFail($disciplinaId);

        if ($ehAnual) {
            $miniPautasAnuais = [];
            foreach ([1, 2, 3] as $t) {
                $bloco = $this->pautaService->gerarMiniPauta(
                    (int) $turmaId,
                    (int) $disciplinaId,
                    $t,
                    $anoLectivo
                );
                $meta = $this->pautaService->metaMiniPauta((int) $turmaId, $t);
                $miniPautasAnuais[$t] = [
                    'miniPauta' => $bloco,
                    'estatisticas' => $this->pautaService->getEstatisticasMiniPauta($bloco),
                    'colunas' => $meta['colunas'],
                    'chaveResultado' => $meta['chave_resultado'],
                ];
            }

            return view('mini-pautas.show-anual', compact(
                'turma',
                'disciplina',
                'anoLectivo',
                'miniPautasAnuais'
            ));
        }

        $miniPauta = $this->pautaService->gerarMiniPauta(
            $turmaId,
            $disciplinaId,
            (int) $trimestre,
            $anoLectivo
        );

        $estatisticas = $this->pautaService->getEstatisticasMiniPauta($miniPauta);
        $meta = $this->pautaService->metaMiniPauta($turmaId, (int) $trimestre);
        $colunas = $meta['colunas'];
        $chaveResultado = $meta['chave_resultado'];

        return view('mini-pautas.show', compact(
            'turma',
            'disciplina',
            'trimestre',
            'anoLectivo',
            'miniPauta',
            'estatisticas',
            'colunas',
            'chaveResultado'
        ));
    }

    /**
     * Formulário de importação de mini-pauta via Excel.
     */
    public function importacaoForm(): View
    {
        $user = auth()->user();

        if (! $user->can('notas.lancar')) {
            abort(403, 'Não tem permissão para importar notas.');
        }

        $trimestreActual = Configuracao::getTrimestreActual();
        $anoLectivo = Configuracao::getAnoLectivoActual();

        $trimestresStatus = [];
        for ($t = 1; $t <= 3; $t++) {
            $trimestresStatus[$t] = [
                'aberto' => Configuracao::trimestreAbertoParaLancamento($t),
                'actual' => $t === $trimestreActual,
            ];
        }

        $turmas = $this->turmasDisponiveis($user);

        return view('mini-pautas.importacao', compact(
            'turmas',
            'trimestreActual',
            'trimestresStatus',
            'anoLectivo',
        ));
    }

    /**
     * Importar notas a partir do Excel oficial da mini-pauta.
     */
    public function importacaoStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'arquivo' => 'required|file|mimes:xlsx,xls|max:10240',
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
            'trimestre' => 'required|integer|min:1|max:3',
            'ano_lectivo' => 'nullable|string|max:9',
        ]);

        $user = auth()->user();
        $turmaId = (int) $validated['turma_id'];
        $disciplinaId = (int) $validated['disciplina_id'];
        $trimestre = (int) $validated['trimestre'];

        if (! $this->verificarAcessoProfessor($turmaId, $disciplinaId)) {
            return back()->with('error', 'Não tem autorização para importar notas desta mini-pauta.');
        }

        if (! NotaLancamentoRules::utilizadorPodeLancarNaTurmaDisciplina($user, $turmaId, $disciplinaId)) {
            return back()->with('error', 'Não tem permissão para lançar notas nesta disciplina/turma.');
        }

        if (! $user->podeIgnorarTrimestreFechado()) {
            if (! Configuracao::trimestreAbertoParaLancamento($trimestre)) {
                return back()->with('error', 'O ' . $trimestre . '.º trimestre não está aberto para importação de notas.');
            }
        }

        $turma = Turma::with('curso')->findOrFail($turmaId);
        $anoLectivoId = (int) ($turma->ano_lectivo_id ?: Configuracao::getAnoLectivoActualId());

        if (Configuracao::isModoConsultaById($anoLectivoId, $user)) {
            return back()->with('error', 'Ano lectivo anterior — importação de notas desactivada (modo consulta).');
        }

        $meta = $this->pautaService->metaMiniPauta($turmaId, $trimestre);
        $regimeId = $turma->curso?->regime()?->id() ?? '';

        DB::beginTransaction();

        try {
            $import = new MiniPautaImport(
                $turmaId,
                $disciplinaId,
                $trimestre,
                $anoLectivoId,
                $user,
                $this->notaService,
                $meta['colunas'],
                $regimeId,
            );

            $import->processar($request->file('arquivo')->getRealPath());

            if ($import->criadas === 0 && $import->atualizadas === 0 && count($import->erros) > 0) {
                DB::rollBack();

                return back()
                    ->with('error', 'Não foi possível importar o ficheiro. Verifique os erros abaixo.')
                    ->with('erros_importacao', $import->erros);
            }

            $anoLectivoNome = AnoLectivo::getNomeById($anoLectivoId);
            $resultadoMt = $this->pautaService->calcularMediasTrimestraisTurma(
                $turmaId,
                $disciplinaId,
                $trimestre,
                $user->id,
                $anoLectivoNome,
                true,
            );

            activity()
                ->causedBy($user)
                ->withProperties([
                    'turma_id' => $turmaId,
                    'disciplina_id' => $disciplinaId,
                    'trimestre' => $trimestre,
                    'criadas' => $import->criadas,
                    'atualizadas' => $import->atualizadas,
                    'bloqueadas' => $import->bloqueadas,
                    'mts_calculadas' => $resultadoMt['calculadas'] ?? 0,
                    'erros' => count($import->erros),
                ])
                ->log('Importação de mini-pauta via Excel');

            DB::commit();

            $partes = [];
            if ($import->criadas > 0) {
                $partes[] = "{$import->criadas} nota(s) criada(s)";
            }
            if ($import->atualizadas > 0) {
                $partes[] = "{$import->atualizadas} nota(s) actualizada(s)";
            }
            if (($resultadoMt['calculadas'] ?? 0) > 0) {
                $partes[] = "{$resultadoMt['calculadas']} média(s) trimestral(is) recalculada(s)";
            }
            if ($import->bloqueadas > 0) {
                $partes[] = "{$import->bloqueadas} nota(s) bloqueada(s) (pós-conselho)";
            }
            if (count($import->erros) > 0) {
                $partes[] = count($import->erros) . ' aviso(s)';
            }

            $mensagem = $partes !== []
                ? 'Importação concluída: ' . implode(', ', $partes) . '.'
                : 'Importação concluída. Nenhuma nota nova foi gravada.';

            if (count($import->erros) > 0) {
                return back()
                    ->with('warning', $mensagem)
                    ->with('erros_importacao', $import->erros);
            }

            return back()->with('success', $mensagem);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erro na importação de mini-pauta: ' . $e->getMessage());

            return back()->with('error', 'Erro ao importar ficheiro: ' . $e->getMessage());
        }
    }

    /**
     * Calcular médias trimestrais
     */
    public function calcularMediasTrimestre(Request $request): RedirectResponse
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
            'trimestre' => 'required|integer|min:1|max:3',
        ]);

        $user = auth()->user();
        $turmaId = $request->input('turma_id');
        $disciplinaId = $request->input('disciplina_id');
        $trimestre = $request->input('trimestre');

        // Verificar acesso
        if (!$this->verificarAcessoProfessor($turmaId, $disciplinaId)) {
            return back()->with('error', 'Não tem autorização para calcular médias desta disciplina.');
        }

        $resultado = $this->pautaService->calcularMediasTrimestraisTurma(
            $turmaId,
            $disciplinaId,
            $trimestre,
            $user->id,
            null,
            true  // Recalcular finais automaticamente
        );

        $mensagem = "Médias trimestrais: ";
        if ($resultado['criadas'] > 0) {
            $mensagem .= "{$resultado['criadas']} criadas";
        }
        if ($resultado['atualizadas'] > 0) {
            if ($resultado['criadas'] > 0) {
                $mensagem .= ", ";
            }
            $mensagem .= "{$resultado['atualizadas']} atualizadas";
        }
        $mensagem .= ". ";

        if ($resultado['pendentes'] > 0) {
            $mensagem .= "{$resultado['pendentes']} aluno(s) sem todas as notas. ";
        }
        if (isset($resultado['finais_recalculadas'])) {
            $mensagem .= "Notas finais recalculadas: {$resultado['finais_recalculadas']} aluno(s).";
        }

        return back()->with('success', $mensagem);
    }

    /**
     * Calcular notas finais
     */
    public function calcularNotasFinais(Request $request): RedirectResponse
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
        ]);

        $user = auth()->user();
        $turmaId = $request->input('turma_id');
        $disciplinaId = $request->input('disciplina_id');

        // Verificar acesso
        if (!$this->verificarAcessoProfessor($turmaId, $disciplinaId)) {
            return back()->with('error', 'Não tem autorização para calcular notas finais desta disciplina.');
        }

        $resultado = $this->pautaService->calcularNotasFinaisTurma(
            $turmaId,
            $disciplinaId,
            $user->id
        );

        $mensagem = "Notas finais calculadas: {$resultado['calculadas']} aluno(s). ";
        if ($resultado['pendentes'] > 0) {
            $mensagem .= "{$resultado['pendentes']} aluno(s) ainda não tem todas as médias trimestrais.";
        }

        return back()->with('success', $mensagem);
    }

    /**
     * Calcular MFT2 (Média Final dos 2 Trimestres)
     */
    public function calcularMFT2(Request $request): RedirectResponse
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
        ]);

        $user = auth()->user();
        $turmaId = $request->input('turma_id');
        $disciplinaId = $request->input('disciplina_id');

        // Verificar acesso
        if (!$this->verificarAcessoProfessor($turmaId, $disciplinaId)) {
            return back()->with('error', 'Não tem autorização para calcular MFT2 desta disciplina.');
        }

        $turma = Turma::with('alunos')->findOrFail($turmaId);
        $criadas = 0;
        $atualizadas = 0;
        $pendentes = 0;

        foreach ($turma->alunos as $aluno) {
            $notaMFT2 = $this->pautaService->calcularEGuardarMFT2(
                $aluno->id,
                $disciplinaId,
                $turmaId,
                $user->id
            );

            if ($notaMFT2) {
                if ($notaMFT2->wasRecentlyCreated) {
                    $criadas++;
                } else {
                    $atualizadas++;
                }
            } else {
                $pendentes++;
            }
        }

        $mensagem = "MFT2 calculadas: ";
        if ($criadas > 0) {
            $mensagem .= "{$criadas} criadas";
        }
        if ($atualizadas > 0) {
            if ($criadas > 0) {
                $mensagem .= ", ";
            }
            $mensagem .= "{$atualizadas} atualizadas";
        }
        $mensagem .= ". ";

        if ($pendentes > 0) {
            $mensagem .= "{$pendentes} aluno(s) sem MT1 ou MT2.";
        }

        return back()->with('success', $mensagem);
    }

    /**
     * Calcular CF (Classificação Final)
     */
    public function calcularCF(Request $request): RedirectResponse
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
        ]);

        $user = auth()->user();
        $turmaId = $request->input('turma_id');
        $disciplinaId = $request->input('disciplina_id');

        // Verificar acesso
        if (!$this->verificarAcessoProfessor($turmaId, $disciplinaId)) {
            return back()->with('error', 'Não tem autorização para calcular CF desta disciplina.');
        }

        $turma = Turma::with('alunos')->findOrFail($turmaId);
        $criadas = 0;
        $atualizadas = 0;
        $pendentes = 0;

        foreach ($turma->alunos as $aluno) {
            $notaCF = $this->pautaService->calcularEGuardarCF(
                $aluno->id,
                $disciplinaId,
                $turmaId,
                $user->id
            );

            if ($notaCF) {
                if ($notaCF->wasRecentlyCreated) {
                    $criadas++;
                } else {
                    $atualizadas++;
                }
            } else {
                $pendentes++;
            }
        }

        $mensagem = "CF calculadas: ";
        if ($criadas > 0) {
            $mensagem .= "{$criadas} criadas";
        }
        if ($atualizadas > 0) {
            if ($criadas > 0) {
                $mensagem .= ", ";
            }
            $mensagem .= "{$atualizadas} atualizadas";
        }
        $mensagem .= ". ";

        if ($pendentes > 0) {
            $mensagem .= "{$pendentes} aluno(s) sem MFT2 ou MT3.";
        }

        return back()->with('success', $mensagem);
    }

    /**
     * Calcular CA (Classificação Anual)
     */
    public function calcularCA(Request $request): RedirectResponse
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
        ]);

        $user = auth()->user();
        $turmaId = $request->input('turma_id');
        $disciplinaId = $request->input('disciplina_id');

        // Verificar acesso
        if (!$this->verificarAcessoProfessor($turmaId, $disciplinaId)) {
            return back()->with('error', 'Não tem autorização para calcular CA desta disciplina.');
        }

        $turma = Turma::with('alunos', 'classe')->findOrFail($turmaId);
        $classe = $turma->classe->codigo; // 10, 11 ou 12

        $criadas = 0;
        $atualizadas = 0;
        $pendentes = 0;

        foreach ($turma->alunos as $aluno) {
            $notaCA = $this->pautaService->calcularEGuardarCA(
                $aluno->id,
                $disciplinaId,
                $turmaId,
                $classe,
                $user->id
            );

            if ($notaCA) {
                if ($notaCA->wasRecentlyCreated) {
                    $criadas++;
                } else {
                    $atualizadas++;
                }
            } else {
                $pendentes++;
            }
        }

        $mensagem = "CA{$classe} calculadas: ";
        if ($criadas > 0) {
            $mensagem .= "{$criadas} criadas";
        }
        if ($atualizadas > 0) {
            if ($criadas > 0) {
                $mensagem .= ", ";
            }
            $mensagem .= "{$atualizadas} atualizadas";
        }
        $mensagem .= ". ";

        if ($pendentes > 0) {
            $mensagem .= "{$pendentes} aluno(s) sem CF ou PG.";
        }

        return back()->with('success', $mensagem);
    }

    /**
     * Calcular CFD (Classificação Final da Disciplina)
     */
    public function calcularCFD(Request $request): RedirectResponse
    {
        $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
        ]);

        $user = auth()->user();
        $turmaId = $request->input('turma_id');
        $disciplinaId = $request->input('disciplina_id');

        // Verificar acesso
        if (!$this->verificarAcessoProfessor($turmaId, $disciplinaId)) {
            return back()->with('error', 'Não tem autorização para calcular CFD desta disciplina.');
        }

        $turma = Turma::with('alunos')->findOrFail($turmaId);
        $criadas = 0;
        $atualizadas = 0;
        $pendentes = 0;
        $naoTerminal = 0;

        foreach ($turma->alunos as $aluno) {
            try {
                $notaCFD = $this->pautaService->calcularEGuardarCFD(
                    $aluno->id,
                    $disciplinaId,
                    $turmaId,
                    $user->id
                );

                if ($notaCFD) {
                    if ($notaCFD->wasRecentlyCreated) {
                        $criadas++;
                    } else {
                        $atualizadas++;
                    }
                } else {
                    $naoTerminal++;
                }
            } catch (\Exception $e) {
                $pendentes++;
            }
        }

        $mensagem = "CFD calculadas: ";
        if ($criadas > 0) {
            $mensagem .= "{$criadas} criadas";
        }
        if ($atualizadas > 0) {
            if ($criadas > 0) {
                $mensagem .= ", ";
            }
            $mensagem .= "{$atualizadas} atualizadas";
        }
        $mensagem .= ". ";

        if ($naoTerminal > 0) {
            $mensagem .= "{$naoTerminal} aluno(s) sem CA necessárias ou disciplina não terminal nesta classe.";
        }

        return back()->with('success', $mensagem);
    }

    /**
     * API: Obter disciplinas para uma turma específica
     */
    public function getDisciplinas(Request $request)
    {
        $turmaId = $request->input('turma_id');
        $user = auth()->user();

        if (!$turmaId) {
            return response()->json([]);
        }

        // Administrativos e Subdiretor Pedagógico veem todas as disciplinas da turma
        if ($user->temVisaoInstitucional() || $user->hasAnyRole(['coordenador_curso', 'director_turma'])) {
            $turma = Turma::with('curso')->find($turmaId);
            if (!$turma || !$turma->curso || !$turma->classe_id) {
                return response()->json([]);
            }

            $disciplinas = Disciplina::whereHas('cursos', function ($query) use ($turma) {
                    $query->where('cursos.id', $turma->curso_id);
                })
                ->whereHas('classes', function ($query) use ($turma) {
                    $query->where('classes.id', $turma->classe_id);
                })
                ->where('activo', true)
                ->orderBy('nome')
                ->get(['id', 'nome', 'codigo']);
        } else {
            // Professor vê apenas suas disciplinas nesta turma
            $associacoes = ProfessorDisciplinaTurma::where('user_id', $user->id)
                ->where('turma_id', $turmaId)
                ->where('activo', true)
                ->with('disciplina')
                ->get();

            $disciplinas = $associacoes->pluck('disciplina')->filter(fn($d) => $d && $d->activo);
        }

        return response()->json($disciplinas->map(fn($d) => [
            'id' => $d->id,
            'nome' => $d->nome,
        ])->values());
    }

    /**
     * Turmas acessíveis ao utilizador (mini-pautas e importação).
     */
    private function turmasDisponiveis($user)
    {
        if ($user->temVisaoInstitucional()) {
            return Turma::activo()->with('curso')->orderBy('nome')->get();
        }

        $turmaIds = collect();

        $associacoes = ProfessorDisciplinaTurma::where('user_id', $user->id)
            ->where('activo', true)
            ->with('turma')
            ->get();

        $turmaIds = $turmaIds->merge(
            $associacoes->pluck('turma')->filter(fn ($t) => $t !== null && $t->activo)->pluck('id')
        );

        if ($user->hasRole('director_turma')) {
            $turmasComoDirector = $user->turmasComoDirector()
                ->where('director_turma.activo', true)
                ->where('turmas.activo', true)
                ->pluck('turmas.id');
            $turmaIds = $turmaIds->merge($turmasComoDirector);
        }

        $turmaIds = $turmaIds->unique()->values();

        if ($turmaIds->isNotEmpty()) {
            return Turma::activo()
                ->whereIn('id', $turmaIds)
                ->with('curso')
                ->orderBy('nome')
                ->get();
        }

        if ($user->hasRole('coordenador_curso')) {
            $cursosIds = $user->coordenadorCursos()->where('activo', true)->pluck('curso_id');

            return Turma::activo()
                ->whereIn('curso_id', $cursosIds)
                ->with('curso')
                ->orderBy('nome')
                ->get();
        }

        return collect();
    }

    /**
     * Verificar se o usuário tem acesso à turma/disciplina.
     * A verificação de associação de professor tem prioridade sobre qualquer role.
     */
    private function verificarAcessoProfessor(int $turmaId, ?int $disciplinaId = null): bool
    {
        return $this->turmaEscopo->podeAcederTurma(auth()->user(), $turmaId, $disciplinaId);
    }
}
