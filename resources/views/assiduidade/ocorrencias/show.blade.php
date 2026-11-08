<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Ocorrência</h2>
            <a href="{{ route('ocorrencias.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container col-lg-8">
            <x-breadcrumb :items="[
                ['label' => 'Assiduidade', 'url' => route('presencas.index')],
                ['label' => 'Ocorrências', 'url' => route('ocorrencias.index')],
                ['label' => $ocorrencia->aluno?->nome ?? 'Detalhe', 'url' => route('ocorrencias.show', $ocorrencia)],
            ]" />

            <x-tip-card icon="bi-exclamation-triangle" variant="info" title="Detalhe da ocorrência" id="tip-ocorrencias-show" compact>
                Consulte tipo, severidade e medidas. Se estiver aberta e tiver permissão, pode marcá-la como resolvida.
            </x-tip-card>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-start gap-2 flex-wrap">
                    <div>
                        <h5 class="mb-1">{{ $ocorrencia->aluno?->nome }}</h5>
                        <div class="small text-muted">
                            {{ $ocorrencia->turma?->nome }} · {{ $ocorrencia->data->format('d/m/Y') }}
                        </div>
                    </div>
                    @if($ocorrencia->estado === 'aberta')
                        <span class="badge bg-warning text-dark">Aberta</span>
                    @else
                        <span class="badge bg-success">Resolvida</span>
                    @endif
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Tipo</dt>
                        <dd class="col-sm-9">{{ $tipos[$ocorrencia->tipo] ?? $ocorrencia->tipo }}</dd>
                        <dt class="col-sm-3">Severidade</dt>
                        <dd class="col-sm-9">
                            <span class="badge bg-secondary">{{ $severidades[$ocorrencia->severidade] ?? $ocorrencia->severidade }}</span>
                        </dd>
                        <dt class="col-sm-3">Descrição</dt>
                        <dd class="col-sm-9">{{ $ocorrencia->descricao }}</dd>
                        <dt class="col-sm-3">Medida</dt>
                        <dd class="col-sm-9">{{ $ocorrencia->medida ?: '—' }}</dd>
                        <dt class="col-sm-3">Registado por</dt>
                        <dd class="col-sm-9">{{ $ocorrencia->registadoPor?->name }} · {{ $ocorrencia->created_at?->format('d/m/Y H:i') }}</dd>
                        @if($ocorrencia->resolvidoPor)
                            <dt class="col-sm-3">Resolvido por</dt>
                            <dd class="col-sm-9">{{ $ocorrencia->resolvidoPor->name }} · {{ $ocorrencia->resolvido_em?->format('d/m/Y H:i') }}</dd>
                        @endif
                    </dl>

                    @can('ocorrencias.resolver')
                        @if($ocorrencia->isAberta())
                            <hr>
                            <form method="POST" action="{{ route('ocorrencias.resolver', $ocorrencia) }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Medida / resolução</label>
                                    <textarea name="medida" class="form-control" rows="2" maxlength="2000">{{ old('medida', $ocorrencia->medida) }}</textarea>
                                </div>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check2-circle me-1"></i> Marcar como resolvida
                                </button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
