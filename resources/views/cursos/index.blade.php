<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-journals me-2 text-primary"></i>Cursos
            </h2>
            @can('create', App\Models\Curso::class)
                <a href="{{ route('cursos.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Novo Curso
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Cursos', 'url' => route('cursos.index')]
            ]" />

            <x-tip-card icon="bi-journals" variant="primary" title="Gestão de Cursos" id="tip-cursos-index">
                Aqui pode visualizar, criar e gerir todos os cursos da instituição. Cada curso pode ter várias turmas e disciplinas associadas. Utilize o botão "Novo Curso" para adicionar um curso.
            </x-tip-card>

            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <x-stat-card
                        icon="bi-journals"
                        icon-class="stat-icon-primary"
                        :value="$cursos->total()"
                        label="Total de Cursos"
                    />
                </div>
                <div class="col-md-4">
                    <x-stat-card
                        icon="bi-check-circle"
                        icon-class="stat-icon-success"
                        :value="$cursos->where('activo', true)->count()"
                        label="Ativos"
                    />
                </div>
                <div class="col-md-4">
                    <x-stat-card
                        icon="bi-pause-circle"
                        icon-class="stat-icon-warning"
                        :value="$cursos->where('activo', false)->count()"
                        label="Inativos"
                    />
                </div>
            </div>

            <x-data-table
                title="Lista de Cursos"
                icon="bi-list-ul"
                :badge="$cursos->total() . ' registos'"
                :pagination="$cursos"
            >
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="text-nowrap" style="width: 120px;">Código</th>
                                    <th class="text-nowrap">Nome</th>
                                    <th class="text-nowrap text-center">Disciplinas</th>
                                    <th class="text-nowrap text-center">Turmas</th>
                                    <th class="text-nowrap text-center" style="width: 100px;">Estado</th>
                                    <th class="text-nowrap text-end" style="width: 150px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($cursos as $curso)
                                    <tr class="align-middle">
                                        <td class="text-nowrap">
                                            <span class="badge bg-light text-dark border">
                                                <i class="bi bi-hash"></i>{{ $curso->codigo }}
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <strong>{{ $curso->nome }}</strong>
                                            @if($curso->descricao)
                                                <br><small class="text-muted">{{ Str::limit($curso->descricao, 50) }}</small>
                                            @endif
                                        </td>
                                        <td class="text-nowrap text-center">
                                            <span class="badge bg-info">{{ $curso->disciplinas_count }}</span>
                                        </td>
                                        <td class="text-nowrap text-center">
                                            <span class="badge bg-purple">{{ $curso->turmas_count }}</span>
                                        </td>
                                        <td class="text-nowrap text-center">
                                            @if($curso->activo)
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
                                                :view-url="route('cursos.show', $curso)"
                                                :edit-url="Gate::allows('update', $curso) ? route('cursos.edit', $curso) : null"
                                                :delete-route="Gate::allows('delete', $curso) ? 'cursos.destroy' : null"
                                                :delete-id="$curso->id"
                                                :delete-name="'o curso ' . $curso->nome"
                                            />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            <x-empty-state
                                                icon="bi-journals"
                                                message="Nenhum curso encontrado."
                                                :create-url="route('cursos.create')"
                                                create-label="Criar primeiro curso"
                                                :can-create="Gate::allows('create', App\Models\Curso::class)"
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
