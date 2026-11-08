<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-people me-2 text-primary"></i>Turmas
            </h2>
            @can('create', App\Models\Turma::class)
                @if(!$modoConsulta)
                    <a href="{{ route('turmas.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Nova Turma
                    </a>
                @else
                    <button class="btn btn-secondary" disabled title="Modo consulta: apenas leitura">
                        <i class="bi bi-lock me-1"></i> Nova Turma (Bloqueado)
                    </button>
                @endif
            @endcan
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Turmas', 'url' => route('turmas.index')]
            ]" />

            <x-tip-card icon="bi-people" variant="primary" title="Gestão de Turmas" id="tip-turmas-index">
                Visualize e gerir todas as turmas. Cada turma pertence a um curso e pode ter alunos matriculados. Pode filtrar por curso, classe ou turno para encontrar rapidamente a turma desejada.
            </x-tip-card>

            {{-- Filtro de Ano Lectivo --}}
            <div class="card shadow-soft mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('turmas.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <x-ano-lectivo-selector
                                :anoLectivoId="$anoLectivoId"
                                :anosLectivos="$anosLectivos"
                                size="sm"
                            />
                        </div>
                        <div class="col-md-8">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-funnel me-1"></i>Filtrar
                            </button>
                            <a href="{{ route('turmas.index') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x-circle me-1"></i>Limpar
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Indicador de Modo Consulta --}}
            @if($modoConsulta)
                <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <strong>Modo Consulta:</strong> Está a visualizar dados de um ano lectivo anterior. Apenas leitura permitida.
                    <a href="{{ route('turmas.index') }}" class="alert-link">Voltar ao ano actual</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <x-stat-card
                        icon="bi-people"
                        icon-class="stat-icon-primary"
                        :value="$turmas->total()"
                        label="Total de Turmas"
                    />
                </div>
                <div class="col-md-3">
                    <x-stat-card
                        icon="bi-check-circle"
                        icon-class="stat-icon-success"
                        :value="$turmas->where('activo', true)->count()"
                        label="Ativas"
                    />
                </div>
                <div class="col-md-3">
                    <x-stat-card
                        icon="bi-person-fill"
                        icon-class="stat-icon-info"
                        :value="$turmas->sum('alunos_count')"
                        label="Total Alunos"
                    />
                </div>
                <div class="col-md-3">
                    <x-stat-card
                        icon="bi-pause-circle"
                        icon-class="stat-icon-warning"
                        :value="$turmas->where('activo', false)->count()"
                        label="Inativas"
                    />
                </div>
            </div>

            <x-data-table
                title="Lista de Turmas"
                icon="bi-list-ul"
                :badge="$turmas->total() . ' registos'"
                :pagination="$turmas"
            >
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="text-nowrap">Código</th>
                                    <th class="text-nowrap">Nome</th>
                                    <th class="text-nowrap">Curso</th>
                                    <th class="text-nowrap">Classe</th>
                                    <th class="text-nowrap">Ano Lectivo</th>
                                    <th class="text-nowrap text-center">Turno</th>
                                    <th class="text-nowrap text-center">Alunos</th>
                                    <th class="text-nowrap text-center" style="width: 100px;">Estado</th>
                                    <th class="text-nowrap text-end" style="width: 150px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($turmas as $turma)
                                    <tr class="align-middle">
                                        <td class="text-nowrap">
                                            <span class="badge bg-light text-dark border">
                                                <i class="bi bi-hash"></i>{{ $turma->codigo }}
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <strong>{{ $turma->nome }}</strong>
                                            @if($turma->sala)
                                                <br><small class="text-muted"><i class="bi bi-door-open me-1"></i>{{ $turma->sala->nome }}</small>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            <a href="{{ route('cursos.show', $turma->curso) }}" class="text-decoration-none">
                                                <span class="badge bg-info">{{ $turma->curso->nome }}</span>
                                            </a>
                                        </td>
                                        <td class="text-nowrap">
                                            @if($turma->classe)
                                                <span class="badge bg-secondary">{{ $turma->classe->nome }}</span>
                                            @else
                                                <span class="badge bg-light text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            <span class="badge bg-purple">{{ $turma->ano_lectivo }}</span>
                                        </td>
                                        <td class="text-nowrap text-center">
                                            <span class="badge bg-light text-dark border">
                                                <i class="bi bi-clock me-1"></i>{{ $turma->turno->nome }}
                                            </span>
                                        </td>
                                        <td class="text-nowrap text-center">
                                            <span class="badge bg-info">
                                                <i class="bi bi-person me-1"></i>{{ $turma->alunos_count }}
                                            </span>
                                        </td>
                                        <td class="text-nowrap text-center">
                                            @if($turma->activo)
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle me-1"></i>Ativo
                                                </span>
                                            @else
                                                <span class="badge bg-secondary">
                                                    <i class="bi bi-pause-circle me-1"></i>Inativo
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap text-end">
                                            <x-action-buttons
                                                :view-url="Gate::allows('view', $turma) ? route('turmas.show', $turma) : null"
                                                :edit-url="Gate::allows('update', $turma) ? route('turmas.edit', $turma) : null"
                                                :delete-route="Gate::allows('delete', $turma) ? 'turmas.destroy' : null"
                                                :delete-id="$turma->id"
                                                :delete-name="'a turma ' . $turma->nome"
                                            >
                                                <!-- Botão Exportar PDF usando slot -->
                                                <a href="{{ route('export.lista-alunos.pdf', ['turma_id' => $turma->id]) }}"
                                                   class="btn btn-sm btn-outline-success"
                                                   title="Exportar alunos em PDF"
                                                   data-bs-toggle="tooltip"
                                                   target="_blank">
                                                    <i class="bi bi-file-earmark-pdf"></i>
                                                </a>
                                            </x-action-buttons>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9">
                                            <x-empty-state
                                                icon="bi-people"
                                                message="Nenhuma turma encontrada."
                                                :create-url="route('turmas.create')"
                                                create-label="Criar primeira turma"
                                                :can-create="Gate::allows('create', App\Models\Turma::class)"
                                            />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
            </x-data-table>
        </div>
    </div>
</x-app-layout>
