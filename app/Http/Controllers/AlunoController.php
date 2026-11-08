<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlunoRequest;
use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\AlunoMatriculadoNotification;
use App\Services\WhatsApp\EncarregadoWhatsAppNotifier;
use App\Support\AlunoImportTempFile;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AlunoController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Aluno::class);

        // Filtro de ano lectivo via turma
        $anoLectivoId = $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());
        $turmaId = $request->get('turma_id');
        $cursoId = $request->get('curso_id');
        $situacao = $request->get('situacao');

        $query = Aluno::with(['turma.curso', 'turma.classe', 'turma.anoLectivo'])
            ->whereHas('turma', function($q) use ($anoLectivoId) {
                $q->where('ano_lectivo_id', $anoLectivoId);
            })
            ->orderBy('nome');

        if ($turmaId) {
            $query->where('turma_id', $turmaId);
        }

        if ($cursoId) {
            $query->whereHas('turma', function ($q) use ($cursoId) {
                $q->where('curso_id', $cursoId);
            });
        }

        if ($situacao) {
            $query->where('situacao_academica', $situacao);
        }

        // Filtrar por permissoes do utilizador
        $user = auth()->user();
        if ($user->temVisaoInstitucional() || $user->temAcessoSecretariaAlunos() || $user->temVisaoAssiduidade()) {
            // Admin, DP, directora, gestor, secretaria, inspetor — visão do ano
        } elseif ($user->hasRole('coordenador_curso')) {
            $anoId = Configuracao::getAnoLectivoActualId();
            $cursosQuery = $user->cursosComoCoordenador()->wherePivot('activo', true);
            if ($anoId) {
                $cursosQuery->wherePivot('ano_lectivo_id', $anoId);
            }
            $cursosIds = $cursosQuery->pluck('cursos.id');
            if ($cursosIds->isEmpty()) {
                $cursosIds = $user->cursosComoCoordenador()->wherePivot('activo', true)->pluck('cursos.id');
            }
            $query->whereHas('turma', fn ($q) => $q->whereIn('curso_id', $cursosIds));
        } elseif ($user->hasRole('coordenador_disciplina')) {
            $turmaIds = app(\App\Services\Pedagogico\DisciplinaCoordEscopoService::class)
                ->turmaIdsPorClasses($user, (int) $anoLectivoId);
            $query->whereIn('turma_id', $turmaIds->isEmpty() ? [-1] : $turmaIds);
        } elseif ($user->hasRole('director_turma')) {
            $turmasIds = $user->turmasComoDirector()->wherePivot('activo', true)->pluck('turmas.id');
            $query->whereIn('turma_id', $turmasIds);
        } else {
            $turmasIds = $user->turmasComoProfessor()->pluck('turma_id');
            $query->whereIn('turma_id', $turmasIds);
        }

        $alunos = $query->paginate(10)->withQueryString();

        // Turmas filtradas pelo ano lectivo selecionado E por permissões do usuário
        $turmasQuery = Turma::where('ano_lectivo_id', $anoLectivoId);

        // Aplicar filtros de permissão nas turmas disponíveis
        if ($user->temVisaoInstitucional() || $user->temAcessoSecretariaAlunos() || $user->temVisaoAssiduidade()) {
            // visão ampla
        } elseif ($user->hasRole('coordenador_curso')) {
            $anoId = Configuracao::getAnoLectivoActualId();
            $cursosQuery = $user->cursosComoCoordenador()->wherePivot('activo', true);
            if ($anoId) {
                $cursosQuery->wherePivot('ano_lectivo_id', $anoId);
            }
            $cursosIds = $cursosQuery->pluck('cursos.id');
            if ($cursosIds->isEmpty()) {
                $cursosIds = $user->cursosComoCoordenador()->wherePivot('activo', true)->pluck('cursos.id');
            }
            $turmasQuery->whereIn('curso_id', $cursosIds);
        } elseif ($user->hasRole('coordenador_disciplina')) {
            $turmaIds = app(\App\Services\Pedagogico\DisciplinaCoordEscopoService::class)
                ->turmaIdsPorClasses($user, (int) $anoLectivoId);
            $turmasQuery->whereIn('id', $turmaIds->isEmpty() ? [-1] : $turmaIds);
        } elseif ($user->hasRole('director_turma')) {
            $turmasIds = $user->turmasComoDirector()->wherePivot('activo', true)->pluck('turmas.id');
            $turmasQuery->whereIn('id', $turmasIds);
        } else {
            $turmasIds = $user->turmasComoProfessor()->pluck('turma_id');
            $turmasQuery->whereIn('id', $turmasIds);
        }

        $turmas = $turmasQuery->with('curso')
            ->orderBy('nome')
            ->get();

        // Dados para o filtro
        $anosLectivos = AnoLectivo::getParaSelect();
        $modoConsulta = Configuracao::isModoConsultaById($anoLectivoId);

        return view('alunos.index', compact('alunos', 'turmas', 'turmaId', 'cursoId', 'anosLectivos', 'anoLectivoId', 'modoConsulta'));
    }

    public function create(): View|RedirectResponse
    {
        $this->authorize('create', Aluno::class);

        $user = auth()->user();
        if ($user->hasRole('secretaria')
            && ! $user->temVisaoInstitucional()
            && $user->can('secretaria.matriculas.gerir')) {
            return redirect()
                ->route('secretaria.matriculas.create')
                ->with('info', 'Novos alunos devem ser matriculados pela Secretaria.');
        }

        $query = Turma::activo()->with('curso');

        // Filtrar turmas por permissões do utilizador
        $anoLectivoAtualId = Configuracao::getAnoLectivoActualId();

        if ($user->temVisaoInstitucional() || $user->hasRole('coordenador_curso')) {
            // Admin, Subdiretor e Coordenador veem todas - sem filtro adicional
        } elseif ($user->hasRole('director_turma')) {
            $turmasIds = $user->turmasComoDirector()->pluck('turmas.id');
            $query->whereIn('id', $turmasIds);
        } else {
            $turmasIds = $user->turmasComoProfessor()->pluck('turma_id');
            $query->whereIn('id', $turmasIds);
        }

        $turmas = $query->orderBy('nome')->get();

        return view('alunos.create', compact('turmas'));
    }

    public function store(AlunoRequest $request): RedirectResponse
    {
        $this->authorize('create', Aluno::class);

        $data = $request->validated();
        unset($data['foto']);

        $filiacao = [
            'pai' => [
                'nome' => $data['pai_nome'] ?? null,
                'telefone' => $data['pai_telefone'] ?? null,
            ],
            'mae' => [
                'nome' => $data['mae_nome'] ?? null,
                'telefone' => $data['mae_telefone'] ?? null,
            ],
            'encarregado' => [
                'nome' => null,
            ],
        ];
        unset($data['pai_nome'], $data['pai_telefone'], $data['mae_nome'], $data['mae_telefone']);

        if ($request->hasFile('foto')) {
            $data['foto_path'] = $request->file('foto')->store('alunos/fotos', 'public');
        }

        // 🔤 ORDEM ALFABÉTICA: Temporariamente null, será recalculado
        $data['numero_ordem'] = null;

        $aluno = Aluno::create($data);

        app(\App\Services\MatriculaService::class)->vincularFiliacao($aluno, $filiacao);

        try {
            app(\App\Services\AlunoAuthService::class)->criarOuActualizarConta($aluno);
        } catch (\Throwable $e) {
            Log::warning('Falha ao criar conta de aluno: '.$e->getMessage(), ['aluno_id' => $aluno->id]);
        }

        // Recalcular todos os números de ordem da turma em ordem alfabética
        $this->recalcularNumerosOrdem($aluno->turma_id);

        app(\App\Services\MatriculaService::class)->registarHistorico($aluno->fresh(), \App\Models\MatriculaHistorico::TIPO_MATRICULA, [
            'turma_id' => $aluno->turma_id,
            'para_turma_id' => $aluno->turma_id,
            'ano_lectivo_id' => $aluno->turma?->ano_lectivo_id,
            'motivo' => 'Matrícula via cadastro de aluno',
            'actor' => auth()->user(),
        ]);

        // Notificar admins sobre nova matrícula — nunca bloqueia o fluxo principal
        try {
            $aluno->load(['turma.curso', 'turma.sala']);
            $anoLectivo = Configuracao::getAnoLectivoActual() ?? '';

            $admins = User::role('admin_full')->where('estado', 'activo')->get();
            foreach ($admins as $admin) {
                $admin->notify(new AlunoMatriculadoNotification($aluno->turma, $anoLectivo));
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar AlunoMatriculadoNotification: ' . $e->getMessage(), [
                'aluno_id' => $aluno->id,
            ]);
        }

        return redirect()->route('alunos.index')
            ->with('success', 'Aluno criado com sucesso.');
    }

    public function show(Aluno $aluno): View
    {
        $this->authorize('view', $aluno);

        $aluno->load(['turma.curso', 'turma.turno', 'turma.sala', 'notas.disciplina', 'user', 'documentos.uploadedBy', 'historicosMatricula', 'encarregados']);

        $assinaturaService = app(\App\Services\DocumentoAssinaturaService::class);
        $cargosAssinatura = $assinaturaService->cargosPermitidos(auth()->user());
        $anoAssinatura = Configuracao::getAnoLectivoActual();
        $chaveBoletim = 'aluno:'.$aluno->id.'|ano:'.$anoAssinatura.'|t:anual';
        $assinaturasBoletim = $assinaturaService->listar('boletim', $chaveBoletim);

        return view('alunos.show', compact('aluno', 'cargosAssinatura', 'anoAssinatura', 'assinaturasBoletim'));
    }

    public function reporPalavraPasse(Aluno $aluno): RedirectResponse
    {
        $this->authorize('reporPalavraPasse', $aluno);

        $temporaria = app(\App\Services\PasswordResetService::class)->reporAluno($aluno);

        return back()->with(
            'success',
            "Palavra-passe reposta. Login: nº de processo. Senha temporária: {$temporaria} — o aluno deve alterá-la no primeiro acesso."
        );
    }

    public function edit(Aluno $aluno): View
    {
        $this->authorize('update', $aluno);

        $query = Turma::activo()->with('curso');

        // Filtrar turmas por permissões do utilizador
        $user = auth()->user();
        $anoLectivoAtualId = Configuracao::getAnoLectivoActualId();

        if ($user->temVisaoInstitucional() || $user->hasRole('coordenador_curso')) {
            // Admin, Subdiretor e Coordenador veem todas - sem filtro adicional
        } elseif ($user->hasRole('director_turma')) {
            $turmasIds = $user->turmasComoDirector()->pluck('turmas.id');
            $query->whereIn('id', $turmasIds);
        } else {
            $turmasIds = $user->turmasComoProfessor()->pluck('turma_id');
            $query->whereIn('id', $turmasIds);
        }

        $turmas = $query->orderBy('nome')->get();

        return view('alunos.edit', compact('aluno', 'turmas'));
    }

    public function update(AlunoRequest $request, Aluno $aluno): RedirectResponse
    {
        $this->authorize('update', $aluno);

        $data = $request->validated();
        unset(
            $data['foto'],
            $data['pai_nome'],
            $data['pai_telefone'],
            $data['mae_nome'],
            $data['mae_telefone'],
        );
        $turmaAnterior = $aluno->turma_id;

        if ($request->hasFile('foto')) {
            if ($aluno->foto_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($aluno->foto_path);
            }
            $data['foto_path'] = $request->file('foto')->store('alunos/fotos', 'public');
        }

        // 🔤 ORDEM ALFABÉTICA: Se a turma mudou, recalcular ambas as turmas
        if ($data['turma_id'] != $turmaAnterior) {
            $data['numero_ordem'] = null;
            $aluno->update($data);

            // Recalcular ordem alfabética na turma anterior e na nova
            $this->recalcularNumerosOrdem($turmaAnterior);
            $this->recalcularNumerosOrdem($data['turma_id']);
        } else {
            // Se mudou o nome, recalcular ordem alfabética da turma
            if (isset($data['nome']) && $data['nome'] !== $aluno->nome) {
                $aluno->update($data);
                $this->recalcularNumerosOrdem($aluno->turma_id);
            } else {
                // Sem mudança de nome ou turma, apenas atualizar
                unset($data['numero_ordem']);
                $aluno->update($data);
            }
        }

        return redirect()->route('alunos.index')
            ->with('success', 'Aluno actualizado com sucesso.');
    }

    public function toggleConsultaPortal(Aluno $aluno): RedirectResponse
    {
        $this->authorize('update', $aluno);

        $aluno->update([
            'consulta_notas_portal' => ! $aluno->consulta_notas_portal,
        ]);

        $estado = $aluno->consulta_notas_portal ? 'activada' : 'bloqueada';

        activity()
            ->performedOn($aluno)
            ->causedBy(auth()->user())
            ->withProperties(['consulta_notas_portal' => $aluno->consulta_notas_portal])
            ->log("Consulta de notas no portal {$estado}");

        if ($aluno->consulta_notas_portal) {
            try {
                app(EncarregadoWhatsAppNotifier::class)->boletimDisponivel($aluno);
            } catch (\Throwable $e) {
                Log::warning('WhatsApp boletim disponível falhou: '.$e->getMessage(), [
                    'aluno_id' => $aluno->id,
                ]);
            }
        }

        return back()->with('success', "Consulta de notas no portal {$estado} para {$aluno->nome}.");
    }

    public function destroy(Aluno $aluno): RedirectResponse
    {
        $this->authorize('delete', $aluno);

        $nome = $aluno->nome;
        $notasCount = $aluno->notas()->count();

        \Illuminate\Support\Facades\DB::transaction(function () use ($aluno) {
            $aluno->eliminarPermanentementeComAvaliacoes();
        });

        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'aluno_eliminado' => $nome,
                'notas_eliminadas' => $notasCount,
            ])
            ->log('Aluno eliminado permanentemente pelo admin (com avaliações)');

        $mensagem = "Aluno {$nome} eliminado permanentemente.";
        if ($notasCount > 0) {
            $mensagem .= " Foram removidas {$notasCount} avaliação(ões).";
        }

        return redirect()->route('alunos.index')
            ->with('success', $mensagem);
    }


    /**
     * Exibe o formulário de importação em massa
     */
    public function import(): View
    {
        $this->authorize('create', Aluno::class);

        $turmas = Turma::with('curso')
            ->orderBy('nome')
            ->get();

        return view('alunos.import', compact('turmas'));
    }

    /**
     * Preview da importação antes de executar
     * Melhoria #7: Implementar preview antes de executar
     */
    public function importPreview(Request $request): View|RedirectResponse
    {
        $this->authorize('create', Aluno::class);

        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048',
            'turma_id' => 'nullable|exists:turmas,id',
        ]);

        if (! $this->hasZipSupportForImportFile($request->file('file')->getClientOriginalName())) {
            return redirect()->route('alunos.import')
                ->with('error', 'A extensão ZIP do PHP está desativada. Ative extension=zip no php.ini e reinicie o servidor para importar ficheiros .xlsx.');
        }

        // Validar acesso à turma especificada
        $turmaId = $request->turma_id;
        if ($turmaId) {
            $turma = Turma::findOrFail($turmaId);
            $user = auth()->user();

            if ($user->temVisaoInstitucional() || $user->hasRole('coordenador_curso')) {
                $hasAccess = true;
            } else {
                $hasAccess = false;

                if ($user->hasRole('director_turma')) {
                    $turmasIds = $user->turmasComoDirector()->pluck('turmas.id');
                    $hasAccess = $turmasIds->contains($turmaId);
                }

                if (!$hasAccess) {
                    return redirect()->route('alunos.import')
                        ->with('error', 'Não tem permissão para importar alunos para esta turma.');
                }
            }
        }

        try {
            $file = $request->file('file');
            $userId = (int) auth()->id();

            $tempFilePath = AlunoImportTempFile::storeFromUpload($file, $userId);

            \Log::info('=== ARQUIVO SALVO COM SUCESSO ===', [
                'exists' => file_exists($tempFilePath),
                'size' => filesize($tempFilePath),
            ]);

            // Ler arquivo sem importar
            $rows = \Maatwebsite\Excel\Facades\Excel::toCollection(
                new \App\Imports\AlunosImport($turmaId),
                $tempFilePath
            )->first();

            // Limitar preview a 100 linhas
            $previewRows = $rows->take(100);
            $totalRows = $rows->count();

            // Verificar limite
            $exceedsLimit = $totalRows > \App\Imports\AlunosImport::MAX_ROWS;

            // Buscar informações das turmas mencionadas
            $turmasIds = $previewRows->pluck('turma_id')->filter()->unique();
            $turmas = Turma::with('curso')->whereIn('id', $turmasIds)->get()->keyBy('id');

            // Adicionar turma padrão se fornecida
            if ($turmaId) {
                $turmaPadrao = Turma::with('curso')->find($turmaId);
            } else {
                $turmaPadrao = null;
            }

            \Log::info('=== RETORNANDO VIEW PREVIEW ===', [
                'turma_id' => $turmaId,
                'total_rows' => $totalRows,
            ]);

            $importToken = AlunoImportTempFile::registerInSession(
                $request,
                $tempFilePath,
                (int) auth()->id(),
                $turmaId ? (int) $turmaId : null,
            );

            return view('alunos.import-preview', compact(
                'previewRows',
                'totalRows',
                'turmas',
                'turmaPadrao',
                'turmaId',
                'exceedsLimit',
                'importToken',
            ));

        } catch (\Exception $e) {
            \Log::error('Erro no preview de importação de alunos', ['exception' => $e->getMessage()]);

            return redirect()->route('alunos.import')
                ->with('error', 'Erro ao processar ficheiro. Verifique o formato e tente novamente.');
        }
    }

    /**
     * Processa a importação em massa de alunos
     *
     * Melhorias implementadas:
     * #1 - Transação DB explícita para garantir atomicidade
     * #5 - Logging de batch completo com auditoria
     * #6 - Turma padrão opcional (se todas linhas tiverem turma_id)
     * #7 - Preview antes de executar
     * #8 - Download de erros em CSV
     */
    public function importStore(Request $request): RedirectResponse
    {
        \Log::info('=== INÍCIO IMPORT STORE ===', [
            'has_import_token' => $request->filled('import_token'),
            'has_file' => $request->hasFile('file'),
            'turma_id' => $request->turma_id,
        ]);

        $this->authorize('create', Aluno::class);

        $request->validate([
            'file' => 'required_without:import_token|mimes:xlsx,xls,csv|max:2048',
            'import_token' => 'required_without:file|string|size:64',
            'turma_id' => 'nullable|exists:turmas,id',
        ]);

        // Validar acesso à turma especificada
        $turmaId = $request->turma_id;
        if ($turmaId) {
            $turma = Turma::findOrFail($turmaId);
            $user = auth()->user();

            if ($user->temVisaoInstitucional() || $user->hasRole('coordenador_curso')) {
                $hasAccess = true;
            } else {
                $hasAccess = false;

                if ($user->hasRole('director_turma')) {
                    $turmasIds = $user->turmasComoDirector()->pluck('turmas.id');
                    $hasAccess = $turmasIds->contains($turmaId);
                }

                if (!$hasAccess) {
                    return redirect()->route('alunos.import')
                        ->with('error', 'Não tem permissão para importar alunos para esta turma.');
                }
            }
        }

        \Log::info('=== VALIDAÇÃO PASSOU ===');

        $importToken = $request->input('import_token');
        $filePath = null;

        if ($importToken) {
            $filePath = AlunoImportTempFile::resolveFromSession(
                $request,
                $importToken,
                (int) auth()->id(),
            );

            if (! file_exists($filePath)) {
                return redirect()->route('alunos.import')
                    ->with('error', 'Ficheiro temporário não encontrado. Por favor, faça o upload novamente.');
            }

            $file = $filePath;
            $fileName = basename($filePath);
            $fileSize = filesize($filePath);
        } else {
            // Arquivo direto do formulário
            $uploadedFile = $request->file('file');
            $file = $uploadedFile;
            $fileName = $uploadedFile->getClientOriginalName();
            $fileSize = $uploadedFile->getSize();

            \Log::info('=== USANDO ARQUIVO DIRETO ===', [
                'name' => $fileName,
                'size' => $fileSize,
            ]);
        }

        $turmaId = $request->turma_id;
        $importedCount = 0;
        $failures = collect();

        \Log::info('=== ANTES DA TRANSAÇÃO ===', [
            'turma_id' => $turmaId,
            'file' => $fileName,
        ]);

        if (! $this->hasZipSupportForImportFile($fileName)) {
            if ($importToken) {
                AlunoImportTempFile::deleteFile($filePath, (int) auth()->id());
                AlunoImportTempFile::forgetSession($request, $importToken);
            }

            return redirect()->route('alunos.import')
                ->with('error', 'A extensão ZIP do PHP está desativada. Ative extension=zip no php.ini e reinicie o servidor para importar ficheiros .xlsx.');
        }

        try {
            if (! $importToken) {
                $rows = \Maatwebsite\Excel\Facades\Excel::toCollection(
                    new \App\Imports\AlunosImport($turmaId),
                    $file
                )->first();
                $totalRows = $rows ? $rows->count() : 0;

                if ($totalRows > \App\Imports\AlunosImport::MAX_ROWS) {
                    return redirect()->route('alunos.import')
                        ->with('error', 'O ficheiro excede o limite de '.\App\Imports\AlunosImport::MAX_ROWS.' alunos por importação.');
                }
            }

            \Log::info('=== ENTRANDO NA TRANSAÇÃO ===');

            // Melhoria #1: Envolver tudo em transação DB
            DB::transaction(function () use ($file, $turmaId, &$importedCount, &$failures) {
                \Log::info('=== DENTRO DA TRANSAÇÃO ===', ['turma_id' => $turmaId]);

                $import = new \App\Imports\AlunosImport($turmaId);

                \Log::info('=== IMPORT CRIADO, INICIANDO EXCEL::IMPORT ===');

                \Maatwebsite\Excel\Facades\Excel::import($import, $file);

                \Log::info('=== EXCEL::IMPORT CONCLUÍDO ===');

                $importedCount = $import->getImportedCount();
                $failures = $import->failures();

                \Log::info('=== CONTADORES ===', [
                    'imported' => $importedCount,
                    'failures' => $failures->count(),
                ]);

                // Se houver falhas, lançar exceção para fazer rollback
                if ($failures->count() > 0) {
                    \Log::warning('=== FALHAS DETECTADAS, FAZENDO ROLLBACK ===');
                    throw new \Exception('Falhas na importação');
                }

                \Log::info('=== TRANSAÇÃO VAI COMMITAR ===');
            }, attempts: 3);

            \Log::info('=== TRANSAÇÃO COMMITADA COM SUCESSO ===');

            // 🔤 ORDEM ALFABÉTICA: Recalcular todas as turmas afetadas
            $turmasAfetadas = $import->getTurmasAfetadas();
            foreach ($turmasAfetadas as $turmaIdAfetada) {
                $this->recalcularNumerosOrdem($turmaIdAfetada);
            }

            try {
                $authService = app(\App\Services\AlunoAuthService::class);
                foreach ($turmasAfetadas as $turmaIdAfetada) {
                    Aluno::query()
                        ->where('turma_id', $turmaIdAfetada)
                        ->where('activo', true)
                        ->each(fn (Aluno $a) => $authService->criarOuActualizarConta($a));
                }
            } catch (\Throwable $e) {
                Log::warning('Falha ao sincronizar contas de alunos importados: '.$e->getMessage());
            }

            // Sucesso total - Melhoria #5: Logging de batch
            activity()
                ->causedBy(auth()->user())
                ->withProperties([
                    'imported_count' => $importedCount,
                    'file_name' => $fileName,
                    'file_size' => $fileSize,
                    'turma_id' => $turmaId,
                    'turma_nome' => $turmaId ? Turma::find($turmaId)?->codigo : 'Variadas',
                    'turmas_afetadas' => $turmasAfetadas,
                ])
                ->log('Importação em massa de alunos realizada com sucesso');

            // Limpar arquivo temporário se existir
            if ($importToken) {
                AlunoImportTempFile::deleteFile($filePath ?? null, (int) auth()->id());
                AlunoImportTempFile::forgetSession($request, $importToken);
            }

            \Log::info('=== REDIRECIONANDO PARA SUCESSO ===', ['count' => $importedCount]);

            return redirect()->route('alunos.index')
                ->with('success', "{$importedCount} alunos importados com sucesso!");

        } catch (\Exception $e) {
            \Log::error('=== EXCEPTION CAPTURADA ===', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'failures_count' => $failures->count(),
            ]);

            // Limpar arquivo temporário se existir
            if ($importToken) {
                AlunoImportTempFile::deleteFile($filePath ?? null, (int) auth()->id());
                AlunoImportTempFile::forgetSession($request, $importToken);
            }

            // Se houver falhas de validação, processar sem transação para mostrar erros
            if ($e->getMessage() === 'Falhas na importação' && $failures->count() > 0) {
                \Log::info('=== PROCESSANDO FALHAS DE VALIDAÇÃO ===');
                // Melhoria #8: Gerar CSV com erros
                $errorCsvPath = $this->generateErrorCsv($failures);

                // Logging de importação parcial
                activity()
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'imported_count' => 0,
                        'failed_count' => $failures->count(),
                        'file_name' => $fileName,
                        'error_file' => $errorCsvPath,
                    ])
                    ->log('Importação em massa de alunos falhou');

                return redirect()->route('alunos.import')
                    ->with('import_errors', $failures)
                    ->with('error_csv_path', $errorCsvPath)
                    ->with('warning', "A importação falhou. {$failures->count()} registos com erro. Baixe o arquivo de erros para corrigir.");
            }

            // Logging de erro geral
            \Log::error('Erro na importação de alunos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $fileName ?? 'unknown',
                'user' => auth()->id(),
            ]);

            return redirect()->route('alunos.import')
                ->with('error', 'Erro na importação: ' . $e->getMessage());
        }
    }

    /**
     * Download do template de importação
     */
    public function downloadTemplate()
    {
        $this->authorize('create', Aluno::class);

        return \App\Support\AlunoImportTemplate::download('template_importacao_alunos.xlsx');
    }

    /**
     * Gera arquivo CSV com erros da importação
     * Melhoria #8: Download de erros em CSV
     *
     * @param \Illuminate\Support\Collection $failures
     * @return string Path do arquivo gerado
     */
    private function hasZipSupportForImportFile(string $fileName): bool
    {
        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'xlsx'
            || class_exists(\ZipArchive::class);
    }

    private function generateErrorCsv($failures): string
    {
        $userId = auth()->id();
        $timestamp = now()->format('YmdHis');
        $filename = "erros-importacao-{$userId}-{$timestamp}.csv";

        // Gerar conteúdo CSV
        $csvContent = "Linha;Erros\n";

        foreach ($failures as $failure) {
            $linha = $failure->row();
            $erros = implode(', ', $failure->errors());

            // Escapar aspas duplas no conteúdo
            $erros = str_replace('"', '""', $erros);

            $csvContent .= "{$linha};\"{$erros}\"\n";
        }

        // Salvar no storage público
        Storage::disk('public')->put("import-errors/{$filename}", $csvContent);

        return "import-errors/{$filename}";
    }

    /**
     * Download do arquivo de erros da importação
     * Melhoria #8: Download de erros em CSV
     */
    public function downloadErrors(Request $request)
    {
        $this->authorize('create', Aluno::class);

        $path = $request->query('path');

        if (!$path || !Storage::disk('public')->exists($path)) {
            abort(404, 'Arquivo de erros não encontrado.');
        }

        return Storage::disk('public')->download($path);
    }

    /**
     * Atualiza situação acadêmica de um aluno
     */
    public function updateSituacao(Request $request, Aluno $aluno)
    {
        $this->authorize('updateSituacao', $aluno);

        $request->validate([
            'situacao_academica' => 'required|in:matriculado,aprovado,reprovado,desistente,transferido',
            'observacao' => 'nullable|string|max:500',
        ]);

        $situacaoAnterior = $aluno->situacao_academica;

        $aluno->update([
            'situacao_academica' => $request->situacao_academica,
        ]);

        // Auditoria
        activity()
            ->causedBy(auth()->user())
            ->performedOn($aluno)
            ->withProperties([
                'situacao_anterior' => $situacaoAnterior,
                'situacao_nova' => $request->situacao_academica,
                'observacao' => $request->observacao,
            ])
            ->log('Situação académica alterada manualmente');

        return back()->with('success', 'Situação académica actualizada com sucesso.');
    }

    /**
     * Exibir formulário de transferência de turma
     */
    public function transferForm(Aluno $aluno): View
    {
        $this->authorize('update', $aluno);

        // Buscar turmas do mesmo ano lectivo (exceto a turma atual)
        $turmasDisponiveis = Turma::where('ano_lectivo_id', $aluno->turma->ano_lectivo_id)
            ->where('id', '!=', $aluno->turma_id)
            ->where('activo', true)
            ->with(['curso', 'classe'])
            ->orderBy('nome')
            ->get();

        // Verificar se há notas lançadas
        $notasCount = $aluno->notas()->count();

        return view('alunos.transfer', compact('aluno', 'turmasDisponiveis', 'notasCount'));
    }

    /**
     * Processar transferência de aluno para outra turma
     */
    public function transfer(Request $request, Aluno $aluno): RedirectResponse
    {
        $this->authorize('update', $aluno);

        // 🔒 VALIDAÇÃO CRÍTICA 1: Modo Consulta
        // Protege dados históricos em anos lectivos bloqueados
        if (\App\Models\Configuracao::isModoConsultaById($aluno->turma->ano_lectivo_id)) {
            if (!auth()->user()->hasRole('admin_full')) {
                return back()->with('error',
                    'Não é possível transferir alunos em ano lectivo em modo consulta. ' .
                    'Este ano lectivo está bloqueado para preservar dados históricos.'
                );
            }

            // Admin_full pode, mas com aviso forte
            session()->flash('warning',
                '⚠️ ATENÇÃO: Você está modificando dados de um ano lectivo em modo consulta (bloqueado). ' .
                'Esta ação será registada na auditoria.'
            );
        }

        $validated = $request->validate([
            'nova_turma_id' => 'required|exists:turmas,id',
            'motivo' => 'required|string|max:500',
            'manter_notas' => 'nullable|boolean',
        ], [
            'nova_turma_id.required' => 'Selecione a turma de destino.',
            'nova_turma_id.exists' => 'A turma selecionada não existe.',
            'motivo.required' => 'Informe o motivo da transferência.',
            'motivo.max' => 'O motivo não pode ter mais de 500 caracteres.',
        ]);

        $novaTurma = Turma::findOrFail($validated['nova_turma_id']);
        $turmaAnterior = $aluno->turma;

        // Validar que é o mesmo ano lectivo
        if ($novaTurma->ano_lectivo_id !== $turmaAnterior->ano_lectivo_id) {
            return back()->with('error', 'Só é possível transferir alunos entre turmas do mesmo ano lectivo.');
        }

        // 🔒 VALIDAÇÃO CRÍTICA 2: Mesmo Curso
        // Previne inconsistências de disciplinas entre cursos diferentes
        if ($novaTurma->curso_id !== $turmaAnterior->curso_id) {
            // Admin / secretaria podem forçar com confirmação explícita
            if (! auth()->user()->podeTransferirEntreCursos() || ! $request->boolean('confirmar_troca_curso')) {
                $cursoAnterior = $turmaAnterior->curso->nome ?? 'curso anterior';
                $cursoNovo = $novaTurma->curso->nome ?? 'curso novo';

                return back()->with('error',
                    "Não é possível transferir alunos entre cursos diferentes ({$cursoAnterior} → {$cursoNovo}). " .
                    "As disciplinas dos cursos são incompatíveis. " .
                    "Confirme a troca de curso explicitamente se tiver permissão, ou seleccione uma turma do mesmo curso."
                );
            }

            // Confirmou: força arquivamento de notas
            session()->flash('warning',
                '⚠️ ATENÇÃO: Transferência entre cursos diferentes detectada. ' .
                'Todas as notas serão ARQUIVADAS automaticamente para evitar inconsistências.'
            );
            $request->merge(['manter_notas' => false]); // Força arquivamento
        }

        // Validar que não é a mesma turma
        if ($novaTurma->id === $turmaAnterior->id) {
            return back()->with('error', 'O aluno já está matriculado nesta turma.');
        }

        DB::beginTransaction();
        try {
            // Verificar notas existentes
            $notasCount = $aluno->notas()->count();

            if ($notasCount > 0) {
                if ($request->boolean('manter_notas')) {
                    // Manter notas: atualizar turma_id das notas para a nova turma
                    $aluno->notas()->update([
                        'turma_id' => $novaTurma->id,
                    ]);

                    activity()
                        ->causedBy(auth()->user())
                        ->performedOn($aluno)
                        ->withProperties([
                            'notas_atualizadas' => $notasCount,
                            'turma_anterior_id' => $turmaAnterior->id,
                            'turma_nova_id' => $novaTurma->id,
                            'motivo' => 'Transferência de turma',
                        ])
                        ->log('Notas do aluno transferidas para nova turma');
                } else {
                    // 🔒 CORREÇÃO CRÍTICA: Arquivar em vez de deletar permanentemente
                    // Permite recuperação posterior por administradores
                    $reason = sprintf(
                        'Transferência de %s para %s. Motivo: %s',
                        $turmaAnterior->nome,
                        $novaTurma->nome,
                        $validated['motivo']
                    );

                    $arquivadas = 0;
                    foreach ($aluno->notas as $nota) {
                        if ($nota->archive($reason, auth()->id())) {
                            $arquivadas++;
                        }
                    }

                    activity()
                        ->causedBy(auth()->user())
                        ->performedOn($aluno)
                        ->withProperties([
                            'notas_arquivadas' => $arquivadas,
                            'notas_total' => $notasCount,
                            'turma_anterior' => $turmaAnterior->nome,
                            'turma_nova' => $novaTurma->nome,
                            'motivo' => $validated['motivo'],
                            'reversivel' => true,
                        ])
                        ->log('Notas do aluno arquivadas por transferência (reversível)');
                }
            }

            // Atualizar turma do aluno
            $aluno->update([
                'turma_id' => $novaTurma->id,
                'numero_ordem' => null, // Será recalculado
            ]);

            // Recalcular números de ordem na turma anterior
            $this->recalcularNumerosOrdem($turmaAnterior->id);

            // Recalcular números de ordem na nova turma
            $this->recalcularNumerosOrdem($novaTurma->id);

            // Log de auditoria
            activity()
                ->causedBy(auth()->user())
                ->performedOn($aluno)
                ->withProperties([
                    'turma_anterior' => [
                        'id' => $turmaAnterior->id,
                        'nome' => $turmaAnterior->nome,
                        'curso' => $turmaAnterior->curso->nome,
                    ],
                    'turma_nova' => [
                        'id' => $novaTurma->id,
                        'nome' => $novaTurma->nome,
                        'curso' => $novaTurma->curso->nome,
                    ],
                    'motivo' => $validated['motivo'],
                    'notas_mantidas' => $request->boolean('manter_notas'),
                    'notas_count' => $notasCount,
                ])
                ->log('Aluno transferido de turma');

            $tipoHistorico = ($novaTurma->curso_id !== $turmaAnterior->curso_id)
                ? \App\Models\MatriculaHistorico::TIPO_MUDANCA_CURSO
                : \App\Models\MatriculaHistorico::TIPO_TRANSFERENCIA;

            app(\App\Services\MatriculaService::class)->registarHistorico($aluno->fresh(), $tipoHistorico, [
                'turma_id' => $novaTurma->id,
                'de_turma_id' => $turmaAnterior->id,
                'para_turma_id' => $novaTurma->id,
                'ano_lectivo_id' => $novaTurma->ano_lectivo_id,
                'motivo' => $validated['motivo'],
                'actor' => auth()->user(),
                'dados' => [
                    'notas_mantidas' => $request->boolean('manter_notas'),
                    'notas_count' => $notasCount,
                ],
            ]);

            DB::commit();

            $mensagem = "Aluno transferido com sucesso de {$turmaAnterior->nome} para {$novaTurma->nome}.";

            if ($notasCount > 0) {
                if ($request->boolean('manter_notas')) {
                    $mensagem .= " As {$notasCount} notas foram mantidas.";
                } else {
                    $mensagem .= " As {$notasCount} notas foram removidas.";
                }
            }

            return redirect()->route('alunos.show', $aluno)
                ->with('success', $mensagem);

        } catch (\Exception $e) {
            DB::rollBack();

            activity()
                ->causedBy(auth()->user())
                ->performedOn($aluno)
                ->withProperties([
                    'erro' => $e->getMessage(),
                    'turma_destino_id' => $validated['nova_turma_id'],
                ])
                ->log('Erro ao transferir aluno de turma');

            return back()->with('error', 'Erro ao transferir aluno: ' . $e->getMessage());
        }
    }

    /**
     * Arquivar aluno (inactivar + histórico), sem eliminar.
     */
    public function arquivar(Request $request, Aluno $aluno): RedirectResponse
    {
        $this->authorize('update', $aluno);
        abort_unless($request->user()->temAcessoSecretariaAlunos() || $request->user()->temVisaoInstitucional(), 403);

        if (! $aluno->activo) {
            return back()->with('error', 'Este aluno já está arquivado/inactivo.');
        }

        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        $turmaId = $aluno->turma_id;

        DB::transaction(function () use ($aluno, $data, $request) {
            $anoLectivoId = $aluno->turma?->ano_lectivo_id;
            $turmaId = $aluno->turma_id;

            $aluno->update([
                'activo' => false,
                'situacao_academica' => Aluno::SITUACAO_DESISTENTE,
            ]);

            app(\App\Services\MatriculaService::class)->registarHistorico($aluno->fresh(), \App\Models\MatriculaHistorico::TIPO_ARQUIVO, [
                'turma_id' => $turmaId,
                'ano_lectivo_id' => $anoLectivoId,
                'motivo' => $data['motivo'],
                'actor' => $request->user(),
                'dados' => ['activo_anterior' => true],
            ]);
        });

        if ($turmaId) {
            $this->recalcularNumerosOrdem($turmaId);
        }

        return redirect()
            ->route('alunos.show', $aluno)
            ->with('success', 'Aluno arquivado. O histórico de matrícula foi registado.');
    }

    /**
     * Enviar email pontual (encarregado ou email indicado).
     */
    public function enviarEmail(Request $request, Aluno $aluno): RedirectResponse
    {
        $this->authorize('update', $aluno);
        $user = $request->user();
        $podeComunicar = $user->temAcessoSecretariaAlunos()
            || $user->temVisaoInstitucional()
            || ($user->can('turma.comunicar') && $aluno->turma_id
                && app(\App\Services\TurmaEscopoService::class)->podeAcederTurma($user, (int) $aluno->turma_id));

        abort_unless($podeComunicar, 403);

        $data = $request->validate([
            'destinatario' => ['required', 'email', 'max:255'],
            'assunto' => ['required', 'string', 'max:160'],
            'mensagem' => ['required', 'string', 'max:5000'],
            'nome_destinatario' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            \Illuminate\Support\Facades\Mail::to($data['destinatario'])
                ->send(new \App\Mail\SecretariaMensagemMail(
                    $data['assunto'],
                    $data['mensagem'],
                    $data['nome_destinatario'] ?: ($aluno->nome),
                ));
        } catch (\Throwable $e) {
            Log::warning('Falha envio email secretaria: '.$e->getMessage(), ['aluno_id' => $aluno->id]);

            return back()->with('error', 'Não foi possível enviar o email. Verifique a configuração de mail.');
        }

        activity()
            ->causedBy($user)
            ->performedOn($aluno)
            ->withProperties([
                'destinatario' => $data['destinatario'],
                'assunto' => $data['assunto'],
            ])
            ->log('Email enviado ao encarregado / contacto do aluno');

        return back()->with('success', 'Email enviado para '.$data['destinatario'].'.');
    }

    /**
     * Recalcular números de ordem de uma turma
     */
    private function recalcularNumerosOrdem(int $turmaId): void
    {
        // 🔒 CORREÇÃO: Evitar conflitos de constraint UNIQUE ao recalcular
        // Primeiro, limpar todos os números de ordem (definir como NULL)
        Aluno::where('turma_id', $turmaId)
            ->update(['numero_ordem' => null]);

        // Depois, atribuir novos números em ordem alfabética
        $alunos = Aluno::where('turma_id', $turmaId)
            ->orderBy('nome')
            ->get();

        foreach ($alunos as $index => $alunoItem) {
            $alunoItem->update(['numero_ordem' => $index + 1]);
        }
    }
}
