<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-door-open me-2 text-primary"></i>Salas
            </h2>
            @can('create', App\Models\Sala::class)
                <a href="{{ route('salas.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Nova Sala
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-door-open" variant="info" title="Salas de Aula" id="tip-salas-index">
                Gerir as salas de aula disponíveis na instituição. As salas podem ser associadas a turmas para organizar o espaço físico. Defina a capacidade de cada sala para um melhor planeamento.
            </x-tip-card>

            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <x-stat-card
                        icon="bi-door-open"
                        icon-class="stat-icon-primary"
                        :value="$totalSalas"
                        label="Total de Salas"
                    />
                </div>
                <div class="col-md-4">
                    <x-stat-card
                        icon="bi-check-circle"
                        icon-class="stat-icon-success"
                        :value="$salasAtivas"
                        label="Ativas"
                    />
                </div>
                <div class="col-md-4">
                    <x-stat-card
                        icon="bi-pause-circle"
                        icon-class="stat-icon-warning"
                        :value="$salasInativas"
                        label="Inativas"
                    />
                </div>
            </div>

            <x-data-table
                title="Lista de Salas"
                icon="bi-list-ul"
                :badge="$salas->total() . ' registos'"
                :pagination="$salas"
            >
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="text-nowrap" style="width: 120px;">Código</th>
                                    <th class="text-nowrap">Nome</th>
                                    <th class="text-nowrap text-center">Capacidade</th>
                                    <th class="text-nowrap text-center">Turmas</th>
                                    <th class="text-nowrap text-center" style="width: 100px;">Estado</th>
                                    <th class="text-nowrap text-end" style="width: 150px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($salas as $sala)
                                    <tr class="align-middle">
                                        <td class="text-nowrap">
                                            <span class="badge bg-light text-dark border">
                                                <i class="bi bi-hash"></i>{{ $sala->codigo }}
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <strong>{{ $sala->nome }}</strong>
                                            @if($sala->descricao)
                                                <br><small class="text-muted">{{ Str::limit($sala->descricao, 50) }}</small>
                                            @endif
                                        </td>
                                        <td class="text-nowrap text-center">
                                            @if($sala->capacidade)
                                                <span class="badge bg-info">
                                                    <i class="bi bi-people me-1"></i>{{ $sala->capacidade }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap text-center">
                                            <span class="badge bg-purple">{{ $sala->turmas_count }}</span>
                                        </td>
                                        <td class="text-nowrap text-center">
                                            @if($sala->activo)
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
                                                :view-url="Gate::allows('view', $sala) ? route('salas.show', $sala) : null"
                                                :edit-url="Gate::allows('update', $sala) ? route('salas.edit', $sala) : null"
                                                :delete-route="Gate::allows('delete', $sala) ? 'salas.destroy' : null"
                                                :delete-id="$sala->id"
                                                :delete-name="'a sala ' . $sala->nome"
                                            />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            <x-empty-state
                                                icon="bi-door-open"
                                                message="Nenhuma sala encontrada."
                                                :create-url="route('salas.create')"
                                                create-label="Criar primeira sala"
                                                :can-create="Gate::allows('create', App\Models\Sala::class)"
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
