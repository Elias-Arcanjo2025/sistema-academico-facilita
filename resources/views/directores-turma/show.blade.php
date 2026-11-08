<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Detalhes do Director de Turma</h2>
            <a href="{{ route('directores-turma.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Informações</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Professor</dt>
                                <dd class="col-sm-8">{{ $director->director->name }}</dd>

                                <dt class="col-sm-4">Turma</dt>
                                <dd class="col-sm-8">
                                    <a href="{{ route('turmas.show', $director->turma) }}">
                                        {{ $director->turma->nome }}
                                    </a>
                                </dd>

                                <dt class="col-sm-4">Curso</dt>
                                <dd class="col-sm-8">
                                    <a href="{{ route('cursos.show', $director->turma->curso) }}">
                                        {{ $director->turma->curso->nome }}
                                    </a>
                                </dd>

                                <dt class="col-sm-4">Turno</dt>
                                <dd class="col-sm-8">{{ $director->turma->turno->nome }}</dd>

                                <dt class="col-sm-4">Sala</dt>
                                <dd class="col-sm-8">{{ $director->turma->sala->nome }}</dd>

                                <dt class="col-sm-4">Ano Lectivo</dt>
                                <dd class="col-sm-8">{{ $director->ano_lectivo }}</dd>

                                <dt class="col-sm-4">Estado</dt>
                                <dd class="col-sm-8">
                                    @if($director->activo)
                                        <span class="badge bg-success">Ativo</span>
                                    @else
                                        <span class="badge bg-secondary">Inativo</span>
                                    @endif
                                </dd>

                                <dt class="col-sm-4">Criado em</dt>
                                <dd class="col-sm-8">{{ $director->created_at->format('d/m/Y H:i') }}</dd>
                            </dl>
                        </div>
                        @can('update', $director)
                            <div class="card-footer">
                                <a href="{{ route('directores-turma.edit', $director) }}" class="btn btn-primary">
                                    <i class="bi bi-pencil"></i> Editar
                                </a>
                            </div>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
