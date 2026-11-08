<?php

use App\Http\Controllers\AssistenteController;
use App\Http\Controllers\AlunoController;
use App\Http\Controllers\AlunoTransicaoController;
use App\Http\Controllers\AnoLectivoTransferController;
use App\Http\Controllers\AssociacaoController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\ConselhoController;
use App\Http\Controllers\CoordenadorCursoController;
use App\Http\Controllers\CoordenadorDisciplinaController;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectorTurmaController;
use App\Http\Controllers\DisciplinaController;
use App\Http\Controllers\EstatisticasController;
use App\Http\Controllers\NotaController;
use App\Http\Controllers\OcupacaoController;
use App\Http\Controllers\PautaArquivoController;
use App\Http\Controllers\PautaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SalaController;
use App\Http\Controllers\TurmaController;
use App\Http\Controllers\TurnoController;
use App\Http\Controllers\ConfiguracaoController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\PaginaPublicaController;
use App\Http\Controllers\PublicStorageController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\Control\ControlHubController;
use App\Http\Controllers\Control\ControlLicenseBlockedController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\Pedagogico\AulaController as PedagogicoAulaController;
use App\Http\Controllers\Pedagogico\CorrecaoController as PedagogicoCorrecaoController;
use App\Http\Controllers\Pedagogico\DashboardPedagogicoController;
use App\Http\Controllers\Pedagogico\ExecucaoController as PedagogicoExecucaoController;
use App\Http\Controllers\Pedagogico\ExercicioController as PedagogicoExercicioController;
use App\Http\Controllers\Pedagogico\PedagogicoController;
use App\Http\Controllers\Pedagogico\PlanificacaoController;
use App\Http\Controllers\Pedagogico\AprovacaoController;
use App\Http\Controllers\Pedagogico\BancoQuestaoController;
use App\Http\Controllers\Secretaria\BoletimConfigController as SecretariaBoletimConfigController;
use App\Http\Controllers\Secretaria\CartaoController as SecretariaCartaoController;
use App\Http\Controllers\Secretaria\DashboardController as SecretariaDashboardController;
use App\Http\Controllers\Secretaria\DocumentoController as SecretariaDocumentoController;
use App\Http\Controllers\Secretaria\MatriculaController as SecretariaMatriculaController;
use App\Http\Controllers\SetupInicialController;
use App\Http\Controllers\Assiduidade\PresencaController;
use App\Http\Controllers\Assiduidade\InspetorPainelController;
use App\Http\Controllers\Ocorrencias\OcorrenciaController;
use App\Http\Controllers\Pedagogico\PlanoAulaController;
use App\Http\Controllers\Pedagogico\ProvaController as PedagogicoProvaController;
use Illuminate\Support\Facades\Route;

Route::get('/arquivos/{path}', [PublicStorageController::class, 'show'])
    ->where('path', '.*')
    ->name('arquivos.publicos');

Route::get('/', function () {
    if (config('control.mode') === 'hub') {
        return auth()->check()
            ? redirect()->route('control.hub.index')
            : redirect()->route('login');
    }

    $configuracaoService = app(\App\Services\ConfiguracaoService::class);
    $instituicao = $configuracaoService->getInstituicao();
    $anoLectivo = \App\Models\Configuracao::getAnoLectivoActual();

    return view('welcome', compact('instituicao', 'anoLectivo'));
})->name('home');

Route::middleware('signed')->get('documentos/boletim/{aluno}', [ExportController::class, 'boletimPdfSigned'])
    ->name('documentos.boletim.signed');

Route::get('/licenca-bloqueada', ControlLicenseBlockedController::class)
    ->name('control.license.blocked');

Route::get('/webhooks/whatsapp', [\App\Http\Controllers\Webhooks\WhatsAppWebhookController::class, 'verify'])
    ->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [\App\Http\Controllers\Webhooks\WhatsAppWebhookController::class, 'receive'])
    ->middleware('throttle:120,1')
    ->name('webhooks.whatsapp.receive');

$facilitaPrefix = trim((string) config('facilita.route_prefix', 'app'), '/');

