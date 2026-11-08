<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Logs de Auditoria</h2>
            <div>
                <a href="{{ route('auditoria.estatisticas') }}" class="btn btn-outline-primary">
                    <i class="bi bi-graph-up"></i> Estatisticas
                </a>
                <a href="{{ route('auditoria.exportar', request()->query()) }}" class="btn btn-success">
                    <i class="bi bi-download"></i> Exportar CSV
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-shield-check" variant="info" title="Registo de Auditoria" id="tip-auditoria">
                Consulte o histórico de todas as acções realizadas no sistema. Filtre por utilizador, tipo de acção ou período para encontrar registos específicos. A auditoria ajuda a manter a transparência e a segurança.
            </x-tip-card>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-funnel"></i> Filtros
                </div>
                <div class="card-body">
                    <form action="{{ route('auditoria.index') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Data Inicio</label>
                                <input type="date" class="form-control" name="data_inicio" value="{{ request('data_inicio') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Data Fim</label>
                                <input type="date" class="form-control" name="data_fim" value="{{ request('data_fim') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Utilizador</label>
                                <select class="form-select" name="user_id">
                                    <option value="">Todos</option>
                                    @foreach($utilizadores as $user)
                                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tipo de Log</label>
                                <select class="form-select" name="log_name">
                                    <option value="">Todos</option>
                                    @foreach($logNames as $name)
                                        <option value="{{ $name }}" {{ request('log_name') == $name ? 'selected' : '' }}>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Entidade</label>
                                <select class="form-select" name="subject_type">
                                    <option value="">Todas</option>
                                    @foreach($subjectTypes as $type)
                                        <option value="{{ $type['value'] }}" {{ request('subject_type') == $type['value'] ? 'selected' : '' }}>
                                            {{ $type['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Evento</label>
                                <select class="form-select" name="event">
                                    <option value="">Todos</option>
                                    @foreach($events as $event)
                                        <option value="{{ $event }}" {{ request('event') == $event ? 'selected' : '' }}>
                                            {{ ucfirst($event) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Pesquisa</label>
                                <input type="text" class="form-control" name="pesquisa" value="{{ request('pesquisa') }}" placeholder="Pesquisar na descrição...">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-search"></i> Filtrar
                                </button>
                                <a href="{{ route('auditoria.index') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de Logs -->
            <x-data-table
                title="Registos"
                icon="bi-list-ul"
                :badge="$logs->total() . ' registos'"
                :pagination="$logs"
            >
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th class="text-nowrap" style="width: 150px;">Data/Hora</th>
                                    <th class="text-nowrap" style="width: 150px;">Utilizador</th>
                                    <th class="text-nowrap" style="width: 100px;">Evento</th>
                                    <th class="text-nowrap">Descrição</th>
                                    <th class="text-nowrap" style="width: 100px;">Entidade</th>
                                    <th class="text-nowrap text-center" style="width: 80px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                    <tr>
                                        <td class="text-nowrap">
                                            <small>{{ $log->created_at->format('d/m/Y') }}</small><br>
                                            <small class="text-muted">{{ $log->created_at->format('H:i:s') }}</small>
                                        </td>
                                        <td class="text-nowrap">
                                            @if($log->causer)
                                                <span title="{{ $log->causer->email }}">{{ $log->causer->name }}</span>
                                            @else
                                                <span class="text-muted">Sistema</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            @switch($log->event)
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
                                                    <span class="badge bg-secondary">{{ $log->event ?? $log->log_name }}</span>
                                            @endswitch
                                        </td>
                                        <td class="text-nowrap">
                                            {{ Str::limit($log->description, 80) }}
                                        </td>
                                        <td class="text-nowrap">
                                            @if($log->subject_type)
                                                <small class="text-muted">{{ class_basename($log->subject_type) }}</small>
                                                @if($log->subject_id)
                                                    <br><small>#{{ $log->subject_id }}</small>
                                                @endif
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap text-center">
                                            <a href="{{ route('auditoria.show', $log) }}" class="btn btn-sm btn-outline-primary" title="Ver detalhes">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            <x-empty-state
                                                icon="bi-shield-check"
                                                message="Nenhum registo de auditoria encontrado."
                                            />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
            </x-data-table>
        </div>
    </div>
</x-app-layout>
