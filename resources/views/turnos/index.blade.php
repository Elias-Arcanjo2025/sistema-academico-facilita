<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-clock me-2 text-primary"></i>Turnos
            </h2>
            @can('create', App\Models\Turno::class)
                <a href="{{ route('turnos.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Novo Turno
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-clock" variant="info" title="Turnos" id="tip-turnos-index">
                Configure os turnos de funcionamento da instituição (manhã, tarde, noite). Os turnos são utilizados para organizar as turmas e os horários de aulas.
            </x-tip-card>

            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <x-stat-card
                        icon="bi-clock"
                        icon-class="stat-icon-primary"
                        :value="$turnos->total()"
                        label="Total de Turnos"
                    />
                </div>
                <div class="col-md-4">
                    <x-stat-card
                        icon="bi-check-circle"
                        icon-class="stat-icon-success"
                        :value="$turnos->where('activo', true)->count()"
                        label="Ativos"
                    />
                </div>
                <div class="col-md-4">
                    <x-stat-card
                        icon="bi-pause-circle"
                        icon-class="stat-icon-warning"
                        :value="$turnos->where('activo', false)->count()"
                        label="Inativos"
                    />
                </div>
            </div>

            <x-data-table
                title="Lista de Turnos"
                icon="bi-list-ul"
                :badge="$turnos->total() . ' registos'"
                :pagination="$turnos"
            >
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="text-nowrap">Nome</th>
                                    <th class="text-nowrap">Horário</th>
                                    <th class="text-nowrap text-center">Turmas</th>
                                    <th class="text-nowrap text-center" style="width: 100px;">Estado</th>
                                    <th class="text-nowrap text-end" style="width: 150px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($turnos as $turno)
                                    <tr class="align-middle">
                                        <td class="text-nowrap">
                                            <strong>{{ $turno->nome }}</strong>
                                        </td>
                                        <td class="text-nowrap">
                                            @if($turno->hora_inicio && $turno->hora_fim)
                                                <span class="badge bg-light text-dark border">
                                                    <i class="bi bi-clock me-1"></i>
                                                    {{ \Carbon\Carbon::parse($turno->hora_inicio)->format('H:i') }} - {{ \Carbon\Carbon::parse($turno->hora_fim)->format('H:i') }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap text-center">
                                            <span class="badge bg-purple">{{ $turno->turmas_count }}</span>
                                        </td>
                                        <td class="text-nowrap text-center">
                                            @if($turno->activo)
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
                                                :view-url="Gate::allows('view', $turno) ? route('turnos.show', $turno) : null"
                                                :edit-url="Gate::allows('update', $turno) ? route('turnos.edit', $turno) : null"
                                                :delete-route="Gate::allows('delete', $turno) ? 'turnos.destroy' : null"
                                                :delete-id="$turno->id"
                                                :delete-name="'o turno ' . $turno->nome"
                                            />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">
                                            <x-empty-state
                                                icon="bi-clock"
                                                message="Nenhum turno encontrado."
                                                :create-url="route('turnos.create')"
                                                create-label="Criar primeiro turno"
                                                :can-create="Gate::allows('create', App\Models\Turno::class)"
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