$registarRotasApp = function (): void {

    Route::get('/manifest-portal.webmanifest', [\App\Http\Controllers\PwaController::class, 'manifestPortal'])
        ->name('portal.manifest');
    Route::get('/manifest-staff.webmanifest', [\App\Http\Controllers\PwaController::class, 'manifestStaff'])
        ->name('pwa.manifest.staff');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'account.status'])
    ->name('dashboard');

    Route::middleware(['auth', 'account.status'])->group(function () {
    Route::get('/setup', [SetupInicialController::class, 'show'])->name('setup.inicial');
    Route::post('/setup', [SetupInicialController::class, 'store'])->name('setup.inicial.guardar');
    Route::get('/setup/aguardar', [SetupInicialController::class, 'aguardar'])->name('setup.aguardar');

    Route::post('/sessao/bloquear', [\App\Http\Controllers\SessionLockController::class, 'bloquear'])
        ->name('sessao.bloquear');
    Route::post('/sessao/desbloquear', [\App\Http\Controllers\SessionLockController::class, 'desbloquear'])
        ->middleware('throttle:10,1')
        ->name('sessao.desbloquear');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/profile/sessoes', [\App\Http\Controllers\SessaoActivaController::class, 'index'])->name('profile.sessoes');
    Route::delete('/profile/sessoes/{sessionId}', [\App\Http\Controllers\SessaoActivaController::class, 'terminar'])->name('profile.sessoes.terminar');
    Route::get('/pesquisa', \App\Http\Controllers\PesquisaGlobalController::class)->name('pesquisa.global');
    Route::get('/regras-avaliacao', [\App\Http\Controllers\RegrasAvaliacaoController::class, 'index'])->name('regras-avaliacao');

    Route::get('/executivo-ia', [\App\Http\Controllers\ExecutivoIaController::class, 'index'])->name('executivo-ia.index');
    Route::post('/executivo-ia/actualizar', [\App\Http\Controllers\ExecutivoIaController::class, 'actualizar'])->name('executivo-ia.actualizar');

    Route::get('/notificacoes', [\App\Http\Controllers\StaffNotificacaoController::class, 'index'])->name('notificacoes.index');
    Route::get('/notificacoes/polling', [\App\Http\Controllers\StaffNotificacaoController::class, 'polling'])->name('notificacoes.polling');
    Route::post('/notificacoes/marcar-todas-lidas', [\App\Http\Controllers\StaffNotificacaoController::class, 'marcarTodasLidas'])->name('notificacoes.marcar-todas-lidas');
    Route::get('/notificacoes/{id}/abrir', [\App\Http\Controllers\StaffNotificacaoController::class, 'abrir'])->name('notificacoes.abrir');
    Route::post('/notificacoes/{id}/lida', [\App\Http\Controllers\StaffNotificacaoController::class, 'marcarLida'])->name('notificacoes.marcar-lida');

    Route::post('/documentos/assinar-boletim/{aluno}', [\App\Http\Controllers\DocumentoAssinaturaController::class, 'assinarBoletim'])
        ->name('documentos.assinar-boletim');
    Route::post('/documentos/assinar-academico/{aluno}', [\App\Http\Controllers\DocumentoAssinaturaController::class, 'assinarDocumentoAcademico'])
        ->name('documentos.assinar-academico');

    // Assistente Virtual (IA)
    Route::prefix('assistente')->name('assistente.')->middleware('can:assistente.usar')->group(function () {
        Route::post('/mensagem', [AssistenteController::class, 'mensagem'])
            ->middleware('throttle:assistant-chat')
            ->name('mensagem');
        Route::get('/conversas', [AssistenteController::class, 'conversas'])->name('conversas');
        Route::post('/conversa/nova', [AssistenteController::class, 'novaConversa'])->name('conversa.nova');
        Route::get('/conversa/{conversa}/historico', [AssistenteController::class, 'historico'])->name('conversa.historico');
        Route::post('/mensagem/{mensagem}/feedback', [AssistenteController::class, 'feedback'])
            ->middleware('throttle:60,1')
            ->name('feedback');
        Route::get('/meta', [AssistenteController::class, 'meta'])->name('meta');
    });

    Route::get('/assistente/auditoria', [\App\Http\Controllers\AssistenteAuditoriaController::class, 'index'])
        ->name('assistente.auditoria');

    // Portal do Aluno
    Route::prefix('portal/aluno')->name('portal.aluno.')->middleware(['role:aluno', 'throttle:30,1'])->group(function () {
        Route::get('/', [\App\Http\Controllers\Portal\AlunoPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/notas', [\App\Http\Controllers\Portal\AlunoPortalController::class, 'notas'])->name('notas');
        Route::get('/horario', [\App\Http\Controllers\Portal\AlunoPortalController::class, 'horario'])->name('horario');
        Route::get('/horario/pdf', [\App\Http\Controllers\Portal\AlunoPortalController::class, 'horarioPdf'])->name('horario.pdf');
        Route::get('/provas', [\App\Http\Controllers\Portal\AlunoPortalController::class, 'provas'])->name('provas');
        Route::get('/provas/pdf', [\App\Http\Controllers\Portal\AlunoPortalController::class, 'provasPdf'])->name('provas.pdf');
        Route::get('/calendario', [\App\Http\Controllers\Portal\AlunoPortalController::class, 'calendario'])->name('calendario');
        Route::get('/materiais', [\App\Http\Controllers\Portal\AlunoPortalController::class, 'materiais'])->name('materiais');
        Route::get('/materiais/{material}/download', [\App\Http\Controllers\Portal\AlunoPortalController::class, 'materialDownload'])->name('materiais.download');
        Route::get('/assiduidade', [\App\Http\Controllers\Portal\AlunoPortalController::class, 'assiduidade'])->name('assiduidade');
        Route::get('/ocorrencias', [\App\Http\Controllers\Portal\AlunoPortalController::class, 'ocorrencias'])->name('ocorrencias');
        Route::get('/boletim/pdf', [\App\Http\Controllers\Portal\AlunoPortalController::class, 'boletimPdf'])->name('boletim.pdf');
        Route::get('/perfil', [\App\Http\Controllers\Portal\AlunoPortalController::class, 'perfil'])->name('perfil');
        Route::patch('/perfil/preferencias', [\App\Http\Controllers\Portal\AlunoPortalController::class, 'actualizarPreferencias'])->name('perfil.preferencias');
        Route::get('/executivo-ia', [\App\Http\Controllers\ExecutivoIaController::class, 'index'])->name('executivo-ia');
        Route::post('/executivo-ia/actualizar', [\App\Http\Controllers\ExecutivoIaController::class, 'actualizar'])->name('executivo-ia.actualizar');

        Route::get('/notificacoes', [\App\Http\Controllers\Portal\PortalNotificacaoController::class, 'index'])->name('notificacoes');
        Route::post('/notificacoes/marcar-todas-lidas', [\App\Http\Controllers\Portal\PortalNotificacaoController::class, 'marcarTodasLidas'])->name('notificacoes.marcar-todas-lidas');
        Route::get('/notificacoes/{id}/abrir', [\App\Http\Controllers\Portal\PortalNotificacaoController::class, 'abrir'])->name('notificacoes.abrir');
        Route::post('/notificacoes/{id}/lida', [\App\Http\Controllers\Portal\PortalNotificacaoController::class, 'marcarLida'])->name('notificacoes.marcar-lida');
    });

    // Portal do Encarregado
    Route::prefix('portal/encarregado')->name('portal.encarregado.')->middleware(['role:encarregado', 'throttle:30,1'])->group(function () {
        Route::get('/', [\App\Http\Controllers\Portal\EncarregadoPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/esta-semana', [\App\Http\Controllers\Portal\EncarregadoPortalController::class, 'resumoSemanal'])->name('resumo-semanal');
        Route::get('/notificacoes', [\App\Http\Controllers\Portal\EncarregadoNotificacaoController::class, 'index'])->name('notificacoes');
        Route::post('/notificacoes/marcar-todas-lidas', [\App\Http\Controllers\Portal\EncarregadoNotificacaoController::class, 'marcarTodasLidas'])->name('notificacoes.marcar-todas-lidas');
        Route::get('/notificacoes/{id}/abrir', [\App\Http\Controllers\Portal\EncarregadoNotificacaoController::class, 'abrir'])->name('notificacoes.abrir');
        Route::post('/notificacoes/{id}/lida', [\App\Http\Controllers\Portal\EncarregadoNotificacaoController::class, 'marcarLida'])->name('notificacoes.marcar-lida');
        Route::get('/filho/{aluno}', [\App\Http\Controllers\Portal\EncarregadoPortalController::class, 'filho'])->name('filho');
        Route::post('/filho/{aluno}/assinar-boletim', [\App\Http\Controllers\Portal\EncarregadoPortalController::class, 'assinarBoletim'])->name('assinar-boletim');
        Route::get('/filho/{aluno}/assiduidade', [\App\Http\Controllers\Portal\EncarregadoPortalController::class, 'assiduidade'])->name('assiduidade');
        Route::get('/filho/{aluno}/ocorrencias', [\App\Http\Controllers\Portal\EncarregadoPortalController::class, 'ocorrencias'])->name('ocorrencias');
        Route::get('/filho/{aluno}/linha-do-tempo', [\App\Http\Controllers\Portal\EncarregadoPortalController::class, 'timeline'])->name('filho.timeline');
        Route::get('/filho/{aluno}/agenda', [\App\Http\Controllers\Portal\EncarregadoPortalController::class, 'agenda'])->name('filho.agenda');
        Route::get('/filho/{aluno}/materiais', [\App\Http\Controllers\Portal\EncarregadoPortalController::class, 'materiais'])->name('filho.materiais');
        Route::get('/filho/{aluno}/materiais/{material}/download', [\App\Http\Controllers\Portal\EncarregadoPortalController::class, 'materialDownload'])->name('filho.materiais.download');
        Route::get('/mensagens', [\App\Http\Controllers\Portal\EncarregadoMensagemController::class, 'index'])->name('mensagens.index');
        Route::get('/filho/{aluno}/mensagens', [\App\Http\Controllers\Portal\EncarregadoMensagemController::class, 'show'])->name('mensagens.show');
        Route::post('/filho/{aluno}/mensagens', [\App\Http\Controllers\Portal\EncarregadoMensagemController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('mensagens.store');
        Route::get('/documentos', [\App\Http\Controllers\Portal\EncarregadoDocumentoPedidoController::class, 'index'])->name('documentos.index');
        Route::post('/documentos', [\App\Http\Controllers\Portal\EncarregadoDocumentoPedidoController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('documentos.store');
        Route::post('/documentos/{pedido}/cancelar', [\App\Http\Controllers\Portal\EncarregadoDocumentoPedidoController::class, 'cancelar'])->name('documentos.cancelar');
    });

    // ========================================
    // INFRAESTRUTURA (NÃO requer ano lectivo)
    // ========================================
    Route::resource('salas', SalaController::class);
    Route::resource('turnos', TurnoController::class);

    // Ocupacao de Infraestrutura (requer ano para filtro, mas permite acesso)
    Route::get('ocupacao', [OcupacaoController::class, 'index'])->name('ocupacao.index');
    Route::get('ocupacao/por-turno', [OcupacaoController::class, 'porTurno'])->name('ocupacao.por-turno');

    // Pauta Certificada / Radar / Encarregados (não exigem ano lectivo para abrir)
    Route::prefix('pauta-certificada')->name('pauta-certificada.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PautaCertificadaController::class, 'index'])->name('index');
        Route::post('/{turma}/analisar', [\App\Http\Controllers\PautaCertificadaController::class, 'analisar'])->name('analisar');
        Route::post('/{turma}/simular', [\App\Http\Controllers\PautaCertificadaController::class, 'simular'])->name('simular');
        Route::get('/{turma}/inspeccao', [\App\Http\Controllers\PautaCertificadaController::class, 'exportarInspeccao'])->name('inspeccao');
    });
    Route::get('/radar', [\App\Http\Controllers\RadarController::class, 'index'])->name('radar.index');
    Route::prefix('encarregados')->name('encarregados.')->group(function () {
        Route::get('/', [\App\Http\Controllers\EncarregadoController::class, 'index'])->name('index');
        Route::get('/criar', [\App\Http\Controllers\EncarregadoController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\EncarregadoController::class, 'store'])->name('store');
    });
    Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
        Route::get('/', [\App\Http\Controllers\WhatsAppTestController::class, 'index'])->name('index');
        Route::post('/simular', [\App\Http\Controllers\WhatsAppTestController::class, 'simularEntrada'])->name('simular');
        Route::post('/enviar', [\App\Http\Controllers\WhatsAppTestController::class, 'enviar'])->name('enviar');
        Route::post('/template', [\App\Http\Controllers\WhatsAppTestController::class, 'template'])->name('template');
        Route::post('/demo', [\App\Http\Controllers\WhatsAppTestController::class, 'criarDemo'])->name('demo');
        Route::post('/testar', [\App\Http\Controllers\WhatsAppTestController::class, 'testarLigacao'])->name('testar');
    });

    // ========================================
    // GESTÃO ACADÉMICA (REQUER ano lectivo)
    // ========================================
    Route::middleware('ano.lectivo.required')->group(function () {
        // Cursos e Disciplinas
        Route::resource('cursos', CursoController::class);
        Route::put('cursos/{curso}/disciplinas', [CursoController::class, 'actualizarDisciplinas'])
            ->name('cursos.disciplinas.actualizar');
        Route::resource('disciplinas', DisciplinaController::class);

        // Turmas
        Route::resource('turmas', TurmaController::class);

        // Horários de aula e calendário de provas
        Route::prefix('horarios')->name('horarios.')->middleware('can:horarios.ver')->group(function () {
            Route::get('/', [\App\Http\Controllers\HorarioTurmaController::class, 'index'])->name('index');
            Route::get('/{turma}/pdf', [\App\Http\Controllers\HorarioTurmaController::class, 'pdf'])->name('pdf');
            Route::get('/{turma}', [\App\Http\Controllers\HorarioTurmaController::class, 'show'])->name('show');
            Route::get('/{turma}/editar', [\App\Http\Controllers\HorarioTurmaController::class, 'edit'])->name('edit');
            Route::put('/{turma}/config', [\App\Http\Controllers\HorarioTurmaController::class, 'updateConfig'])
                ->middleware('can:horarios.gerir')
                ->name('config');
            Route::post('/{turma}', [\App\Http\Controllers\HorarioTurmaController::class, 'store'])
                ->middleware('can:horarios.gerir')
                ->name('store');
            Route::post('/{turma}/aprovar', [\App\Http\Controllers\HorarioTurmaController::class, 'aprovar'])
                ->middleware('can:horarios.aprovar')
                ->name('aprovar');
            Route::post('/{turma}/reabrir', [\App\Http\Controllers\HorarioTurmaController::class, 'reabrir'])
                ->middleware('can:horarios.aprovar')
                ->name('reabrir');
            Route::delete('/slot/{horario}', [\App\Http\Controllers\HorarioTurmaController::class, 'destroy'])
                ->middleware('can:horarios.gerir')
                ->name('destroy');
        });

        Route::prefix('calendario-provas')->name('calendario-provas.')->middleware('can:calendario_provas.ver')->group(function () {
            Route::get('/', [\App\Http\Controllers\CalendarioProvaController::class, 'index'])->name('index');
            Route::get('/{turma}/pdf', [\App\Http\Controllers\CalendarioProvaController::class, 'pdf'])->name('pdf');
            Route::put('/{turma}/config', [\App\Http\Controllers\CalendarioProvaController::class, 'updateConfig'])
                ->middleware('can:calendario_provas.gerir')
                ->name('config');
            Route::post('/gerar-ia', [\App\Http\Controllers\CalendarioProvaController::class, 'gerarIa'])
                ->middleware('can:calendario_provas.ia.gerar')
                ->name('gerar-ia');
            Route::post('/', [\App\Http\Controllers\CalendarioProvaController::class, 'store'])
                ->middleware('can:calendario_provas.gerir')
                ->name('store');
            Route::delete('/{prova}', [\App\Http\Controllers\CalendarioProvaController::class, 'destroy'])
                ->middleware('can:calendario_provas.gerir')
                ->name('destroy');
        });

        // Agenda escolar
        Route::prefix('agenda')->name('agenda.')->middleware('can:agenda.ver')->group(function () {
            Route::get('/', [\App\Http\Controllers\AgendaController::class, 'index'])->name('index');
            Route::get('/criar', [\App\Http\Controllers\AgendaController::class, 'create'])
                ->middleware('can:agenda.gerir')->name('create');
            Route::post('/', [\App\Http\Controllers\AgendaController::class, 'store'])
                ->middleware('can:agenda.gerir')->name('store');
            Route::delete('/{agenda}', [\App\Http\Controllers\AgendaController::class, 'destroy'])
                ->middleware('can:agenda.gerir')->name('destroy');
        });

        // Materiais académicos
        Route::prefix('materiais')->name('materiais.')->middleware('can:materiais.ver')->group(function () {
            Route::get('/', [\App\Http\Controllers\MaterialAcademicoController::class, 'index'])->name('index');
            Route::get('/criar', [\App\Http\Controllers\MaterialAcademicoController::class, 'create'])
                ->middleware('can:materiais.gerir')->name('create');
            Route::get('/gerar-ia', [\App\Http\Controllers\MaterialAcademicoController::class, 'gerarIaForm'])
                ->middleware(['can:materiais.gerir', 'can:pedagogico.ia.gerar'])->name('gerar-ia');
            Route::post('/gerar-ia', [\App\Http\Controllers\MaterialAcademicoController::class, 'gerarIa'])
                ->middleware(['throttle:ai-generation', 'can:materiais.gerir', 'can:pedagogico.ia.gerar'])->name('gerar-ia.store');
            Route::post('/', [\App\Http\Controllers\MaterialAcademicoController::class, 'store'])
                ->middleware('can:materiais.gerir')->name('store');
            Route::get('/{material}/download', [\App\Http\Controllers\MaterialAcademicoController::class, 'download'])->name('download');
            Route::delete('/{material}', [\App\Http\Controllers\MaterialAcademicoController::class, 'destroy'])
                ->middleware('can:materiais.gerir')->name('destroy');
        });

        // Alunos
        Route::get('alunos/import/template', [AlunoController::class, 'downloadTemplate'])->name('alunos.download-template');
        Route::get('alunos/import/errors', [AlunoController::class, 'downloadErrors'])->name('alunos.download-errors');
        Route::get('alunos/import', [AlunoController::class, 'import'])->name('alunos.import');
        Route::post('alunos/import/preview', [AlunoController::class, 'importPreview'])->name('alunos.import.preview');
        Route::post('alunos/import', [AlunoController::class, 'importStore'])->name('alunos.import.store');

        // Transição de Alunos Entre Anos Lectivos (ANTES do resource para evitar conflito com /alunos/{aluno})
        Route::prefix('alunos/transicao')->name('alunos.transicao.')->group(function () {
            Route::get('/', [AlunoTransicaoController::class, 'index'])->name('index');
            Route::post('/preview', [AlunoTransicaoController::class, 'preview'])->name('preview');
            Route::post('/execute', [AlunoTransicaoController::class, 'execute'])->name('execute');
        });

        // Atualização de Situação Acadêmica (ANTES do resource)
        Route::patch('alunos/{aluno}/situacao', [AlunoController::class, 'updateSituacao'])
            ->name('alunos.update-situacao');

        Route::post('alunos/{aluno}/arquivar', [AlunoController::class, 'arquivar'])
            ->name('alunos.arquivar');
        Route::post('alunos/{aluno}/enviar-email', [AlunoController::class, 'enviarEmail'])
            ->name('alunos.enviar-email');

        Route::post('alunos/{aluno}/toggle-consulta-portal', [AlunoController::class, 'toggleConsultaPortal'])
            ->name('alunos.toggle-consulta-portal');

        Route::post('alunos/{aluno}/repor-palavra-passe', [AlunoController::class, 'reporPalavraPasse'])
            ->name('alunos.repor-palavra-passe');

        // Transferência de Turma (ANTES do resource)
        Route::get('alunos/{aluno}/transferir', [AlunoController::class, 'transferForm'])
            ->name('alunos.transfer-form');
        Route::put('alunos/{aluno}/transferir', [AlunoController::class, 'transfer'])
            ->name('alunos.transfer');

        // Notas Arquivadas (sistema de recuperação reversível)
        Route::get('alunos/{aluno}/notas-arquivadas', [\App\Http\Controllers\NotaArchivedController::class, 'index'])
            ->name('alunos.notas-arquivadas')
            ->middleware('can:notas.ver');
        Route::post('notas/{nota}/restore', [\App\Http\Controllers\NotaArchivedController::class, 'restore'])
            ->name('notas.restore')
            ->middleware('can:notas.editar');
        Route::delete('notas/{nota}/force-delete', [\App\Http\Controllers\NotaArchivedController::class, 'forceDelete'])
            ->name('notas.force-delete')
            ->middleware('role:admin_full');

        Route::resource('alunos', AlunoController::class);

        // Secretaria académica
        Route::prefix('secretaria')->name('secretaria.')->group(function () {
            Route::get('/', SecretariaDashboardController::class)->name('dashboard');

            Route::get('matriculas', [SecretariaMatriculaController::class, 'index'])->name('matriculas.index');
            Route::get('matriculas/create', [SecretariaMatriculaController::class, 'create'])->name('matriculas.create');
            Route::post('matriculas', [SecretariaMatriculaController::class, 'store'])->name('matriculas.store');
            Route::get('matriculas/import', [SecretariaMatriculaController::class, 'importForm'])->name('matriculas.import');
            Route::get('matriculas/import/template', [SecretariaMatriculaController::class, 'downloadTemplate'])->name('matriculas.import.template');
            Route::post('matriculas/import/preview', [SecretariaMatriculaController::class, 'importPreview'])->name('matriculas.import.preview');
            Route::post('matriculas/import', [SecretariaMatriculaController::class, 'importStore'])->name('matriculas.import.store');
            Route::get('matriculas/{aluno}/historico', [SecretariaMatriculaController::class, 'historico'])->name('matriculas.historico');
            Route::get('matriculas/{aluno}/renovar', [SecretariaMatriculaController::class, 'renovarForm'])->name('matriculas.renovar');
            Route::post('matriculas/{aluno}/renovar', [SecretariaMatriculaController::class, 'renovar'])->name('matriculas.renovar.store');

            Route::get('distribuicao', [\App\Http\Controllers\Secretaria\DistribuicaoController::class, 'create'])->name('distribuicao.create');
            Route::post('distribuicao', [\App\Http\Controllers\Secretaria\DistribuicaoController::class, 'store'])->name('distribuicao.store');

            Route::get('campanhas', [\App\Http\Controllers\Secretaria\CampanhaNotificacaoController::class, 'index'])->name('campanhas.index');
            Route::get('campanhas/create', [\App\Http\Controllers\Secretaria\CampanhaNotificacaoController::class, 'create'])->name('campanhas.create');
            Route::post('campanhas', [\App\Http\Controllers\Secretaria\CampanhaNotificacaoController::class, 'store'])->name('campanhas.store');

            Route::get('documentos', [SecretariaDocumentoController::class, 'index'])->name('documentos.index');
            Route::get('documentos/pedidos', [\App\Http\Controllers\Secretaria\DocumentoPedidoController::class, 'index'])->name('documentos.pedidos');
            Route::patch('documentos/pedidos/{pedido}', [\App\Http\Controllers\Secretaria\DocumentoPedidoController::class, 'actualizar'])->name('documentos.pedidos.actualizar');
            Route::get('documentos/configurar', [SecretariaDocumentoController::class, 'configurar'])->name('documentos.configurar');
            Route::post('documentos/configurar', [SecretariaDocumentoController::class, 'guardarConfig'])->name('documentos.configurar.guardar');
            Route::get('documentos/emitir', [SecretariaDocumentoController::class, 'emitir'])->name('documentos.emitir');
            Route::post('alunos/{aluno}/documentos', [SecretariaDocumentoController::class, 'storeDocumento'])->name('alunos.documentos.store');
            Route::get('alunos/{aluno}/documentos/{documento}/download', [SecretariaDocumentoController::class, 'downloadDocumento'])->name('alunos.documentos.download');
            Route::delete('alunos/{aluno}/documentos/{documento}', [SecretariaDocumentoController::class, 'destroyDocumento'])->name('alunos.documentos.destroy');

            Route::get('ajax/alunos-por-turma', function (\Illuminate\Http\Request $request) {
                abort_unless(auth()->user()?->can('secretaria.ver') || auth()->user()?->can('alunos.ver'), 403);
                $turmaId = (int) $request->get('turma_id');
                $alunos = \App\Models\Aluno::query()
                    ->where('turma_id', $turmaId)
                    ->activo()
                    ->orderBy('nome')
                    ->get(['id', 'nome', 'numero_processo']);

                return response()->json($alunos);
            })->name('ajax.alunos-por-turma');

            Route::get('cartao/configurar', [SecretariaCartaoController::class, 'configurar'])->name('cartao.configurar');
            Route::post('cartao/configurar', [SecretariaCartaoController::class, 'guardar'])->name('cartao.guardar');
            Route::get('cartao/emitir', [SecretariaCartaoController::class, 'emitirForm'])->name('cartao.emitir');
            Route::post('cartao/emitir', [SecretariaCartaoController::class, 'emitir'])->name('cartao.emitir.store');

            Route::get('boletim/configurar', [SecretariaBoletimConfigController::class, 'configurar'])->name('boletim.configurar');
            Route::post('boletim/configurar', [SecretariaBoletimConfigController::class, 'guardar'])->name('boletim.guardar');
        });

        // Assiduidade & Disciplina (Fase B)
        Route::prefix('presencas')->name('presencas.')->middleware('can:presencas.ver')->group(function () {
            Route::get('/', [PresencaController::class, 'index'])->name('index');
            Route::get('/aula/{horario}', [PresencaController::class, 'aula'])->name('aula');
            Route::post('/aula/{horario}', [PresencaController::class, 'store'])
                ->middleware('can:presencas.lancar')
                ->name('store');
            Route::get('/registo/{aula}', [PresencaController::class, 'show'])->name('show');
            Route::post('/registo/{registo}/justificar', [PresencaController::class, 'justificar'])
                ->middleware('can:presencas.justificar')
                ->name('justificar');
        });

        Route::prefix('ocorrencias')->name('ocorrencias.')->middleware('can:ocorrencias.ver')->group(function () {
            Route::get('/', [OcorrenciaController::class, 'index'])->name('index');
            Route::get('/criar', [OcorrenciaController::class, 'create'])
                ->middleware('can:ocorrencias.registar')
                ->name('create');
            Route::post('/', [OcorrenciaController::class, 'store'])
                ->middleware('can:ocorrencias.registar')
                ->name('store');
            Route::get('/{ocorrencia}', [OcorrenciaController::class, 'show'])->name('show');
            Route::post('/{ocorrencia}/resolver', [OcorrenciaController::class, 'resolver'])
                ->middleware('can:ocorrencias.resolver')
                ->name('resolver');
        });

        Route::get('inspetor', [InspetorPainelController::class, 'index'])->name('inspetor.painel');

        Route::prefix('mensagens-encarregados')->name('mensagens-encarregados.')->group(function () {
            Route::get('/', [\App\Http\Controllers\MensagemEncarregadoController::class, 'index'])->name('index');
            Route::get('/nova', [\App\Http\Controllers\MensagemEncarregadoController::class, 'create'])->name('create');
            Route::post('/iniciar', [\App\Http\Controllers\MensagemEncarregadoController::class, 'iniciar'])
                ->middleware('throttle:20,1')
                ->name('iniciar');
            Route::get('/{conversa}', [\App\Http\Controllers\MensagemEncarregadoController::class, 'show'])->name('show');
            Route::post('/{conversa}', [\App\Http\Controllers\MensagemEncarregadoController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('store');
        });

        Route::prefix('mensagens-internas')->name('mensagens-internas.')->group(function () {
            Route::post('/iniciar', [\App\Http\Controllers\MensagemInternaController::class, 'iniciar'])
                ->middleware('throttle:20,1')
                ->name('iniciar');
            Route::get('/{conversa}', [\App\Http\Controllers\MensagemInternaController::class, 'show'])->name('show');
            Route::post('/{conversa}', [\App\Http\Controllers\MensagemInternaController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('store');
        });

        // Associações Professor-Disciplina-Turma
        Route::get('associacoes/disciplinas', [AssociacaoController::class, 'getDisciplinas'])->name('associacoes.disciplinas');
        Route::get('associacoes/grupo/ver', [AssociacaoController::class, 'showGrupo'])->name('associacoes.show-grupo');
        Route::get('associacoes/grupo/editar', [AssociacaoController::class, 'editGrupo'])->name('associacoes.edit-grupo');
        Route::put('associacoes/grupo/atualizar', [AssociacaoController::class, 'updateGrupo'])->name('associacoes.update-grupo');
        Route::delete('associacoes/grupo/eliminar', [AssociacaoController::class, 'deleteGrupo'])->name('associacoes.delete-grupo');
        Route::resource('associacoes', AssociacaoController::class)->parameters([
            'associacoes' => 'associacao',
        ]);

        // Directores de Turma e Coordenadores de Curso
        // Substituição rápida (mantém curso/turma e ano, troca apenas o utilizador)
        Route::patch('directores-turma/{directoresTurma}/substituir', [DirectorTurmaController::class, 'substituir'])
            ->name('directores-turma.substituir');
        Route::patch('coordenadores-curso/{coordenadoresCurso}/substituir', [CoordenadorCursoController::class, 'substituir'])
            ->name('coordenadores-curso.substituir');

        Route::resource('directores-turma', DirectorTurmaController::class);
        Route::resource('coordenadores-curso', CoordenadorCursoController::class);
        Route::resource('coordenadores-disciplina', CoordenadorDisciplinaController::class)
            ->parameters(['coordenadores-disciplina' => 'coordenadoresDisciplina'])
            ->only(['index', 'create', 'store', 'destroy']);

        // ========================================
        // SISTEMA DE AVALIAÇÃO (REQUER ano lectivo)
        // ========================================
        // Lançamento de Notas
        Route::get('notas/lancar', [NotaController::class, 'lancar'])->name('notas.lancar');
        Route::get('notas/lancar-pg', [NotaController::class, 'lancarPG'])->name('notas.lancar-pg');
        Route::post('notas/store-pg', [NotaController::class, 'storePG'])->name('notas.store-pg');
        Route::get('notas/disciplinas', [NotaController::class, 'getDisciplinas'])->name('notas.disciplinas');

        // Folha completa (modo unificado em notas.lancar?modo=folha); GET legado redirecciona
        Route::get('notas/lancamento-lote', [NotaController::class, 'lancamentoLoteForm'])->name('notas.lancamento-lote');
        Route::post('notas/lancamento-lote', [NotaController::class, 'lancamentoLoteStore'])->name('notas.lancamento-lote.store');

        // Importação via Excel
        Route::get('notas/importacao', [NotaController::class, 'importacaoForm'])->name('notas.importacao');
        Route::post('notas/importacao', [NotaController::class, 'importacaoStore'])->name('notas.importacao.store');
        Route::get('notas/importacao/template', [NotaController::class, 'downloadTemplate'])->name('notas.importacao.template');

        Route::resource('notas', NotaController::class);

        // Mini-Pautas (Professores)
        Route::prefix('mini-pautas')->name('mini-pautas.')->middleware('can:mini-pautas.ver')->group(function () {
            Route::get('/', [\App\Http\Controllers\MiniPautaController::class, 'index'])->name('index');
            Route::get('/visualizar', [\App\Http\Controllers\MiniPautaController::class, 'show'])->name('show');
            Route::get('/disciplinas', [\App\Http\Controllers\MiniPautaController::class, 'getDisciplinas'])->name('disciplinas');
            Route::get('/importacao', [\App\Http\Controllers\MiniPautaController::class, 'importacaoForm'])->name('importacao');
            Route::post('/importacao', [\App\Http\Controllers\MiniPautaController::class, 'importacaoStore'])->name('importacao.store');

            // Cálculos
            Route::post('/calcular-medias-trimestre', [\App\Http\Controllers\MiniPautaController::class, 'calcularMediasTrimestre'])->name('calcular-medias-trimestre');
            Route::post('/calcular-notas-finais', [\App\Http\Controllers\MiniPautaController::class, 'calcularNotasFinais'])->name('calcular-notas-finais');
            Route::post('/calcular-mft2', [\App\Http\Controllers\MiniPautaController::class, 'calcularMFT2'])->name('calcular-mft2');
            Route::post('/calcular-cf', [\App\Http\Controllers\MiniPautaController::class, 'calcularCF'])->name('calcular-cf');
            Route::post('/calcular-ca', [\App\Http\Controllers\MiniPautaController::class, 'calcularCA'])->name('calcular-ca');
            Route::post('/calcular-cfd', [\App\Http\Controllers\MiniPautaController::class, 'calcularCFD'])->name('calcular-cfd');
        });

        // Rota de compatibilidade
        Route::get('pautas/mini-pauta', function () {
            return redirect()->route('mini-pautas.index', request()->all());
        })->name('pautas.mini-pauta');

        // Pautas Administrativas (Directores, Coordenadores)
        Route::prefix('pautas')->name('pautas.')->middleware('can:pautas.ver')->group(function () {
            Route::get('/', [PautaController::class, 'index'])->name('index');
            Route::get('/disciplinas', [PautaController::class, 'getDisciplinas'])->name('disciplinas');
            Route::get('/final-disciplina', [PautaController::class, 'pautaFinalDisciplina'])->name('final-disciplina');
            Route::get('/final-turma', [PautaController::class, 'pautaFinalTurma'])->name('final-turma');
            Route::get('/final-completa-detalhada', [PautaController::class, 'pautaFinalCompletaDetalhada'])->name('final-completa-detalhada');
            // Pauta de aproveitamento trimestral (layout oficial da escola)
            Route::get('/trimestral-completa', [PautaController::class, 'pautaTrimestralCompleta'])->name('trimestral-completa');
            Route::post('/aprovar', [PautaController::class, 'aprovar'])
                ->middleware('can:pautas.aprovar')
                ->name('aprovar');
            Route::post('/reabrir', [PautaController::class, 'reabrir'])
                ->middleware('can:pautas.aprovar')
                ->name('reabrir');
            // Cálculos finais (MFT2, CF, CA, CFD) — também usados na pauta final por disciplina
            Route::post('/calcular-notas-finais', [\App\Http\Controllers\MiniPautaController::class, 'calcularNotasFinais'])->name('calcular-notas-finais');
            Route::post('/calcular-mft2', [\App\Http\Controllers\MiniPautaController::class, 'calcularMFT2'])->name('calcular-mft2');
            Route::post('/calcular-cf', [\App\Http\Controllers\MiniPautaController::class, 'calcularCF'])->name('calcular-cf');
            Route::post('/calcular-ca', [\App\Http\Controllers\MiniPautaController::class, 'calcularCA'])->name('calcular-ca');
            Route::post('/calcular-cfd', [\App\Http\Controllers\MiniPautaController::class, 'calcularCFD'])->name('calcular-cfd');
        });

        // CFDs de disciplinas terminais de anos anteriores (lançamento manual)
        Route::prefix('cfds-anteriores')->name('cfds-anteriores.')->middleware('can:pautas.ver')->group(function () {
            Route::get('/', [\App\Http\Controllers\CfdAnteriorController::class, 'index'])->name('index');
            Route::get('/{turma}/editar', [\App\Http\Controllers\CfdAnteriorController::class, 'editar'])->name('editar');
            Route::post('/{turma}/guardar', [\App\Http\Controllers\CfdAnteriorController::class, 'guardar'])->name('guardar');
        });

        // Exame de Recuperação (10.ª/11.ª, trimestral) — Art.º 30.º DE 424/25
        Route::prefix('recuperacoes')->name('recuperacoes.')->group(function () {
            Route::get('/', [\App\Http\Controllers\RecuperacaoController::class, 'index'])->name('index');
            Route::get('/{turma}/lancar', [\App\Http\Controllers\RecuperacaoController::class, 'lancar'])->name('lancar');
            Route::post('/{turma}/guardar', [\App\Http\Controllers\RecuperacaoController::class, 'guardar'])->name('guardar');
            Route::post('/{turma}/simular/{aluno}', [\App\Http\Controllers\SimuladorTransicaoController::class, 'simular'])->name('simular');
        });

        // Lista consolidada: alunos sujeitos a recuperação e recurso
        Route::get('exames/disciplinas', [\App\Http\Controllers\ExameElegibilidadeController::class, 'getDisciplinas'])
            ->name('exames.disciplinas');
        Route::get('exames/elegibilidade', [\App\Http\Controllers\ExameElegibilidadeController::class, 'index'])
            ->name('exames.elegibilidade');

        // Exame de Recurso (12.ª classe, 3.º trimestre) — Art.º 31.º DE 424/25
        Route::prefix('recursos')->name('recursos.')->group(function () {
            Route::get('/', [\App\Http\Controllers\RecursoController::class, 'index'])->name('index');
            Route::get('/{turma}/lancar', [\App\Http\Controllers\RecursoController::class, 'lancar'])->name('lancar');
            Route::post('/{turma}/guardar', [\App\Http\Controllers\RecursoController::class, 'guardar'])->name('guardar');
            Route::post('/{turma}/simular/{aluno}', [\App\Http\Controllers\SimuladorTransicaoController::class, 'simular'])->name('simular');
        });

        // Exame de Melhoria de Nota — Art.º 34.º DE 424/25
        Route::prefix('melhorias')->name('melhorias.')->middleware('can:pautas.ver')->group(function () {
            Route::get('/', [\App\Http\Controllers\MelhoriaController::class, 'index'])->name('index');
            Route::get('/{turma}/lancar', [\App\Http\Controllers\MelhoriaController::class, 'lancar'])->name('lancar');
            Route::post('/{turma}/guardar', [\App\Http\Controllers\MelhoriaController::class, 'guardar'])->name('guardar');
        });

        // Exame Extraordinário — Art.º 32.º DE 424/25
        Route::prefix('extraordinarios')->name('extraordinarios.')->middleware('can:pautas.ver')->group(function () {
            Route::get('/', [\App\Http\Controllers\ExtraordinarioController::class, 'index'])->name('index');
            Route::get('/{turma}/lancar', [\App\Http\Controllers\ExtraordinarioController::class, 'lancar'])->name('lancar');
            Route::post('/{turma}/guardar', [\App\Http\Controllers\ExtraordinarioController::class, 'guardar'])->name('guardar');
        });

        // Conselhos de Notas
        Route::prefix('conselhos')->name('conselhos.')->middleware('can:conselhos.ver')->group(function () {
            Route::get('/', [ConselhoController::class, 'index'])->name('index');
            Route::get('/disciplinas', [ConselhoController::class, 'getDisciplinas'])->name('disciplinas');
            Route::get('/{conselho}', [ConselhoController::class, 'show'])->name('show');
            Route::post('/{conselho}/iniciar', [ConselhoController::class, 'iniciar'])->name('iniciar');
            Route::post('/{conselho}/ajustar', [ConselhoController::class, 'ajustar'])->name('ajustar');
            Route::post('/{conselho}/concluir', [ConselhoController::class, 'concluir'])->name('concluir');
            Route::delete('/ajustes/{ajuste}/reverter', [ConselhoController::class, 'reverterAjuste'])->name('ajustes.reverter');
            Route::get('/{conselho}/estatisticas', [ConselhoController::class, 'estatisticas'])->name('estatisticas');
        });

        // Gestao de Arquivos de Pautas
        Route::prefix('pauta-arquivos')->name('pauta-arquivos.')->group(function () {
            Route::get('/', [PautaArquivoController::class, 'index'])->name('index');
            Route::get('/criar', [PautaArquivoController::class, 'create'])->name('create');
            Route::post('/', [PautaArquivoController::class, 'store'])->name('store');
            Route::post('/arquivar-sistema', [PautaArquivoController::class, 'arquivarDoSistema'])->name('arquivar-sistema');
            Route::get('/{pautaArquivo}', [PautaArquivoController::class, 'show'])->name('show');
            Route::get('/{pautaArquivo}/editar', [PautaArquivoController::class, 'edit'])->name('edit');
            Route::put('/{pautaArquivo}', [PautaArquivoController::class, 'update'])->name('update');
            Route::delete('/{pautaArquivo}', [PautaArquivoController::class, 'destroy'])->name('destroy');
            Route::delete('/{pautaArquivo}/force', [PautaArquivoController::class, 'forceDelete'])->name('force-delete');

            // Download
            Route::get('/{pautaArquivo}/download', [PautaArquivoController::class, 'download'])->name('download');

            // API Endpoints
            Route::get('/api/disciplinas', [PautaArquivoController::class, 'getDisciplinas'])->name('disciplinas');
            Route::get('/api/turmas', [PautaArquivoController::class, 'getTurmas'])->name('turmas');
        });

        // ========================================
        // RELATÓRIOS E EXPORTAÇÕES (REQUER ano lectivo)
        // ========================================
        // Interfaces de Relatórios
        Route::prefix('relatorios')->name('relatorios.')->group(function () {
            Route::get('/boletins', [RelatorioController::class, 'boletins'])->name('boletins');
            Route::get('/boletins-lote', [RelatorioController::class, 'boletinsLote'])->name('boletins-lote');
            Route::get('/pautas', [RelatorioController::class, 'pautas'])->name('pautas');
            Route::get('/lista-alunos', [RelatorioController::class, 'listaAlunos'])->name('lista-alunos');

            // Relatório Académico (coordenadores, subdirector, directora, admin)
            Route::get('/academico', [\App\Http\Controllers\RelatorioAcademicoController::class, 'index'])
                ->name('academico')
                ->middleware('can:relatorios.academico');
            Route::get('/academico/pdf', [\App\Http\Controllers\RelatorioAcademicoController::class, 'exportPdf'])
                ->name('academico-pdf')
                ->middleware('can:relatorios.academico');
        });

        // Exportações (com rate limiting para prevenir sobrecarga)
        Route::prefix('export')->name('export.')->middleware('throttle:exports')->group(function () {
            // Boletim individual (20 req/min)
            Route::get('boletim/{aluno}/pdf', [ExportController::class, 'boletimPdf'])->name('boletim.pdf');
            Route::get('boletim/{aluno}/excel', [ExportController::class, 'boletimExcel'])->name('boletim.excel');

            // Boletins de toda a turma num único PDF — 2 boletins por folha A4 (poupa papel)
            Route::get('boletins-turma/pdf', [ExportController::class, 'boletinsTurmaPdf'])->name('boletins-turma.pdf');

            // Mini-pauta (20 req/min)
            Route::get('mini-pauta/pdf', [ExportController::class, 'miniPautaPdf'])->name('mini-pauta.pdf');
            Route::get('mini-pauta/excel', [ExportController::class, 'miniPautaExcel'])->name('mini-pauta.excel');
            Route::get('mini-pauta-anual/pdf', [ExportController::class, 'miniPautaAnualPdf'])->name('mini-pauta-anual.pdf');
            Route::get('mini-pauta-anual/excel', [ExportController::class, 'miniPautaAnualExcel'])->name('mini-pauta-anual.excel');

            // Pauta final disciplina (20 req/min)
            Route::get('pauta-final-disciplina/pdf', [ExportController::class, 'pautaFinalDisciplinaPdf'])->name('pauta-final-disciplina.pdf');
            Route::get('pauta-final-disciplina/excel', [ExportController::class, 'pautaFinalDisciplinaExcel'])->name('pauta-final-disciplina.excel');

            // Pauta final turma (20 req/min)
            Route::get('pauta-final-turma/pdf', [ExportController::class, 'pautaFinalTurmaPdf'])->name('pauta-final-turma.pdf');
            Route::get('pauta-final-turma/excel', [ExportController::class, 'pautaFinalTurmaExcel'])->name('pauta-final-turma.excel');

            // Pauta final completa detalhada (20 req/min)
            Route::get('pauta-final-completa/pdf', [ExportController::class, 'pautaFinalCompletaPdf'])->name('pauta-final-completa.pdf');
            Route::get('pauta-final-completa/excel', [ExportController::class, 'pautaFinalCompletaExcel'])->name('pauta-final-completa.excel');

            // Pauta trimestral completa — layout oficial da escola (1 trimestre, todas disciplinas)
            Route::get('pauta-trimestral-completa/excel', [ExportController::class, 'pautaTrimestralCompletaExcel'])->name('pauta-trimestral-completa.excel');

            // Listas por resultado de transição (T3, 10.ª–12.ª)
            Route::get('lista-transicao-turma/pdf', [ExportController::class, 'listaTransicaoTurmaPdf'])->name('lista-transicao-turma.pdf');
            Route::get('lista-transicao-turma/excel', [ExportController::class, 'listaTransicaoTurmaExcel'])->name('lista-transicao-turma.excel');

            // Lista de alunos (20 req/min)
            Route::get('lista-alunos/pdf', [ExportController::class, 'listaAlunosPdf'])->name('lista-alunos.pdf');
            Route::get('lista-alunos/excel', [ExportController::class, 'listaAlunosExcel'])->name('lista-alunos.excel');
            Route::get('livro-turma/pdf', [ExportController::class, 'livroTurmaPdf'])
                ->middleware('can:alunos.ver')
                ->name('livro-turma.pdf');
            Route::get('etiquetas-alunos/pdf', [ExportController::class, 'etiquetasAlunosPdf'])
                ->middleware('can:alunos.ver')
                ->name('etiquetas-alunos.pdf');

            // Boletins em lote (limite mais restritivo: 5 req/min)
            Route::post('boletins-em-lote', [ExportController::class, 'boletinsEmLote'])->name('boletins-em-lote')->middleware('throttle:exports-batch');
            Route::get('boletins-em-lote/estado', [ExportController::class, 'boletinsEmLoteEstado'])->name('boletins-em-lote.estado');
            Route::get('boletins-em-lote/download', [ExportController::class, 'boletinsEmLoteDownload'])->name('boletins-em-lote.download');
        });

        // ========================================
        // ESTATÍSTICAS (REQUER ano lectivo)
        // ========================================
        Route::prefix('estatisticas')->name('estatisticas.')->group(function () {
            Route::get('/', [EstatisticasController::class, 'index'])->name('index');
            Route::get('/export-pdf', [EstatisticasController::class, 'exportPdf'])->name('export-pdf');
            Route::post('/consolidar', [EstatisticasController::class, 'consolidar'])->name('consolidar');
            Route::get('/mapa-aproveitamento', [EstatisticasController::class, 'mapaAproveitamento'])->name('mapa-aproveitamento');
            Route::get('/mapa-aproveitamento/pdf', [EstatisticasController::class, 'mapaAproveitamentoPdf'])->name('mapa-aproveitamento.pdf');
        });

        // ========================================
        // ASSISTENTE PEDAGÓGICO (REQUER ano lectivo)
        // ========================================
        Route::prefix('pedagogico')->name('pedagogico.')->middleware('can:pedagogico.ver')->group(function () {
            Route::get('/', [PedagogicoController::class, 'index'])->name('index');

            Route::prefix('planificacoes')->name('planificacoes.')->group(function () {
                Route::get('/', [PlanificacaoController::class, 'index'])->name('index');
                Route::get('/create', [PlanificacaoController::class, 'create'])
                    ->middleware('can:pedagogico.planificacao.criar')->name('create');
                Route::post('/', [PlanificacaoController::class, 'store'])
                    ->middleware('can:pedagogico.planificacao.criar')->name('store');
                Route::get('/ajax/disciplinas', [PlanificacaoController::class, 'getDisciplinas'])->name('disciplinas');
                Route::get('/ajax/professores', [PlanificacaoController::class, 'getProfessores'])->name('professores');
                Route::get('/{planificacao}', [PlanificacaoController::class, 'show'])->name('show');
                Route::get('/{planificacao}/edit', [PlanificacaoController::class, 'edit'])
                    ->middleware('can:pedagogico.planificacao.editar')->name('edit');
                Route::put('/{planificacao}', [PlanificacaoController::class, 'update'])
                    ->middleware('can:pedagogico.planificacao.editar')->name('update');
                Route::delete('/{planificacao}', [PlanificacaoController::class, 'destroy'])
                    ->middleware('can:pedagogico.planificacao.editar')->name('destroy');
                Route::post('/{planificacao}/quinzenas', [PlanificacaoController::class, 'storeQuinzenas'])
                    ->middleware('can:pedagogico.planificacao.editar')->name('store-quinzenas');
                Route::post('/{planificacao}/horarios', [PlanificacaoController::class, 'storeHorarios'])
                    ->middleware('can:pedagogico.planificacao.editar')->name('store-horarios');
                Route::post('/{planificacao}/activar', [PlanificacaoController::class, 'activar'])
                    ->middleware('can:pedagogico.planificacao.editar')->name('activar');
                Route::post('/{planificacao}/submeter', [PlanificacaoController::class, 'submeter'])
                    ->middleware('can:pedagogico.planificacao.editar')->name('submeter');
                Route::post('/{planificacao}/aprovar', [PlanificacaoController::class, 'aprovar'])
                    ->middleware('can:pedagogico.aprovar')->name('aprovar');
                Route::post('/{planificacao}/rejeitar', [PlanificacaoController::class, 'rejeitar'])
                    ->middleware('can:pedagogico.aprovar')->name('rejeitar');
                Route::post('/{planificacao}/regenerar-calendario', [PlanificacaoController::class, 'regenerarCalendario'])
                    ->middleware('can:pedagogico.planificacao.editar')->name('regenerar-calendario');
            });

            Route::prefix('aulas')->name('aulas.')->group(function () {
                Route::get('/{planificacao}', [PedagogicoAulaController::class, 'index'])->name('index');
                Route::put('/{aula}', [PedagogicoAulaController::class, 'update'])
                    ->middleware('can:pedagogico.planificacao.editar')->name('update');
            });

            Route::prefix('planos')->name('planos.')->group(function () {
                Route::get('/{planificacao}', [PlanoAulaController::class, 'index'])->name('index');
                Route::post('/{planificacao}/gerar', [PlanoAulaController::class, 'gerar'])
                    ->middleware(['throttle:ai-generation', 'can:pedagogico.ia.gerar'])->name('gerar');
                Route::get('/item/{planoAula}', [PlanoAulaController::class, 'show'])->name('show');
                Route::put('/item/{planoAula}', [PlanoAulaController::class, 'update'])
                    ->middleware('can:pedagogico.planificacao.editar')->name('update');
                Route::get('/item/{planoAula}/pdf', [PlanoAulaController::class, 'pdf'])->name('pdf');
                Route::post('/item/{planoAula}/marcar-dado', [PlanoAulaController::class, 'marcarComoDado'])
                    ->middleware('can:pedagogico.planificacao.editar')->name('marcar-dado');
            });

            Route::prefix('execucao')->name('execucao.')->group(function () {
                Route::get('/{planificacao}', [PedagogicoExecucaoController::class, 'show'])->name('show');
                Route::post('/{planificacao}/analisar', [PedagogicoExecucaoController::class, 'analisar'])
                    ->middleware(['throttle:ai-generation', 'can:pedagogico.ia.gerar'])->name('analisar');
            });

            Route::prefix('exercicios')->name('exercicios.')->group(function () {
                Route::get('/', [PedagogicoExercicioController::class, 'index'])->name('index');
                Route::get('/create', [PedagogicoExercicioController::class, 'create'])
                    ->middleware('can:pedagogico.ia.gerar')->name('create');
                Route::post('/gerar', [PedagogicoExercicioController::class, 'gerar'])
                    ->middleware(['throttle:ai-generation', 'can:pedagogico.ia.gerar'])->name('gerar');
                Route::get('/{exercicio}', [PedagogicoExercicioController::class, 'show'])->name('show');
                Route::get('/{exercicio}/pdf', [PedagogicoExercicioController::class, 'pdf'])->name('pdf');
            });

            Route::prefix('provas')->name('provas.')->group(function () {
                Route::get('/', [PedagogicoProvaController::class, 'index'])->name('index');
                Route::get('/create', [PedagogicoProvaController::class, 'create'])
                    ->middleware('can:pedagogico.ia.gerar')->name('create');
                Route::post('/gerar', [PedagogicoProvaController::class, 'gerar'])
                    ->middleware(['throttle:ai-generation', 'can:pedagogico.ia.gerar'])->name('gerar');
                Route::post('/{prova}/regenerar', [PedagogicoProvaController::class, 'regenerar'])
                    ->middleware(['throttle:ai-generation', 'can:pedagogico.ia.gerar'])->name('regenerar');
                Route::post('/{prova}/submeter', [PedagogicoProvaController::class, 'submeter'])->name('submeter');
                Route::post('/{prova}/aprovar', [PedagogicoProvaController::class, 'aprovar'])
                    ->middleware('can:pedagogico.aprovar')->name('aprovar');
                Route::post('/{prova}/rejeitar', [PedagogicoProvaController::class, 'rejeitar'])
                    ->middleware('can:pedagogico.aprovar')->name('rejeitar');
                Route::post('/{prova}/publicar', [PedagogicoProvaController::class, 'publicar'])
                    ->middleware('permission.any:pedagogico.planificacao.editar,pedagogico.aprovar')
                    ->name('publicar');
                Route::post('/{prova}/importar-banco', [PedagogicoProvaController::class, 'importarBanco'])
                    ->middleware('can:pedagogico.banco.ver')->name('importar-banco');
                Route::get('/{prova}', [PedagogicoProvaController::class, 'show'])->name('show');
                Route::get('/{prova}/pdf', [PedagogicoProvaController::class, 'pdf'])->name('pdf');
                Route::get('/{prova}/pdf-matriz', [PedagogicoProvaController::class, 'pdfMatriz'])->name('pdf-matriz');
                Route::get('/{prova}/pdf-chave', [PedagogicoProvaController::class, 'pdfChave'])->name('pdf-chave');
            });

            Route::prefix('aprovacoes')->name('aprovacoes.')->middleware('can:pedagogico.aprovar')->group(function () {
                Route::get('/', [AprovacaoController::class, 'index'])->name('index');
            });

            Route::prefix('banco-questoes')->name('banco.')->middleware('can:pedagogico.banco.ver')->group(function () {
                Route::get('/', [BancoQuestaoController::class, 'index'])->name('index');
                Route::get('/criar', [BancoQuestaoController::class, 'create'])
                    ->middleware('can:pedagogico.banco.gerir')->name('create');
                Route::get('/gerar', [BancoQuestaoController::class, 'createIa'])
                    ->middleware(['can:pedagogico.banco.gerir', 'can:pedagogico.ia.gerar'])->name('create-ia');
                Route::post('/gerar', [BancoQuestaoController::class, 'gerar'])
                    ->middleware(['throttle:ai-generation', 'can:pedagogico.banco.gerir', 'can:pedagogico.ia.gerar'])->name('gerar');
                Route::post('/', [BancoQuestaoController::class, 'store'])
                    ->middleware('can:pedagogico.banco.gerir')->name('store');
                Route::delete('/{questao}', [BancoQuestaoController::class, 'destroy'])
                    ->middleware('can:pedagogico.banco.gerir')->name('destroy');
            });

            Route::prefix('conteudos-minimos')->name('conteudos.')->middleware('can:conteudos.ver')->group(function () {
                Route::get('/', [\App\Http\Controllers\Pedagogico\ConteudoMinimoController::class, 'index'])->name('index');
                Route::get('/criar', [\App\Http\Controllers\Pedagogico\ConteudoMinimoController::class, 'create'])
                    ->middleware('can:conteudos.gerir')->name('create');
                Route::post('/', [\App\Http\Controllers\Pedagogico\ConteudoMinimoController::class, 'store'])
                    ->middleware('can:conteudos.gerir')->name('store');
                Route::get('/{conteudo}', [\App\Http\Controllers\Pedagogico\ConteudoMinimoController::class, 'show'])->name('show');
                Route::get('/{conteudo}/editar', [\App\Http\Controllers\Pedagogico\ConteudoMinimoController::class, 'edit'])
                    ->middleware('can:conteudos.gerir')->name('edit');
                Route::put('/{conteudo}', [\App\Http\Controllers\Pedagogico\ConteudoMinimoController::class, 'update'])
                    ->middleware('can:conteudos.gerir')->name('update');
                Route::post('/{conteudo}/itens', [\App\Http\Controllers\Pedagogico\ConteudoMinimoController::class, 'storeItem'])
                    ->middleware('can:conteudos.gerir')->name('itens.store');
                Route::put('/{conteudo}/itens/{item}', [\App\Http\Controllers\Pedagogico\ConteudoMinimoController::class, 'updateItem'])
                    ->middleware('can:conteudos.gerir')->name('itens.update');
                Route::delete('/{conteudo}/itens/{item}', [\App\Http\Controllers\Pedagogico\ConteudoMinimoController::class, 'destroyItem'])
                    ->middleware('can:conteudos.gerir')->name('itens.destroy');
                Route::post('/{conteudo}/publicar', [\App\Http\Controllers\Pedagogico\ConteudoMinimoController::class, 'publicar'])
                    ->middleware('can:conteudos.publicar')->name('publicar');
                Route::post('/{conteudo}/arquivar', [\App\Http\Controllers\Pedagogico\ConteudoMinimoController::class, 'arquivar'])
                    ->middleware('can:conteudos.publicar')->name('arquivar');
                Route::post('/{conteudo}/nova-versao', [\App\Http\Controllers\Pedagogico\ConteudoMinimoController::class, 'novaVersao'])
                    ->middleware('permission.any:conteudos.gerir,conteudos.publicar')
                    ->name('nova-versao');
            });

            Route::prefix('correcoes')->name('correcoes.')->middleware('can:pedagogico.correcao.usar')->group(function () {
                Route::get('/', [PedagogicoCorrecaoController::class, 'index'])->name('index');
                Route::get('/create', [PedagogicoCorrecaoController::class, 'create'])->name('create');
                Route::post('/', [PedagogicoCorrecaoController::class, 'store'])->name('store');
                Route::get('/{correcao}', [PedagogicoCorrecaoController::class, 'show'])->name('show');
                Route::post('/{correcao}/confirmar', [PedagogicoCorrecaoController::class, 'confirmar'])->name('confirmar');
                Route::post('/{correcao}/rejeitar', [PedagogicoCorrecaoController::class, 'rejeitar'])->name('rejeitar');
            });

            Route::prefix('dashboard')->name('dashboard.')->middleware('can:pedagogico.dashboard.ver')->group(function () {
                Route::get('/', [DashboardPedagogicoController::class, 'index'])->name('index');
                Route::get('/export-pdf', [DashboardPedagogicoController::class, 'exportPdf'])->name('export-pdf');
            });

            Route::prefix('coordenacao')->name('coordenacao.')->group(function () {
                Route::get('/lancamentos', [\App\Http\Controllers\Pedagogico\CoordenacaoDisciplinaController::class, 'lancamentos'])
                    ->middleware('can:notas.supervisionar')
                    ->name('lancamentos');
                Route::get('/relatorio', [\App\Http\Controllers\Pedagogico\CoordenacaoDisciplinaController::class, 'relatorio'])
                    ->middleware('can:relatorios.disciplina')
                    ->name('relatorio');
                Route::get('/avisos', [\App\Http\Controllers\Pedagogico\CoordenacaoDisciplinaController::class, 'avisosForm'])
                    ->middleware('can:disciplina.avisar')
                    ->name('avisos');
                Route::post('/avisos', [\App\Http\Controllers\Pedagogico\CoordenacaoDisciplinaController::class, 'avisosEnviar'])
                    ->middleware('can:disciplina.avisar')
                    ->name('avisos.enviar');
            });

            Route::prefix('orientacoes')->name('orientacoes.')->middleware('can:orientacoes.ver')->group(function () {
                Route::get('/', [\App\Http\Controllers\Pedagogico\OrientacaoMetodologicaController::class, 'index'])->name('index');
                Route::get('/create', [\App\Http\Controllers\Pedagogico\OrientacaoMetodologicaController::class, 'create'])
                    ->middleware('can:orientacoes.gerir')->name('create');
                Route::post('/', [\App\Http\Controllers\Pedagogico\OrientacaoMetodologicaController::class, 'store'])
                    ->middleware('can:orientacoes.gerir')->name('store');
                Route::get('/{orientacao}', [\App\Http\Controllers\Pedagogico\OrientacaoMetodologicaController::class, 'show'])->name('show');
                Route::get('/{orientacao}/editar', [\App\Http\Controllers\Pedagogico\OrientacaoMetodologicaController::class, 'edit'])
                    ->middleware('can:orientacoes.gerir')->name('edit');
                Route::put('/{orientacao}', [\App\Http\Controllers\Pedagogico\OrientacaoMetodologicaController::class, 'update'])
                    ->middleware('can:orientacoes.gerir')->name('update');
                Route::post('/{orientacao}/publicar', [\App\Http\Controllers\Pedagogico\OrientacaoMetodologicaController::class, 'publicar'])
                    ->middleware('can:orientacoes.gerir')->name('publicar');
                Route::post('/{orientacao}/arquivar', [\App\Http\Controllers\Pedagogico\OrientacaoMetodologicaController::class, 'arquivar'])
                    ->middleware('can:orientacoes.gerir')->name('arquivar');
            });
        });

        // ========================================
        // TRANSFERÊNCIA E TRANSIÇÃO (REQUER ano lectivo)
        // ========================================
        // Transferencia de Ano Lectivo
        Route::prefix('ano-lectivo-transfer')->name('ano-lectivo-transfer.')->group(function () {
            Route::get('/', [AnoLectivoTransferController::class, 'index'])->name('index');
            Route::post('/preview', [AnoLectivoTransferController::class, 'preview'])->name('preview');
            Route::post('/execute', [AnoLectivoTransferController::class, 'execute'])->name('execute');
        });

        // Wizard de Fecho de Ano Lectivo
        Route::prefix('fecho-ano')->name('fecho-ano.')->group(function () {
            Route::get('/', [\App\Http\Controllers\FechoAnoController::class, 'index'])->name('index');
            Route::post('/configurar', [\App\Http\Controllers\FechoAnoController::class, 'configurar'])->name('configurar');
            Route::post('/transferir', [\App\Http\Controllers\FechoAnoController::class, 'transferir'])->name('transferir');
            Route::post('/transitar', [\App\Http\Controllers\FechoAnoController::class, 'transitar'])->name('transitar');
            Route::post('/activar', [\App\Http\Controllers\FechoAnoController::class, 'activar'])->name('activar');
        });
    }); // FIM do grupo ano.lectivo.required

    // ========================================
    // ADMINISTRAÇÃO (NÃO requer ano lectivo)
    // ========================================
    // Gestao de Utilizadores
    Route::post('users/{user}/repor-palavra-passe', [UserController::class, 'reporPalavraPasse'])->name('users.repor-palavra-passe');
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/pendentes', [UserController::class, 'pendentes'])->name('users.pendentes');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::post('users/{user}/aprovar', [UserController::class, 'aprovar'])->name('users.aprovar');
    Route::get('users/{user}/rejeitar', [UserController::class, 'formRejeitar'])->name('users.form-rejeitar');
    Route::post('users/{user}/rejeitar', [UserController::class, 'rejeitar'])->name('users.rejeitar');
    Route::post('users/{user}/alterar-estado', [UserController::class, 'alterarEstado'])->name('users.alterar-estado');
    Route::post('users/{user}/atribuir-cargo', [UserController::class, 'atribuirCargo'])->name('users.atribuir-cargo');
    Route::post('users/{user}/remover-cargo', [UserController::class, 'removerCargo'])->name('users.remover-cargo');
    Route::post('users/{user}/sync-cargos', [UserController::class, 'syncCargos'])->name('users.sync-cargos');
    Route::post('users/{user}/atribuir-permissao', [UserController::class, 'atribuirPermissao'])->name('users.atribuir-permissao');
    Route::post('users/{user}/remover-permissao', [UserController::class, 'removerPermissao'])->name('users.remover-permissao');
    Route::post('users/{user}/sync-permissoes', [UserController::class, 'syncPermissoes'])->name('users.sync-permissoes');
    Route::delete('users/{user}/eliminar-aluno', [UserController::class, 'eliminarAluno'])->name('users.eliminar-aluno');

    // Auditoria
    Route::prefix('auditoria')->name('auditoria.')->group(function () {
        Route::get('/', [AuditoriaController::class, 'index'])->name('index');
        Route::get('/estatisticas', [AuditoriaController::class, 'estatisticas'])->name('estatisticas');
        Route::get('/exportar', [AuditoriaController::class, 'exportar'])->name('exportar');
        Route::get('/{activity}', [AuditoriaController::class, 'show'])->name('show');
    });

    // Configuracoes do Sistema - Fase 10
    Route::get('/onboarding/escola-nova', [\App\Http\Controllers\OnboardingController::class, 'escolaNova'])
        ->name('onboarding.escola-nova');

    Route::prefix('configuracoes')->name('configuracoes.')->group(function () {
        Route::get('/', [ConfiguracaoController::class, 'index'])->name('index');
        Route::put('/update-trimestre', [ConfiguracaoController::class, 'updateTrimestre'])->name('update-trimestre');
        Route::post('/avaliacao', [ConfiguracaoController::class, 'updateAvaliacao'])->name('update-avaliacao');
        Route::post('/recurso', [ConfiguracaoController::class, 'updateRecurso'])->name('update-recurso');
        Route::post('/melhoria', [ConfiguracaoController::class, 'updateMelhoria'])->name('update-melhoria');
        Route::post('/periodos', [ConfiguracaoController::class, 'updatePeriodosLancamento'])->name('update-periodos');
        Route::post('/instituicao', [ConfiguracaoController::class, 'updateInstituicao'])->name('update-instituicao');
        Route::post('/ensino', [ConfiguracaoController::class, 'updateEnsino'])->name('update-ensino');
        Route::post('/pdf', [ConfiguracaoController::class, 'updatePdf'])->name('update-pdf');
        Route::post('/portal', [ConfiguracaoController::class, 'updatePortal'])->name('update-portal');
        Route::post('/seguranca', [ConfiguracaoController::class, 'updateSeguranca'])->name('update-seguranca');
        Route::delete('/logo', [ConfiguracaoController::class, 'removerLogo'])->name('remover-logo');
        Route::post('/limpar-cache', [ConfiguracaoController::class, 'limparCache'])->name('limpar-cache');

        Route::prefix('pagina-publica')->name('pagina-publica.')->group(function () {
            Route::get('/', [PaginaPublicaController::class, 'index'])->name('index');
            Route::get('/identidade', [PaginaPublicaController::class, 'identidade'])->name('identidade');
            Route::post('/identidade', [PaginaPublicaController::class, 'updateIdentidade'])->name('identidade.update');
            Route::get('/textos', [PaginaPublicaController::class, 'textos'])->name('textos');
            Route::post('/textos', [PaginaPublicaController::class, 'updateTextos'])->name('textos.update');
            Route::get('/carousel', [PaginaPublicaController::class, 'carousel'])->name('carousel');
            Route::post('/carousel', [PaginaPublicaController::class, 'updateCarousel'])->name('carousel.update');
            Route::delete('/carousel/{slide}/imagem', [PaginaPublicaController::class, 'removerCarouselImagem'])->name('carousel.remover-imagem');
            Route::get('/opcoes', [PaginaPublicaController::class, 'opcoes'])->name('opcoes');
            Route::post('/opcoes', [PaginaPublicaController::class, 'updateOpcoes'])->name('opcoes.update');
        });

        // Gestão de anos lectivos
        Route::post('/anos-lectivos', [ConfiguracaoController::class, 'storeAnoLectivo'])->name('anos-lectivos.store');
        Route::post('/anos-lectivos/activar', [ConfiguracaoController::class, 'activarAnoLectivo'])->name('anos-lectivos.activar');
    });

    // Sistema de Backups — só admin_full (Gate backup.*)
    Route::prefix('backups')->name('backups.')->middleware('can:backup.viewAny')->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::post('/create', [BackupController::class, 'create'])->middleware('can:backup.create')->name('create');
        Route::get('/{filename}/download', [BackupController::class, 'download'])->name('download');
        Route::delete('/{filename}', [BackupController::class, 'destroy'])->middleware('can:backup.delete')->name('destroy');
    });

    // Sistema de Filas — só admin_full (Gate queue.*)
    Route::prefix('queue')->name('queue.')->middleware('can:queue.viewAny')->group(function () {
        Route::get('/', [QueueController::class, 'index'])->name('index');
        Route::delete('/clear-failed', [QueueController::class, 'clearFailed'])->middleware('can:queue.delete')->name('clear-failed');
        Route::post('/retry-all', [QueueController::class, 'retryAll'])->middleware('can:queue.delete')->name('retry-all');
        Route::post('/{id}/retry', [QueueController::class, 'retryFailed'])->middleware('can:queue.delete')->name('retry-failed');
        Route::delete('/{id}', [QueueController::class, 'deleteFailed'])->middleware('can:queue.delete')->name('delete-failed');
    });

    // Hub de controlo Facilita (CONTROL_MODE=hub + admin_full)
    Route::prefix('controlo')->name('control.hub.')->middleware('role:admin_full')->group(function () {
        Route::get('/', [ControlHubController::class, 'index'])->name('index');
        Route::get('/escolas/criar', [ControlHubController::class, 'create'])->name('escolas.create');
        Route::post('/escolas', [ControlHubController::class, 'store'])->name('escolas.store');
        Route::get('/escolas/{escola}', [ControlHubController::class, 'show'])->name('escolas.show');
        Route::put('/escolas/{escola}', [ControlHubController::class, 'update'])->name('escolas.update');
        Route::post('/escolas/{escola}/bloquear', [ControlHubController::class, 'bloquear'])->name('escolas.bloquear');
        Route::post('/escolas/{escola}/desbloquear', [ControlHubController::class, 'desbloquear'])->name('escolas.desbloquear');
        Route::post('/escolas/{escola}/cobranca', [ControlHubController::class, 'marcarCobranca'])->name('escolas.cobranca');
        Route::post('/escolas/{escola}/cobrancas', [ControlHubController::class, 'criarCobranca'])->name('escolas.cobrancas.store');
        Route::post('/cobrancas/{cobranca}/pagar', [ControlHubController::class, 'pagarCobranca'])->name('cobrancas.pagar');
        Route::post('/cobrancas/{cobranca}/cancelar', [ControlHubController::class, 'cancelarCobranca'])->name('cobrancas.cancelar');
        Route::post('/escolas/{escola}/atraso', [ControlHubController::class, 'marcarAtraso'])->name('escolas.atraso');
        Route::post('/escolas/{escola}/secret', [ControlHubController::class, 'regenerarSecret'])->name('escolas.secret');
        Route::post('/escolas/{escola}/sync', [ControlHubController::class, 'pedirSync'])->name('escolas.sync');
    });
});
};

if ($facilitaPrefix !== '') {
    Route::prefix($facilitaPrefix)->group($registarRotasApp);
} else {
    $registarRotasApp();
}

require __DIR__.'/auth.php';
