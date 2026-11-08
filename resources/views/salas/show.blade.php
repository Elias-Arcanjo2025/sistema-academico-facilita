<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Detalhes da Sala</h2>
            <a href="{{ route('salas.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Informações da Sala</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Código</dt>
                                <dd class="col-sm-8"><code>{{ $sala->codigo }}</code></dd>

                                <dt class="col-sm-4">Nome</dt>
                                <dd class="col-sm-8">{{ $sala->nome }}</dd>

                                <dt class="col-sm-4">Capacidade</dt>
                                <dd class="col-sm-8">{{ $sala->capacidade ? $sala->capacidade . ' alunos' : '-' }}</dd>

                                <dt class="col-sm-4">Estado</dt>
                                <dd class="col-sm-8">
                                    @if($sala->activo)
                                        <span class="badge bg-success">Ativo</span>
                                    @else
                                        <span class="badge bg-secondary">Inativo</span>
                                    @endif
                                </dd>

                                <dt class="col-sm-4">Criado em</dt>
                                <dd class="col-sm-8">{{ $sala->created_at->format('d/m/Y H:i') }}</dd>

                                <dt class="col-sm-4">Actualizado em</dt>
                                <dd class="col-sm-8">{{ $sala->updated_at->format('d/m/Y H:i') }}</dd>
                            </dl>
                        </div>
                        @can('update', $sala)
                            <div class="card-footer">
                                <a href="{{ route('salas.edit', $sala) }}" class="btn btn-primary">
                                    <i class="bi bi-pencil"></i> Editar
                                </a>
                            </div>
                        @endcan
                    </div>

                    @if($sala->turmas->isNotEmpty())
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Turmas nesta Sala ({{ $sala->turmas->count() }})</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Curso</th>
                                                <th>Ano Lectivo</th>
                                                <th>Turno</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($sala->turmas as $turma)
                                                <tr>
                                                    <td>
                                                        <a href="{{ route('turmas.show', $turma) }}">{{ $turma->nome }}</a>
                                                    </td>
                                                    <td>{{ $turma->curso->nome }}</td>
                                                    <td>{{ $turma->ano_lectivo }}</td>
                                                    <td>{{ $turma->turno->nome }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
