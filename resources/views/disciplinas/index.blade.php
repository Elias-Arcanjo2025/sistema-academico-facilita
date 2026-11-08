<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-bookmark me-2 text-primary"></i>Disciplinas
            </h2>
            @can('create', App\Models\Disciplina::class)
                <a href="{{ route('disciplinas.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Nova Disciplina
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Disciplinas', 'url' => route('disciplinas.index')]
            ]" />

            <x-tip-card icon="bi-bookmark" variant="primary" title="Gestão de Disciplinas" id="tip-disciplinas-index">
                Gerir as disciplinas disponíveis na instituição. Cada disciplina pode ser associada a turmas específicas através da secção "Associações". As disciplinas são a base para o lançamento de notas.
            </x-tip-card>

            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <x-stat-card
                        icon="bi-bookmark-fill"
                        icon-class="stat-icon-primary"
                        :value="$disciplinas->total()"
                        label="Total de Disciplinas"
                    />
                </div>
                <div class="col-md-4">
                    <x-stat-card
                        icon="bi-check-circle"
                        icon-class="stat-icon-success"
                        :value="$disciplinas->where('activo', true)->count()"
                        label="Ativas"
                    />
                </div>
                <div class="col-md-4">
                    <x-stat-card
                        icon="bi-pause-circle"
                        icon-class="stat-icon-warning"
                        :value="$disciplinas->where('activo', false)->count()"
                        label="Inativas"
                    />
                </div>
            </div>

            <x-data-table
                title="Lista de Disciplinas"
                icon="bi-list-ul"
                :badge="$disciplinas->total() . ' registos'"
                :pagination="$disciplinas"
            >
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="text-nowrap" style="width: 120px;">Código</th>
                                    <th class="text-nowrap">Nome</th>
                                    <th class="text-nowrap" style="width: 150px;">Tipo</th>
                                    <th class="text-nowrap">Cursos</th>
                                    <th class="text-nowrap">Classes</th>
                                    <th class="text-nowrap text-center" style="width: 100px;">Estado</th>
                                    <th class="text-nowrap text-end" style="width: 150px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($disciplinas as $disciplina)
                                    <tr class="align-middle">
                                        <td class="text-nowrap">
                                            <span class="badge bg-light text-dark border">
                                                <i class="bi bi-hash"></i>{{ $disciplina->codigo }}
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <strong>{{ $disciplina->nome }}</strong>
                                            @if($disciplina->descricao)
                                                <br><small class="text-muted">{{ Str::limit($disciplina->descricao, 50) }}</small>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            <span class="badge bg-secondary" style="font-size: 0.75rem;">
                                                {{ $disciplina->tipo_label }}
                                            </span>
                                            <small class="text-muted d-block mt-1">#{{ $disciplina->ordem_apresentacao }}</small>
                                        </td>
                                        <td class="text-nowrap">
                                            @foreach($disciplina->cursos as $curso)
                                                <span class="badge bg-info me-1">{{ $curso->nome }}</span>
                                            @endforeach
                                            @if($disciplina->cursos->isEmpty())
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            @forelse($disciplina->classes as $classe)
                                                <span class="badge bg-purple me-1">{{ $classe->nome }}</span>
                                            @empty
                                                <span class="badge bg-light text-muted">Todas</span>
                                            @endforelse
                                        </td>
                                        <td class="text-nowrap text-center">
                                            @if($disciplina->activo)
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
                                                :view-url="route('disciplinas.show', $disciplina)"
                                                :edit-url="Gate::allows('update', $disciplina) ? route('disciplinas.edit', $disciplina) : null"
                                                :delete-route="Gate::allows('delete', $disciplina) ? 'disciplinas.destroy' : null"
                                                :delete-id="$disciplina->id"
                                                :delete-name="'a disciplina ' . $disciplina->nome"
                                            />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            <x-empty-state
                                                icon="bi-bookmark"
                                                message="Nenhuma disciplina encontrada."
                                                :create-url="route('disciplinas.create')"
                                                create-label="Criar primeira disciplina"
                                                :can-create="Gate::allows('create', App\Models\Disciplina::class)"
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
