<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Detalhes da Nota</h2>
            <a href="{{ route('notas.index', ['turma_id' => $nota->turma_id, 'disciplina_id' => $nota->disciplina_id]) }}" class="btn btn-outline-secondary">
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
                            <h5 class="mb-0">Informações da Nota</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <dl class="row mb-0">
                                        <dt class="col-sm-5">Aluno</dt>
                                        <dd class="col-sm-7">
                                            <a href="{{ route('alunos.show', $nota->aluno) }}">{{ $nota->aluno->nome }}</a>
                                            <br>
                                            <small class="text-muted">N.º {{ $nota->aluno->numero_ordem }} - {{ $nota->aluno->numero_processo }}</small>
                                        </dd>

                                        <dt class="col-sm-5">Turma</dt>
                                        <dd class="col-sm-7">
                                            <a href="{{ route('turmas.show', $nota->turma) }}">{{ $nota->turma->nome }}</a>
                                        </dd>

                                        <dt class="col-sm-5">Curso</dt>
                                        <dd class="col-sm-7">{{ $nota->aluno->turma->curso->nome }}</dd>

                                        <dt class="col-sm-5">Disciplina</dt>
                                        <dd class="col-sm-7">{{ $nota->disciplina->nome }}</dd>
                                    </dl>
                                </div>
                                <div class="col-md-6">
                                    <dl class="row mb-0">
                                        <dt class="col-sm-5">Tipo</dt>
                                        <dd class="col-sm-7">{{ App\Models\Nota::getTipos()[$nota->tipo] ?? $nota->tipo }}</dd>

                                        <dt class="col-sm-5">Trimestre</dt>
                                        <dd class="col-sm-7">{{ $nota->trimestre ? $nota->trimestre . '.º Trimestre' : '-' }}</dd>

                                        <dt class="col-sm-5">Ano Lectivo</dt>
                                        <dd class="col-sm-7">{{ $nota->ano_lectivo }}</dd>

                                        <dt class="col-sm-5">Professor</dt>
                                        <dd class="col-sm-7">{{ $nota->professor->name }}</dd>
                                    </dl>
                                </div>
                            </div>

                            <div class="text-center mb-4">
                                <h3>Nota</h3>
                                <span class="display-1 {{ $nota->isPositiva() ? 'text-success' : 'text-danger' }}">
                                    {{ number_format($nota->valor, 0) }}
                                </span>
                                <p class="text-muted">
                                    @if($nota->isPositiva())
                                        <span class="badge bg-success">Positiva</span>
                                    @else
                                        <span class="badge bg-danger">Negativa</span>
                                    @endif
                                </p>
                            </div>

                            @if($nota->observacoes)
                                <div class="mb-3">
                                    <h6>Observações</h6>
                                    <p class="text-muted mb-0">{!! nl2br(e($nota->observacoes)) !!}</p>
                                </div>
                            @endif

                            <hr>

                            <div class="row">
                                <div class="col-md-6">
                                    <small class="text-muted">
                                        Criado em: {{ $nota->created_at->format('d/m/Y H:i') }}
                                    </small>
                                </div>
                                <div class="col-md-6 text-end">
                                    <small class="text-muted">
                                        Atualizado em: {{ $nota->updated_at->format('d/m/Y H:i') }}
                                    </small>
                                </div>
                            </div>
                        </div>
                        @can('update', $nota)
                            <div class="card-footer">
                                <a href="{{ route('notas.edit', $nota) }}" class="btn btn-primary">
                                    <i class="bi bi-pencil"></i> Alterar Nota
                                </a>
                            </div>
                        @endcan
                    </div>

                    @if($historico->isNotEmpty())
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Histórico de Alterações</h5>
                            </div>
                            <div class="card-body">
                                <div class="timeline">
                                    @foreach($historico as $actividade)
                                        <div class="mb-3 pb-3 border-bottom">
                                            <div class="d-flex justify-content-between">
                                                <strong>{{ $actividade->description }}</strong>
                                                <small class="text-muted">{{ $actividade->created_at->format('d/m/Y H:i') }}</small>
                                            </div>
                                            <small class="text-muted">
                                                Por: {{ $actividade->causer?->name ?? 'Sistema' }}
                                            </small>
                                            @if($actividade->properties->isNotEmpty())
                                                <div class="mt-2">
                                                    @if($actividade->properties->has('valor_anterior'))
                                                        <span class="badge bg-secondary">
                                                            {{ $actividade->properties['valor_anterior'] }} → {{ $actividade->properties['valor_novo'] }}
                                                        </span>
                                                    @endif
                                                    @if($actividade->properties->has('justificacao'))
                                                        <br>
                                                        <small class="text-muted">
                                                            Justificação: {{ $actividade->properties['justificacao'] }}
                                                        </small>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
