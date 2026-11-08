<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Detalhes da Turma</h2>
            <a href="{{ route('turmas.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Informações da Turma</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-3">Nome</dt>
                                <dd class="col-sm-9">{{ $turma->nome }}</dd>

                                <dt class="col-sm-3">Curso</dt>
                                <dd class="col-sm-9">
                                    <a href="{{ route('cursos.show', $turma->curso) }}">{{ $turma->curso->nome }}</a>
                                </dd>

                                <dt class="col-sm-3">Ano Lectivo</dt>
                                <dd class="col-sm-9">{{ $turma->ano_lectivo }}</dd>

                                <dt class="col-sm-3">Turno</dt>
                                <dd class="col-sm-9">
                                    <a href="{{ route('turnos.show', $turma->turno) }}">{{ $turma->turno->nome }}</a>
                                    @if($turma->turno->hora_inicio && $turma->turno->hora_fim)
                                        <span class="text-muted">
                                            ({{ \Carbon\Carbon::parse($turma->turno->hora_inicio)->format('H:i') }} - {{ \Carbon\Carbon::parse($turma->turno->hora_fim)->format('H:i') }})
                                        </span>
                                    @endif
                                </dd>

                                <dt class="col-sm-3">Sala</dt>
                                <dd class="col-sm-9">
                                    <a href="{{ route('salas.show', $turma->sala) }}">{{ $turma->sala->nome }}</a>
                                    @if($turma->sala->capacidade)
                                        <span class="text-muted">({{ $turma->sala->capacidade }} lugares)</span>
                                    @endif
                                </dd>

                                <dt class="col-sm-3">Estado</dt>
                                <dd class="col-sm-9">
                                    @if($turma->activo)
                                        <span class="badge bg-success">Ativo</span>
                                    @else
                                        <span class="badge bg-secondary">Inativo</span>
                                    @endif
                                </dd>

                                <dt class="col-sm-3">Criado em</dt>
                                <dd class="col-sm-9">{{ $turma->created_at->format('d/m/Y H:i') }}</dd>

                                <dt class="col-sm-3">Atualizado em</dt>
                                <dd class="col-sm-9">{{ $turma->updated_at->format('d/m/Y H:i') }}</dd>
                            </dl>
                        </div>
                        @can('update', $turma)
                            <div class="card-footer">
                                <a href="{{ route('turmas.edit', $turma) }}" class="btn btn-primary">
                                    <i class="bi bi-pencil"></i> Editar
                                </a>
                            </div>
                        @endcan
                    </div>

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Alunos da Turma ({{ $turma->alunos->count() }})</h5>
                            @if($turma->alunos->count() > 0)
                                <a href="{{ route('export.lista-alunos.pdf', ['turma_id' => $turma->id]) }}"
                                   class="btn btn-sm btn-success"
                                   target="_blank"
                                   title="Exportar lista de alunos em PDF">
                                    <i class="bi bi-file-earmark-pdf me-1"></i>Exportar PDF
                                </a>
                            @endif
                        </div>
                        <div class="card-body">
                            @if($turma->alunos->isEmpty())
                                <p class="text-muted mb-0">Nenhum aluno inscrito nesta turma.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>N.º</th>
                                                <th>Nome</th>
                                                <th>Número de Estudante</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($turma->alunos as $aluno)
                                                <tr>
                                                    <td>{{ $aluno->numero_ordem }}</td>
                                                    <td>{{ $aluno->nome }}</td>
                                                    <td><code>{{ $aluno->numero_estudante }}</code></td>
                                                    <td>
                                                        @if($aluno->activo)
                                                            <span class="badge bg-success">Ativo</span>
                                                        @else
                                                            <span class="badge bg-secondary">Inativo</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
