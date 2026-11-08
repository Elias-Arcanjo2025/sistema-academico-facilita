<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Mensagens</h2>
            <div class="d-flex gap-2">
                <a href="{{ route('mensagens-encarregados.create', ['tipo' => 'familia']) }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-people"></i> Encarregado
                </a>
                <a href="{{ route('mensagens-encarregados.create', ['tipo' => 'interna']) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-person-badge"></i> Colaborador
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container" style="max-width: 52rem;">
            <x-tip-card icon="bi-chat-dots" variant="info" title="Dois canais de comunicação" id="tip-msg-enc" compact>
                <strong>Encarregados</strong> — conversa centrada no educando (família ↔ escola).
                <strong>Colaboradores</strong> — mensagens internas entre professores, DT, coordenação, secretaria, etc.
            </x-tip-card>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <h6 class="text-muted text-uppercase small mb-2 mt-3">Internas</h6>
            <div class="card mb-4">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Colaborador</th>
                                <th>Cargo</th>
                                <th>Última mensagem</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($conversasInternas as $item)
                                <tr>
                                    <td>{{ $item->outro?->name ?? '—' }}</td>
                                    <td><span class="badge bg-secondary-subtle text-dark">{{ $item->cargo }}</span></td>
                                    <td class="small text-muted">{{ $item->last_message_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('mensagens-internas.show', $item->conversa) }}" class="btn btn-sm btn-outline-primary">Abrir</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">
                                        Sem conversas internas.
                                        <a href="{{ route('mensagens-encarregados.create', ['tipo' => 'interna']) }}">Contactar um colaborador</a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <h6 class="text-muted text-uppercase small mb-2">Famílias</h6>
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Aluno</th>
                                <th>Turma</th>
                                <th>Encarregado</th>
                                <th>Última mensagem</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($conversas as $conversa)
                                <tr>
                                    <td>{{ $conversa->aluno?->nome }}</td>
                                    <td>{{ $conversa->aluno?->turma?->codigo ?? $conversa->aluno?->turma?->nome }}</td>
                                    <td>{{ $conversa->encarregadoUser?->name }}</td>
                                    <td class="small text-muted">
                                        {{ $conversa->last_message_at?->format('d/m/Y H:i') ?? '—' }}
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('mensagens-encarregados.show', $conversa) }}" class="btn btn-sm btn-outline-primary">Abrir</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">
                                        Sem conversas com encarregados.
                                        <a href="{{ route('mensagens-encarregados.create', ['tipo' => 'familia']) }}">Iniciar</a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
