<?php

namespace App\Http\Controllers;

use App\Http\Requests\PautaArquivoRequest;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\PautaArquivo;
use App\Models\Turma;
use App\Models\User;
use App\Services\PautaArquivoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PautaArquivoController extends Controller
{
    public function __construct(
        protected PautaArquivoService $pautaArquivoService,
    ) {}

    /**
     * Listagem de arquivos de pauta com filtros
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PautaArquivo::class);

        $user = auth()->user();

        // Query base
        $query = PautaArquivo::with(['curso', 'turma.curso', 'disciplina', 'uploadedBy', 'sala'])
            ->activo();

        // Aplicar filtros
        if ($request->filled('curso_id')) {
            $query->where('curso_id', $request->curso_id);
        }

        if ($request->filled('turma_id')) {
            $query->where('turma_id', $request->turma_id);
        }

        // Capturar ano lectivo filtrado para usar nas permissões
        $anoLectivoFiltroId = null;
        if ($request->filled('ano_lectivo')) {
            $anoLectivoFiltroId = \App\Models\AnoLectivo::getIdByNome($request->ano_lectivo);
            $query->where('ano_lectivo', $request->ano_lectivo);
        }

        if ($request->filled('tipo_pauta')) {
            $query->where('tipo_pauta', $request->tipo_pauta);
        }

        if ($request->filled('trimestre')) {
            $query->where('trimestre', $request->trimestre);
        }

        if ($request->filled('origem')) {
            $query->where('origem', $request->origem);
        }

        // Restrições por permissão
        if ($user->hasAnyRole(['admin_full', 'subdiretor_pedagogico'])) {
            // Admin e Subdiretor veem todos
        } elseif ($user->hasRole('director_turma')) {
            $turmasIds = $user->turmasComoDirector()
                ->where('director_turma.activo', true)
                ->pluck('turmas.id');
            $query->where(function ($q) use ($turmasIds) {
                $q->whereIn('turma_id', $turmasIds)
                  ->orWhereNull('turma_id');
            });
        } elseif ($user->hasRole('coordenador_curso')) {
            // Se filtrou por ano, usar; senão, pegar ano atual
            $anoParaPermissao = $anoLectivoFiltroId ?? \App\Models\Configuracao::getAnoLectivoActualId();

            $cursosIds = $user->cursosComoCoordenador()
                ->where('coordenador_curso.activo', true)
                ->where('coordenador_curso.ano_lectivo_id', $anoParaPermissao)
                ->pluck('cursos.id');

            $query->whereIn('curso_id', $cursosIds);
        } else {
            // Professor: turmas onde leciona
            $cursosIds = $user->turmasComoProfessor()
                ->where('professor_disciplina_turma.activo', true)
                ->pluck('turmas.curso_id')
                ->unique();
            $query->whereIn('curso_id', $cursosIds);
        }

        $estatisticas = [
            'total' => (clone $query)->count(),
            'sistema' => (clone $query)->where('origem', PautaArquivo::ORIGEM_SISTEMA)->count(),
            'externa' => (clone $query)->where('origem', PautaArquivo::ORIGEM_EXTERNA)->count(),
        ];

        $arquivos = $query->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        // Dados para filtros
        $cursos = $this->getCursosDisponiveis($user);
        $turmas = $request->filled('curso_id')
            ? $this->getTurmasPorCurso($request->curso_id, $user)
            : collect();
        $anosLectivos = Configuracao::getAnosLectivosDisponiveis();

        return view('pauta-arquivos.index', compact(
            'arquivos',
            'cursos',
            'turmas',
            'anosLectivos',
            'estatisticas',
        ));
    }

    /**
     * Formulário de upload
     */
    public function create(): View
    {
        $this->authorize('create', PautaArquivo::class);

        $user = auth()->user();
        $cursos = $this->getCursosDisponiveis($user);
        $anosLectivos = Configuracao::getAnosLectivosDisponiveis();
        $anoLectivoActual = Configuracao::getAnoLectivoActual();
        $trimestreActual = Configuracao::getTrimestreActual();

        return view('pauta-arquivos.create', compact(
            'cursos',
            'anosLectivos',
            'anoLectivoActual',
            'trimestreActual',
        ));
    }

    /**
     * Processar upload
     */
    public function store(PautaArquivoRequest $request): RedirectResponse
    {
        $this->authorize('create', PautaArquivo::class);

        DB::beginTransaction();
        try {
            $pautaArquivo = $this->pautaArquivoService->persistirUpload(
                $request->file('arquivo'),
                $request->validated(),
                auth()->user(),
            );

            DB::commit();

            activity()
                ->performedOn($pautaArquivo)
                ->causedBy(auth()->user())
                ->withProperties([
                    'nome_ficheiro' => $pautaArquivo->nome_original,
                    'tamanho' => $pautaArquivo->tamanho_formatado,
                    'versao' => $pautaArquivo->versao,
                ])
                ->log('Upload de ficheiro de pauta');

            return redirect()
                ->route('pauta-arquivos.show', $pautaArquivo)
                ->with('success', 'Ficheiro de pauta enviado com sucesso.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', 'Erro ao enviar ficheiro: ' . $e->getMessage());
        }
    }

    /**
     * Gerar pauta oficial do sistema e arquivar automaticamente.
     */
    public function arquivarDoSistema(Request $request): RedirectResponse
    {
        $this->authorize('create', PautaArquivo::class);

        $validated = $request->validate([
            'turma_id' => 'required|exists:turmas,id',
            'ano_lectivo' => 'required|string|regex:/^\d{4}-\d{4}$/',
            'trimestre' => 'required|integer|in:0,1,2,3',
            'tipo_pauta' => 'required|in:' . implode(',', array_keys(PautaArquivo::getTiposPauta())),
            'disciplina_id' => 'nullable|exists:disciplinas,id',
        ]);

        $tipo = $validated['tipo_pauta'];
        if (in_array($tipo, [PautaArquivo::TIPO_MINI_PAUTA, PautaArquivo::TIPO_FINAL_DISCIPLINA], true)
            && empty($validated['disciplina_id'])) {
            return back()->withInput()->with('error', 'Seleccione a disciplina para este tipo de pauta.');
        }

        if ($tipo === PautaArquivo::TIPO_COMPLETA && (int) $validated['trimestre'] < 1) {
            return back()->withInput()->with('error', 'Seleccione o trimestre (1.º, 2.º ou 3.º) para a pauta completa.');
        }

        $turma = Turma::findOrFail($validated['turma_id']);

        DB::beginTransaction();
        try {
            $pautaArquivo = $this->pautaArquivoService->arquivarDoSistema(
                $tipo,
                (int) $validated['turma_id'],
                $validated['ano_lectivo'],
                (int) $validated['trimestre'],
                $validated['disciplina_id'] ?? null,
                auth()->user(),
            );

            DB::commit();

            activity()
                ->performedOn($pautaArquivo)
                ->causedBy(auth()->user())
                ->withProperties([
                    'tipo_pauta' => $tipo,
                    'turma_id' => $turma->id,
                    'versao' => $pautaArquivo->versao,
                ])
                ->log('Pauta gerada e arquivada pelo sistema');

            return redirect()
                ->route('pauta-arquivos.show', $pautaArquivo)
                ->with('success', 'Pauta gerada e arquivada com sucesso (versão ' . $pautaArquivo->versao_formatada . ').');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', 'Erro ao gerar pauta: ' . $e->getMessage());
        }
    }

    /**
     * Visualizar detalhes do arquivo
     */
    public function show(PautaArquivo $pautaArquivo): View
    {
        $this->authorize('view', $pautaArquivo);

        $pautaArquivo->load([
            'curso',
            'turma.curso',
            'turma.classe',
            'sala',
            'disciplina',
            'uploadedBy'
        ]);

        // Buscar todas as versões
        $versoes = $pautaArquivo->getTodasVersoes();

        return view('pauta-arquivos.show', compact('pautaArquivo', 'versoes'));
    }

    /**
     * Formulário de edição
     */
    public function edit(PautaArquivo $pautaArquivo): View
    {
        $this->authorize('update', $pautaArquivo);

        return view('pauta-arquivos.edit', compact('pautaArquivo'));
    }

    /**
     * Atualizar metadata (não o arquivo)
     */
    public function update(PautaArquivoRequest $request, PautaArquivo $pautaArquivo): RedirectResponse
    {
        $this->authorize('update', $pautaArquivo);

        $pautaArquivo->update([
            'tipo_pauta' => $request->tipo_pauta,
            'descricao' => $request->descricao,
            'activo' => $request->boolean('activo', true),
        ]);

        activity()
            ->performedOn($pautaArquivo)
            ->causedBy(auth()->user())
            ->log('Metadados de ficheiro de pauta actualizados');

        return redirect()
            ->route('pauta-arquivos.show', $pautaArquivo)
            ->with('success', 'Ficheiro actualizado com sucesso.');
    }

    /**
     * Download do arquivo
     */
    public function download(PautaArquivo $pautaArquivo): StreamedResponse
    {
        $this->authorize('download', $pautaArquivo);

        if (!$pautaArquivo->existe) {
            abort(404, 'Ficheiro não encontrado no armazenamento.');
        }

        // Registrar auditoria
        activity()
            ->performedOn($pautaArquivo)
            ->causedBy(auth()->user())
            ->log('Download de ficheiro de pauta');

        return Storage::disk('local')->download(
            $pautaArquivo->caminho_arquivo,
            $pautaArquivo->nome_original
        );
    }

    /**
     * Soft delete (desativa)
     */
    public function destroy(PautaArquivo $pautaArquivo): RedirectResponse
    {
        $this->authorize('delete', $pautaArquivo);

        // Soft delete: apenas desativa
        $pautaArquivo->update(['activo' => false]);

        activity()
            ->performedOn($pautaArquivo)
            ->causedBy(auth()->user())
            ->log('Ficheiro de pauta desactivado');

        return redirect()
            ->route('pauta-arquivos.index')
            ->with('success', 'Ficheiro desactivado com sucesso.');
    }

    /**
     * Hard delete (remove fisicamente)
     */
    public function forceDelete(PautaArquivo $pautaArquivo): RedirectResponse
    {
        $this->authorize('forceDelete', $pautaArquivo);

        // Deletar arquivo físico
        if ($pautaArquivo->existe) {
            Storage::disk('local')->delete($pautaArquivo->caminho_arquivo);
        }

        activity()
            ->performedOn($pautaArquivo)
            ->causedBy(auth()->user())
            ->log('Ficheiro de pauta removido permanentemente');

        $pautaArquivo->delete();

        return redirect()
            ->route('pauta-arquivos.index')
            ->with('success', 'Ficheiro removido permanentemente.');
    }

    // ==================== API ENDPOINTS ====================

    /**
     * API: Obter disciplinas de uma turma
     */
    public function getDisciplinas(Request $request)
    {
        $turmaId = $request->input('turma_id');

        if (!$turmaId) {
            return response()->json([]);
        }

        $turma = Turma::with('curso', 'classe')->find($turmaId);

        if (!$turma || !$turma->curso_id || !$turma->classe_id) {
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

        return response()->json($disciplinas->map(fn($d) => [
            'id' => $d->id,
            'nome' => $d->nome,
        ])->values());
    }

    /**
     * API: Obter turmas de um curso
     */
    public function getTurmas(Request $request)
    {
        $cursoId = $request->input('curso_id');

        if (!$cursoId) {
            return response()->json([]);
        }

        $turmas = $this->getTurmasPorCurso((int) $cursoId, auth()->user());

        return response()->json($turmas->map(fn ($t) => [
            'id' => $t->id,
            'nome' => $t->nome,
            'curso_id' => $t->curso_id,
        ])->values());
    }

    // ==================== MÉTODOS AUXILIARES PRIVADOS ====================

    /**
     * Retorna cursos disponíveis conforme permissões do utilizador
     */
    private function getCursosDisponiveis(User $user)
    {
        if ($user->hasAnyRole(['admin_full', 'subdiretor_pedagogico'])) {
            return Curso::where('activo', true)->orderBy('nome')->get();
        }

        if ($user->hasRole('coordenador_curso')) {
            // Pegar apenas cursos do ano lectivo atual
            $anoLectivoAtualId = \App\Models\Configuracao::getAnoLectivoActualId();

            return $user->cursosComoCoordenador()
                ->where('coordenador_curso.activo', true)
                ->where('coordenador_curso.ano_lectivo_id', $anoLectivoAtualId)
                ->orderBy('cursos.nome')
                ->get();
        }

        if ($user->hasRole('director_turma')) {
            $cursosIds = $user->turmasComoDirector()
                ->where('director_turma.activo', true)
                ->pluck('turmas.curso_id')
                ->unique();

            return Curso::whereIn('id', $cursosIds)
                ->where('activo', true)
                ->orderBy('nome')
                ->get();
        }

        // Professor: cursos das turmas onde leciona
        $cursosIds = $user->turmasComoProfessor()
            ->where('professor_disciplina_turma.activo', true)
            ->pluck('turmas.curso_id')
            ->unique();

        return Curso::whereIn('id', $cursosIds)
            ->where('activo', true)
            ->orderBy('nome')
            ->get();
    }

    /**
     * Retorna turmas de um curso disponíveis conforme permissões
     */
    private function getTurmasPorCurso(int $cursoId, User $user)
    {
        $query = Turma::where('curso_id', $cursoId)
            ->where('activo', true);

        if ($user->hasAnyRole(['admin_full', 'subdiretor_pedagogico'])) {
            // Admin e Subdiretor veem todas
        } elseif ($user->hasRole('director_turma')) {
            $turmasIds = $user->turmasComoDirector()
                ->where('director_turma.activo', true)
                ->pluck('turmas.id');
            $query->whereIn('id', $turmasIds);
        } elseif ($user->hasRole('professor')) {
            $turmasIds = $user->turmasComoProfessor()
                ->where('professor_disciplina_turma.activo', true)
                ->pluck('turmas.id');
            $query->whereIn('id', $turmasIds);
        }

        return $query->orderBy('nome')->get();
    }
}
