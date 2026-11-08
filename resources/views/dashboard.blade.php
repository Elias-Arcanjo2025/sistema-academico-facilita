<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="welcome-title">
                    <i class="bi bi-sun me-2"></i>Olá, {{ Auth::user()->name }}!
                </h2>
                <p class="welcome-subtitle mb-0">
                    Bem-vindo ao Sistema Académico Facilita. Aqui pode gerir notas, turmas e muito mais.
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <span class="badge bg-white text-primary px-3 py-2" style="font-size: 0.9rem;">
                    <i class="bi bi-calendar3 me-1"></i>{{ now()->format('d/m/Y') }}
                </span>
            </div>
        </div>
    </div>

    <x-regras-avaliacao-destaque />

    <x-tip-card icon="bi-keyboard" variant="primary" title="Atalhos de teclado" id="tip-dashboard-atalhos">
        Use <span class="f-key">Ctrl</span>+<span class="f-key">K</span> para pesquisar,
        <span class="f-key">Ctrl</span>+<span class="f-key">/</span> para ver todos os atalhos,
        e <span class="f-key">Ctrl</span>+<span class="f-key">Shift</span>+<span class="f-key">A</span> para o Assistente IA.
        A lista completa está no menu <strong>Atalhos de teclado</strong>.
    </x-tip-card>

    <!-- Permissões Extras (Debug) -->
    @php
        $extraPermissions = Auth::user()->getDirectPermissions();
    @endphp
    @if($extraPermissions->isNotEmpty())
    <div class="alert alert-warning mb-4">
        <h6><i class="bi bi-star-fill"></i> Tem {{ $extraPermissions->count() }} permissão(ões) extra(s) atribuída(s):</h6>
        <div class="mt-2">
            @foreach($extraPermissions as $perm)
                <span class="badge bg-warning text-dark me-1">{{ $perm->name }}</span>
            @endforeach
        </div>
    </div>
    @endif

    @php
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin_full');

        // Ano lectivo e trimestre actuais
        $anoLectivo = \App\Models\Configuracao::getAnoLectivoActual();
        $trimestre = \App\Models\Configuracao::getTrimestreActual();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);

        // Estatísticas baseadas no ano lectivo actual
        $totalAlunos = \App\Models\Aluno::whereHas('turma', function($q) use ($anoLectivoId) {
            $q->where('ano_lectivo_id', $anoLectivoId);
        })->count();
        $totalTurmas = \App\Models\Turma::where('ano_lectivo_id', $anoLectivoId)->count();
        $totalDisciplinas = \App\Models\Disciplina::where('activo', true)->count();
        $totalCursos = \App\Models\Curso::count();
        $totalProfessores = \App\Models\User::role('professor')->where('estado', 'activo')->count();
        $utilizadoresPendentes = \App\Models\User::where('estado', 'pendente')->count();
    @endphp

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value">{{ number_format($totalAlunos) }}</div>
                        <div class="stat-label">Alunos Matriculados</div>
                    </div>
                    <div class="stat-icon stat-icon-primary">
                        <i class="bi bi-mortarboard"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value">{{ number_format($totalTurmas) }}</div>
                        <div class="stat-label">Turmas Ativas</div>
                    </div>
                    <div class="stat-icon stat-icon-success">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value">{{ number_format($totalDisciplinas) }}</div>
                        <div class="stat-label">Disciplinas</div>
                    </div>
                    <div class="stat-icon stat-icon-warning">
                        <i class="bi bi-book"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value">{{ number_format($totalCursos) }}</div>
                        <div class="stat-label">Cursos</div>
                    </div>
                    <div class="stat-icon stat-icon-info">
                        <i class="bi bi-journals"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <h5 class="mb-3 fw-semibold">
        <i class="bi bi-lightning-charge me-2" style="color: var(--warning-color);"></i>Acções Rápidas
    </h5>
    <div class="row g-4 mb-4">
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('notas.lancar') }}" class="quick-action">
                <div class="quick-action-icon stat-icon-primary">
                    <i class="bi bi-pencil-square"></i>
                </div>
                <span class="quick-action-label">Lançar Notas</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('pautas.index') }}" class="quick-action">
                <div class="quick-action-icon stat-icon-success">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <span class="quick-action-label">Ver Pautas</span>
            </a>
        </div>
        @if($isAdmin || $user->hasRole('coordenador_curso'))
            <div class="col-6 col-md-4 col-lg-2">
                <a href="{{ route('alunos.index') }}" class="quick-action">
                    <div class="quick-action-icon stat-icon-warning">
                        <i class="bi bi-person-badge"></i>
                    </div>
                    <span class="quick-action-label">Alunos</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="{{ route('turmas.index') }}" class="quick-action">
                    <div class="quick-action-icon stat-icon-info">
                        <i class="bi bi-people"></i>
                    </div>
                    <span class="quick-action-label">Turmas</span>
                </a>
            </div>
        @endif
        @if($isAdmin)
            <div class="col-6 col-md-4 col-lg-2">
                <a href="{{ route('users.index') }}" class="quick-action">
                    <div class="quick-action-icon stat-icon-danger">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <span class="quick-action-label">Utilizadores</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="{{ route('configuracoes.index') }}" class="quick-action">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, rgba(168, 85, 247, 0.1), rgba(192, 132, 252, 0.1)); color: #a855f7;">
                        <i class="bi bi-gear"></i>
                    </div>
                    <span class="quick-action-label">Configurações</span>
                </a>
            </div>
        @endif
    </div>

    <div class="row g-4">
        <!-- Admin Alerts -->
        @if($isAdmin && $utilizadoresPendentes > 0)
            <div class="col-12">
                <div class="alert alert-warning alert-modern mb-0">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div class="flex-grow-1">
                        <strong>Atenção!</strong> Existem <strong>{{ $utilizadoresPendentes }}</strong> utilizadores aguardando aprovação.
                    </div>
                    <a href="{{ route('users.pendentes') }}" class="btn btn-warning btn-sm ms-3">
                        <i class="bi bi-arrow-right me-1"></i>Ver Pendentes
                    </a>
                </div>
            </div>
        @endif

        <!-- Recent Activity / Stats -->
        <div class="col-lg-8">
            <div class="content-card">
                <div class="content-card-header">
                    <h5 class="content-card-title">
                        <i class="bi bi-bar-chart me-2"></i>Resumo do Sistema
                    </h5>
                </div>
                <div class="content-card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center p-3 rounded-3" style="background: var(--bg-light);">
                                <div class="stat-icon stat-icon-primary me-3" style="width: 48px; height: 48px; font-size: 1.25rem;">
                                    <i class="bi bi-person-workspace"></i>
                                </div>
                                <div>
                                    <div class="fw-bold" style="font-size: 1.25rem;">{{ $totalProfessores }}</div>
                                    <div class="text-muted small">Professores Ativos</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center p-3 rounded-3" style="background: var(--bg-light);">
                                <div class="stat-icon stat-icon-success me-3" style="width: 48px; height: 48px; font-size: 1.25rem;">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div>
                                    <div class="fw-bold" style="font-size: 1.25rem;">{{ $totalCursos }}</div>
                                    <div class="text-muted small">Cursos Disponíveis</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center p-3 rounded-3" style="background: var(--bg-light);">
                                <div class="stat-icon stat-icon-warning me-3" style="width: 48px; height: 48px; font-size: 1.25rem;">
                                    <i class="bi bi-calendar-check"></i>
                                </div>
                                <div>
                                    <div class="fw-bold" style="font-size: 1.25rem;">{{ $anoLectivo }}</div>
                                    <div class="text-muted small">Ano Lectivo Actual</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center p-3 rounded-3" style="background: var(--bg-light);">
                                <div class="stat-icon stat-icon-info me-3" style="width: 48px; height: 48px; font-size: 1.25rem;">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <div>
                                    <div class="fw-bold" style="font-size: 1.25rem;">{{ $trimestre }}º</div>
                                    <div class="text-muted small">Trimestre Actual</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Info Card -->
        <div class="col-lg-4">
            <div class="content-card h-100">
                <div class="content-card-header">
                    <h5 class="content-card-title">
                        <i class="bi bi-person-circle me-2"></i>A Minha Conta
                    </h5>
                </div>
                <div class="content-card-body text-center">
                    <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <h5 class="fw-bold mb-1">{{ Auth::user()->name }}</h5>
                    <p class="text-muted mb-3">{{ Auth::user()->email }}</p>

                    <div class="mb-3">
                        @foreach(Auth::user()->roles as $role)
                            <span class="badge badge-status badge-primary">
                                {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                            </span>
                        @endforeach
                    </div>

                    <div class="d-flex gap-2 justify-content-center">
                        <a href="{{ route('profile.edit') }}" class="btn btn-modern btn-primary-modern">
                            <i class="bi bi-pencil me-1"></i>Editar Perfil
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
