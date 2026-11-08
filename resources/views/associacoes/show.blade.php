<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Detalhes da Associacao</h2>
            <a href="{{ route('associacoes.index') }}" class="btn btn-outline-secondary">
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
                            <h5 class="mb-0">Informações da Associação</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Professor</dt>
                                <dd class="col-sm-8">{{ $associacao->professor->name }}</dd>

                                <dt class="col-sm-4">Disciplina</dt>
                                <dd class="col-sm-8">
                                    {{ $associacao->disciplina->nome }}
                                    <br>
                                    <small class="text-muted">Código: {{ $associacao->disciplina->codigo }}</small>
                                </dd>

                                <dt class="col-sm-4">Curso</dt>
                                <dd class="col-sm-8">
                                    @if($associacao->turma?->curso)
                                        <a href="{{ route('cursos.show', $associacao->turma->curso) }}">
                                            {{ $associacao->turma->curso->nome }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </dd>

                                <dt class="col-sm-4">Turma</dt>
                                <dd class="col-sm-8">
                                    <a href="{{ route('turmas.show', $associacao->turma) }}">
                                        {{ $associacao->turma->nome }}
                                    </a>
                                </dd>

                                <dt class="col-sm-4">Turno</dt>
                                <dd class="col-sm-8">{{ $associacao->turma->turno?->nome ?? '—' }}</dd>

                                <dt class="col-sm-4">Sala</dt>
                                <dd class="col-sm-8">{{ $associacao->turma->sala?->nome ?? '—' }}</dd>

                                <dt class="col-sm-4">Ano Lectivo</dt>
                                <dd class="col-sm-8">{{ $associacao->ano_lectivo }}</dd>

                                <dt class="col-sm-4">Estado</dt>
                                <dd class="col-sm-8">
                                    @if($associacao->activo)
                                        <span class="badge bg-success">Ativo</span>
                                    @else
                                        <span class="badge bg-secondary">Inativo</span>
                                    @endif
                                </dd>

                                <dt class="col-sm-4">Notas Lançadas</dt>
                                <dd class="col-sm-8">
                                    <span class="badge bg-info">{{ $notasCount }}</span>
                                </dd>

                                <dt class="col-sm-4">Criado em</dt>
                                <dd class="col-sm-8">{{ $associacao->created_at->format('d/m/Y H:i') }}</dd>

                                <dt class="col-sm-4">Atualizado em</dt>
                                <dd class="col-sm-8">{{ $associacao->updated_at->format('d/m/Y H:i') }}</dd>
                            </dl>
                        </div>
                        @can('update', $associacao)
                            <div class="card-footer">
                                <a href="{{ route('associacoes.edit', $associacao) }}" class="btn btn-primary">
                                    <i class="bi bi-pencil"></i> Editar
                                </a>
                                @if($notasCount > 0)
                                    <span class="text-muted ms-2">
                                        <i class="bi bi-info-circle"></i>
                                        Esta associacao tem notas lançadas e não pode ser eliminada.
                                    </span>
                                @endif
                            </div>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
