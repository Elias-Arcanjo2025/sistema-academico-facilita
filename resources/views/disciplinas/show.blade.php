<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Detalhes da Disciplina</h2>
            <a href="{{ route('disciplinas.index') }}" class="btn btn-outline-secondary">
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
                            <h5 class="mb-0">Informações da Disciplina</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Código</dt>
                                <dd class="col-sm-8"><code>{{ $disciplina->codigo }}</code></dd>

                                <dt class="col-sm-4">Nome</dt>
                                <dd class="col-sm-8">{{ $disciplina->nome }}</dd>

                                <dt class="col-sm-4">Tipo de Disciplina</dt>
                                <dd class="col-sm-8">
                                    <span class="badge bg-info">{{ $disciplina->tipo_label }}</span>
                                </dd>

                                <dt class="col-sm-4">Ordem de Apresentação</dt>
                                <dd class="col-sm-8">
                                    <span class="badge bg-secondary">{{ $disciplina->ordem_apresentacao }}</span>
                                </dd>

                                <dt class="col-sm-4">Cursos</dt>
                                <dd class="col-sm-8">
                                    @if($disciplina->cursos->count() > 0)
                                        @foreach($disciplina->cursos as $curso)
                                            <a href="{{ route('cursos.show', $curso) }}" class="badge bg-primary text-decoration-none">
                                                {{ $curso->nome }}
                                            </a>
                                        @endforeach
                                    @else
                                        <span class="text-muted">Sem cursos associados</span>
                                    @endif
                                </dd>

                                <dt class="col-sm-4">Descrição</dt>
                                <dd class="col-sm-8">{{ $disciplina->descricao ?: '-' }}</dd>

                                <dt class="col-sm-4">Estado</dt>
                                <dd class="col-sm-8">
                                    @if($disciplina->activo)
                                        <span class="badge bg-success">Ativo</span>
                                    @else
                                        <span class="badge bg-secondary">Inativo</span>
                                    @endif
                                </dd>

                                <dt class="col-sm-4">Criado em</dt>
                                <dd class="col-sm-8">{{ $disciplina->created_at->format('d/m/Y H:i') }}</dd>

                                <dt class="col-sm-4">Atualizado em</dt>
                                <dd class="col-sm-8">{{ $disciplina->updated_at->format('d/m/Y H:i') }}</dd>
                            </dl>
                        </div>
                        @can('update', $disciplina)
                            <div class="card-footer">
                                <a href="{{ route('disciplinas.edit', $disciplina) }}" class="btn btn-primary">
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
