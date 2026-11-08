<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Detalhes do Registo de Auditoria</h2>
            <a href="{{ route('auditoria.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <!-- Informações Principais -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="bi bi-info-circle"></i> Informações do Registo
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-3">ID</dt>
                                <dd class="col-sm-9">{{ $activity->id }}</dd>

                                <dt class="col-sm-3">Data/Hora</dt>
                                <dd class="col-sm-9">{{ $activity->created_at->format('d/m/Y H:i:s') }}</dd>

                                <dt class="col-sm-3">Utilizador</dt>
                                <dd class="col-sm-9">
                                    @if($activity->causer)
                                        {{ $activity->causer->name }}
                                        <small class="text-muted">({{ $activity->causer->email }})</small>
                                    @else
                                        <span class="text-muted">Sistema</span>
                                    @endif
                                </dd>

                                <dt class="col-sm-3">Tipo de Log</dt>
                                <dd class="col-sm-9">
                                    <span class="badge bg-secondary">{{ $activity->log_name }}</span>
                                </dd>

                                <dt class="col-sm-3">Evento</dt>
                                <dd class="col-sm-9">
                                    @switch($activity->event)
                                        @case('created')
                                            <span class="badge bg-success">Criado</span>
                                            @break
                                        @case('updated')
                                            <span class="badge bg-warning text-dark">Atualizado</span>
                                            @break
                                        @case('deleted')
                                            <span class="badge bg-danger">Eliminado</span>
                                            @break
                                        @default
                                            <span class="badge bg-info">{{ $activity->event ?? '-' }}</span>
                                    @endswitch
                                </dd>

                                <dt class="col-sm-3">Descrição</dt>
                                <dd class="col-sm-9">{{ $activity->description }}</dd>
                            </dl>
                        </div>
                    </div>

                    <!-- Entidade Afectada -->
                    @if($activity->subject_type)
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-box"></i> Entidade Afetada
                            </div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-sm-3">Tipo</dt>
                                    <dd class="col-sm-9">
                                        {{ class_basename($activity->subject_type) }}
                                        <small class="text-muted">({{ $activity->subject_type }})</small>
                                    </dd>

                                    <dt class="col-sm-3">ID</dt>
                                    <dd class="col-sm-9">{{ $activity->subject_id ?? '-' }}</dd>

                                    @if($activity->subject)
                                        <dt class="col-sm-3">Estado Atual</dt>
                                        <dd class="col-sm-9">
                                            <span class="badge bg-success">Existe</span>
                                        </dd>
                                    @else
                                        <dt class="col-sm-3">Estado Atual</dt>
                                        <dd class="col-sm-9">
                                            <span class="badge bg-secondary">Eliminado/Nao encontrado</span>
                                        </dd>
                                    @endif
                                </dl>
                            </div>
                        </div>
                    @endif

                    <!-- Propriedades/Detalhes -->
                    @if($activity->properties && $activity->properties->count() > 0)
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-code-square"></i> Detalhes da Alteração
                            </div>
                            <div class="card-body">
                                @if($activity->properties->has('old') && $activity->properties->has('attributes'))
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="text-danger">Valores Anteriores</h6>
                                            <div class="bg-light p-3 rounded">
                                                <pre class="mb-0" style="font-size: 12px; white-space: pre-wrap;">{{ json_encode($activity->properties['old'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-success">Valores Novos</h6>
                                            <div class="bg-light p-3 rounded">
                                                <pre class="mb-0" style="font-size: 12px; white-space: pre-wrap;">{{ json_encode($activity->properties['attributes'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                @elseif($activity->properties->has('attributes'))
                                    <h6 class="text-success">Valores</h6>
                                    <div class="bg-light p-3 rounded">
                                        <pre class="mb-0" style="font-size: 12px; white-space: pre-wrap;">{{ json_encode($activity->properties['attributes'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                @else
                                    <div class="bg-light p-3 rounded">
                                        <pre class="mb-0" style="font-size: 12px; white-space: pre-wrap;">{{ json_encode($activity->properties->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                <div class="col-md-4">
                    <!-- Ações Rápidas -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="bi bi-lightning"></i> Ações Rápidas
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                @if($activity->causer)
                                    <a href="{{ route('auditoria.index', ['user_id' => $activity->causer_id]) }}" class="btn btn-outline-primary">
                                        <i class="bi bi-person"></i> Ver logs deste utilizador
                                    </a>
                                @endif

                                @if($activity->subject_type)
                                    <a href="{{ route('auditoria.index', ['subject_type' => $activity->subject_type]) }}" class="btn btn-outline-primary">
                                        <i class="bi bi-box"></i> Ver logs desta entidade
                                    </a>
                                @endif

                                <a href="{{ route('auditoria.index', ['data_inicio' => $activity->created_at->format('Y-m-d'), 'data_fim' => $activity->created_at->format('Y-m-d')]) }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-calendar"></i> Ver logs deste dia
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Metadados -->
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-gear"></i> Metadados
                        </div>
                        <div class="card-body">
                            <dl class="mb-0">
                                <dt>Batch UUID</dt>
                                <dd class="text-muted small">{{ $activity->batch_uuid ?? '-' }}</dd>

                                <dt>Log Name</dt>
                                <dd class="text-muted small">{{ $activity->log_name }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
