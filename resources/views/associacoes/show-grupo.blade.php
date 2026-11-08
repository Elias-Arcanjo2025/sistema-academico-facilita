<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Detalhes do Grupo de Associações</h2>
            <div class="d-flex gap-2">
                @can('update', $associacoes->first())
                    <a href="{{ route('associacoes.edit-grupo', ['professor_id' => $professor->id, 'disciplina_id' => $disciplina->id, 'ano_lectivo' => $anoLectivo]) }}"
                       class="btn btn-primary">
                        <i class="bi bi-pencil me-1"></i>Editar Grupo
                    </a>
                @endcan
                <a href="{{ route('associacoes.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Voltar
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <!-- Informações do Grupo -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card shadow-soft">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informações do Grupo</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-muted">Professor</label>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-2">
                                            {{ strtoupper(substr($professor->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="fw-bold">{{ $professor->name }}</div>
                                            <small class="text-muted">{{ $professor->email }}</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold text-muted">Disciplina</label>
                                    <div>
                                        <span class="badge bg-info" style="font-size: 0.9rem;">{{ $disciplina->nome }}</span>
                                        @if($disciplina->codigo)
                                            <br>
                                            <small class="text-muted">Código: {{ $disciplina->codigo }}</small>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-bold text-muted">Ano Lectivo</label>
                                    <div>
                                        <span class="badge bg-light text-dark border">{{ $anoLectivo }}</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold text-muted">Estatísticas</label>
                                    <div>
                                        <span class="badge bg-purple me-1">{{ $associacoes->count() }} Turmas</span>
                                        <span class="badge bg-success me-1">{{ $associacoes->where('activo', true)->count() }} Activas</span>
                                        @if($associacoes->where('activo', false)->count() > 0)
                                            <span class="badge bg-secondary">{{ $associacoes->where('activo', false)->count() }} Inactivas</span>
                                        @endif
                                    </div>
                                    @if($notasCount > 0)
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                <i class="bi bi-file-earmark-text me-1"></i>{{ $notasCount }} notas lançadas
                                            </small>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lista de Turmas -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card shadow-soft">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-people me-2"></i>Turmas Associadas</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 25%;">Turma</th>
                                            <th style="width: 30%;">Curso</th>
                                            <th style="width: 15%;" class="text-center">Sala</th>
                                            <th style="width: 15%;" class="text-center">Turno</th>
                                            <th style="width: 10%;" class="text-center">Estado</th>
                                            <th style="width: 5%;" class="text-center">Acções</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($associacoes as $associacao)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('turmas.show', $associacao->turma) }}"
                                                       class="text-decoration-none fw-bold">
                                                        {{ $associacao->turma->nome }}
                                                    </a>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border">
                                                        {{ $associacao->turma->curso->nome }}
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    @if($associacao->turma->sala)
                                                        <span class="badge bg-info">{{ $associacao->turma->sala->nome }}</span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if($associacao->turma->turno)
                                                        <span class="badge bg-warning text-dark">{{ $associacao->turma->turno->nome }}</span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if($associacao->activo)
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle me-1"></i>Activo
                                                        </span>
                                                    @else
                                                        <span class="badge bg-secondary">
                                                            <i class="bi bi-pause-circle me-1"></i>Inactivo
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @can('view', $associacao)
                                                        <a href="{{ route('associacoes.show', $associacao) }}"
                                                           class="btn btn-sm btn-outline-primary"
                                                           title="Ver detalhes individuais">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
