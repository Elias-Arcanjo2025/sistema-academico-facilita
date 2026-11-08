<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Detalhes do Curso</h2>
            <a href="{{ route('cursos.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Informações do Curso</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Código</dt>
                                <dd class="col-sm-8"><code>{{ $curso->codigo }}</code></dd>

                                <dt class="col-sm-4">Nome</dt>
                                <dd class="col-sm-8">{{ $curso->nome }}</dd>

                                <dt class="col-sm-4">Descrição</dt>
                                <dd class="col-sm-8">{{ $curso->descricao ?: '-' }}</dd>

                                <dt class="col-sm-4">Classe Máxima</dt>
                                <dd class="col-sm-8">{{ $curso->classe_max ? $curso->classe_max . 'ª Classe' : '-' }}</dd>

                                <dt class="col-sm-4">Regime de Cálculo</dt>
                                <dd class="col-sm-8">
                                    @if($curso->regime_calculo === \App\Models\Curso::REGIME_ADMINISTRACAO)
                                        <span class="badge bg-warning text-dark" title="Trimestres independentes, sem MFT2, CF=(MT1+MT2+MT3)/3">
                                            <i class="bi bi-briefcase me-1"></i>Administração
                                        </span>
                                    @else
                                        <span class="badge bg-info text-dark" title="MFT2=(MT1+MT2)/2, carry-overs CA-10/CA-11">
                                            <i class="bi bi-gear me-1"></i>Politécnico
                                        </span>
                                    @endif
                                </dd>

                                <dt class="col-sm-4">Estado</dt>
                                <dd class="col-sm-8">
                                    @if($curso->activo)
                                        <span class="badge bg-success">Ativo</span>
                                    @else
                                        <span class="badge bg-secondary">Inativo</span>
                                    @endif
                                </dd>

                                <dt class="col-sm-4">Criado em</dt>
                                <dd class="col-sm-8">{{ $curso->created_at->format('d/m/Y H:i') }}</dd>

                                <dt class="col-sm-4">Actualizado em</dt>
                                <dd class="col-sm-8">{{ $curso->updated_at->format('d/m/Y H:i') }}</dd>
                            </dl>
                        </div>
                        @can('update', $curso)
                            <div class="card-footer">
                                <a href="{{ route('cursos.edit', $curso) }}" class="btn btn-primary">
                                    <i class="bi bi-pencil"></i> Editar
                                </a>
                            </div>
                        @endcan
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Disciplinas ({{ $curso->disciplinas->count() }})</h5>
                        </div>
                        <div class="card-body">
                            @if($curso->disciplinas->count() > 0)
                                <ul class="list-group list-group-flush">
                                    @foreach($curso->disciplinas as $disciplina)
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                <code>{{ $disciplina->codigo }}</code>
                                                {{ $disciplina->nome }}
                                            </span>
                                            @if($disciplina->activo)
                                                <span class="badge bg-success">Ativo</span>
                                            @else
                                                <span class="badge bg-secondary">Inativo</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-muted mb-0">Nenhuma disciplina associada.</p>
                            @endif
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Turmas ({{ $curso->turmas->count() }})</h5>
                        </div>
                        <div class="card-body">
                            @if($curso->turmas->count() > 0)
                                <ul class="list-group list-group-flush">
                                    @foreach($curso->turmas as $turma)
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                <code>{{ $turma->codigo }}</code>
                                                {{ $turma->nome }}
                                            </span>
                                            <span class="text-muted">{{ $turma->ano_lectivo }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-muted mb-0">Nenhuma turma associada.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
