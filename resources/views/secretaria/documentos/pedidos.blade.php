<x-app-layout>
    <x-slot name="header">Pedidos de documentos (portal)</x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Secretaria', 'url' => route('secretaria.dashboard')],
                ['label' => 'Documentos', 'url' => route('secretaria.documentos.index')],
                ['label' => 'Pedidos do portal'],
            ]" />

            <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="{{ route('secretaria.documentos.pedidos') }}"
                   class="btn btn-sm {{ $estado === '' ? 'btn-primary' : 'btn-outline-primary' }}">
                    Em fila
                    <span class="badge bg-light text-dark ms-1">{{ (int) (($contagens['pendente'] ?? 0) + ($contagens['em_preparacao'] ?? 0) + ($contagens['pronto'] ?? 0)) }}</span>
                </a>
                @foreach(['pendente' => 'Pendente', 'em_preparacao' => 'Em preparação', 'pronto' => 'Prontos', 'entregue' => 'Entregues', 'cancelado' => 'Cancelados'] as $key => $label)
                    <a href="{{ route('secretaria.documentos.pedidos', ['estado' => $key]) }}"
                       class="btn btn-sm {{ $estado === $key ? 'btn-secondary' : 'btn-outline-secondary' }}">
                        {{ $label }}
                        @if(isset($contagens[$key]))
                            <span class="badge bg-light text-dark ms-1">{{ $contagens[$key] }}</span>
                        @endif
                    </a>
                @endforeach
                <a href="{{ route('secretaria.documentos.index') }}" class="btn btn-sm btn-outline-dark ms-auto">Emitir PDF</a>
            </div>

            <div class="content-card">
                <div class="content-card-header">
                    <h6 class="content-card-title mb-0"><i class="bi bi-inbox me-2"></i>Fila de pedidos</h6>
                </div>
                <div class="content-card-body p-0">
                    @if($pedidos->isEmpty())
                        <div class="p-4">
                            <x-empty-state icon="bi-inbox" message="Sem pedidos neste filtro." :can-create="false" />
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>Quando</th>
                                        <th>Aluno</th>
                                        <th>Documento</th>
                                        <th>Encarregado</th>
                                        <th>Estado</th>
                                        <th style="min-width: 220px;">Acção</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pedidos as $pedido)
                                        <tr>
                                            <td class="small text-muted">{{ $pedido->created_at?->format('d/m/Y H:i') }}</td>
                                            <td>
                                                <div class="fw-semibold">{{ $pedido->aluno?->nome }}</div>
                                                <div class="small text-muted">
                                                    {{ $pedido->aluno?->numero_processo }}
                                                    @if($pedido->aluno?->turma)
                                                        · {{ $pedido->aluno->turma->codigo ?? $pedido->aluno->turma->nome }}
                                                    @endif
                                                </div>
                                                @if($pedido->observacao)
                                                    <div class="small text-muted mt-1"><i class="bi bi-chat-quote"></i> {{ $pedido->observacao }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $pedido->tipoLabel() }}</td>
                                            <td>
                                                <div>{{ $pedido->encarregado?->nome }}</div>
                                                <div class="small text-muted">{{ $pedido->encarregado?->telefone }}</div>
                                            </td>
                                            <td><span class="badge bg-secondary-subtle text-dark">{{ $pedido->estadoLabel() }}</span></td>
                                            <td>
                                                <form method="POST" action="{{ route('secretaria.documentos.pedidos.actualizar', $pedido) }}" class="d-flex flex-column gap-1">
                                                    @csrf
                                                    @method('PATCH')
                                                    <select name="estado" class="form-select form-select-sm">
                                                        @foreach($estados as $valor => $rotulo)
                                                            <option value="{{ $valor }}" @selected($pedido->estado === $valor)>{{ $rotulo }}</option>
                                                        @endforeach
                                                    </select>
                                                    <input type="text" name="nota_secretaria" class="form-control form-control-sm"
                                                           value="{{ $pedido->nota_secretaria }}"
                                                           placeholder="Nota ao encarregado">
                                                    <div class="d-flex gap-1">
                                                        <button class="btn btn-sm btn-primary">Actualizar</button>
                                                        <a class="btn btn-sm btn-outline-secondary"
                                                           href="{{ route('secretaria.documentos.emitir', ['aluno_id' => $pedido->aluno_id, 'tipo' => $pedido->tipo]) }}"
                                                           target="_blank">PDF</a>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3">{{ $pedidos->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
