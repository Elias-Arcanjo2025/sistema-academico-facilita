<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Estatísticas de Auditoria</h2>
            <a href="{{ route('auditoria.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar aos Logs
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <!-- Seletor de Período -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="{{ route('auditoria.estatisticas') }}" method="GET" class="row g-3 align-items-center">
                        <div class="col-auto">
                            <label class="form-label mb-0">Período:</label>
                        </div>
                        <div class="col-auto">
                            <select class="form-select" name="periodo" onchange="this.form.submit()">
                                <option value="7" {{ $periodo == 7 ? 'selected' : '' }}>Últimos 7 dias</option>
                                <option value="30" {{ $periodo == 30 ? 'selected' : '' }}>Últimos 30 dias</option>
                                <option value="90" {{ $periodo == 90 ? 'selected' : '' }}>Últimos 90 dias</option>
                                <option value="365" {{ $periodo == 365 ? 'selected' : '' }}>Último ano</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row">
                <!-- Atividades por Dia -->
                <div class="col-md-8 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="bi bi-calendar3"></i> Atividades por Dia
                        </div>
                        <div class="card-body">
                            @if($actividadesPorDia->count() > 0)
                                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead class="sticky-top bg-white">
                                            <tr>
                                                <th>Data</th>
                                                <th class="text-end">Total</th>
                                                <th style="width: 50%;">Gráfico</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php $maxActividades = $actividadesPorDia->max('total'); @endphp
                                            @foreach($actividadesPorDia as $dia)
                                                <tr>
                                                    <td>{{ \Carbon\Carbon::parse($dia->data)->format('d/m/Y') }}</td>
                                                    <td class="text-end">{{ $dia->total }}</td>
                                                    <td>
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar bg-primary" role="progressbar"
                                                                 style="width: {{ ($dia->total / $maxActividades) * 100 }}%">
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-muted text-center mb-0">Sem dados para o período selecionado.</p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Top Utilizadores -->
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="bi bi-people"></i> Utilizadores Mais Activos
                        </div>
                        <div class="card-body p-0">
                            @if($utilizadoresMaisActivos->count() > 0)
                                <ul class="list-group list-group-flush">
                                    @foreach($utilizadoresMaisActivos as $index => $item)
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                <span class="badge bg-secondary me-2">{{ $index + 1 }}</span>
                                                {{ $item->causer?->name ?? 'Desconhecido' }}
                                            </span>
                                            <span class="badge bg-primary">{{ $item->total }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-muted text-center mb-0 p-3">Sem dados.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Atividades por Evento -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="bi bi-lightning"></i> Atividades por Tipo de Evento
                        </div>
                        <div class="card-body p-0">
                            @if($actividadesPorEvento->count() > 0)
                                <ul class="list-group list-group-flush">
                                    @foreach($actividadesPorEvento as $item)
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                @switch($item->event)
                                                    @case('created')
                                                        <span class="badge bg-success me-2">Criado</span>
                                                        @break
                                                    @case('updated')
                                                        <span class="badge bg-warning text-dark me-2">Atualizado</span>
                                                        @break
                                                    @case('deleted')
                                                        <span class="badge bg-danger me-2">Eliminado</span>
                                                        @break
                                                    @default
                                                        <span class="badge bg-secondary me-2">{{ $item->event ?? 'Outro' }}</span>
                                                @endswitch
                                            </span>
                                            <span class="badge bg-primary">{{ $item->total }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-muted text-center mb-0 p-3">Sem dados.</p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Atividades por Entidade -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="bi bi-box"></i> Atividades por Entidade
                        </div>
                        <div class="card-body p-0">
                            @if($actividadesPorEntidade->count() > 0)
                                <ul class="list-group list-group-flush">
                                    @foreach($actividadesPorEntidade as $item)
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>{{ $item->entidade }}</span>
                                            <span class="badge bg-info">{{ $item->total }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-muted text-center mb-0 p-3">Sem dados.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
