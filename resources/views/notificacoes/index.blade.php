<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Notificações</h2>
            @if($notificacoesNaoLidas > 0)
                <form method="POST" action="{{ route('notificacoes.marcar-todas-lidas') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-check2-all me-1"></i> Marcar todas como lidas
                    </button>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Notificações', 'url' => route('notificacoes.index')],
            ]" />

            <x-tip-card icon="bi-bell" variant="info" title="As suas notificações" id="tip-notificacoes-index" compact>
                Alertas e avisos do sistema. Clique numa notificação para a abrir e marcar como lida.
            </x-tip-card>

            <form method="GET" class="row g-2 mb-3">
                <div class="col-auto">
                    <select name="estado" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="todas" @selected(($filtroEstado ?? 'todas') === 'todas')>Todas</option>
                        <option value="nao_lidas" @selected(($filtroEstado ?? '') === 'nao_lidas')>Não lidas</option>
                    </select>
                </div>
                <div class="col-auto">
                    <input type="text" name="tipo" class="form-control form-control-sm" placeholder="Tipo (opcional)"
                           value="{{ $filtroTipo ?? '' }}">
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-outline-secondary">Filtrar</button>
                </div>
            </form>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($notificacoes->isEmpty())
                <div class="card">
                    <div class="card-body">
                        <x-empty-state
                            icon="bi-bell"
                            message="Sem notificações."
                            :can-create="false"
                        />
                    </div>
                </div>
            @else
                <x-data-table
                    title="Lista de notificações"
                    icon="bi-bell"
                    :badge="$notificacoes->total()"
                    :pagination="$notificacoes">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-nowrap">Notificação</th>
                                <th class="text-nowrap">Quando</th>
                                <th class="text-nowrap">Estado</th>
                                <th class="text-nowrap text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($notificacoes as $notificacao)
                                @php
                                    $dados = $notificacao->data ?? [];
                                    $titulo = $dados['titulo'] ?? null;
                                    $mensagem = $dados['mensagem'] ?? $dados['message'] ?? 'Nova notificação';
                                    $lida = $notificacao->read_at !== null;
                                    $url = route('notificacoes.abrir', $notificacao->id);
                                @endphp
                                <tr class="{{ $lida ? '' : 'table-light' }}">
                                    <td>
                                        <div class="d-flex gap-2 align-items-start">
                                            <i class="bi bi-bell {{ $lida ? 'text-muted' : 'text-primary' }} mt-1"></i>
                                            <div>
                                                @if($titulo)
                                                    <div class="fw-semibold">{{ $titulo }}</div>
                                                    <small class="text-muted">{{ $mensagem }}</small>
                                                @else
                                                    <div class="{{ $lida ? '' : 'fw-semibold' }}">{{ $mensagem }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-nowrap">
                                        <small class="text-muted">{{ $notificacao->created_at?->diffForHumans() }}</small>
                                    </td>
                                    <td class="text-nowrap">
                                        @if($lida)
                                            <span class="badge bg-secondary">Lida</span>
                                        @else
                                            <span class="badge bg-primary">Nova</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap text-end">
                                        <a href="{{ $url }}" class="btn btn-sm btn-outline-primary" title="Abrir">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @unless($lida)
                                            <form method="POST" action="{{ route('notificacoes.marcar-lida', $notificacao->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Marcar como lida">
                                                    <i class="bi bi-check2"></i>
                                                </button>
                                            </form>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-data-table>
            @endif
        </div>
    </div>
</x-app-layout>
