<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0"><i class="bi bi-megaphone me-2"></i>Campanhas de notificação</h2>
            <a href="{{ route('secretaria.campanhas.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Nova campanha
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Secretaria', 'url' => route('secretaria.dashboard')],
                ['label' => 'Campanhas'],
            ]" />

            <x-tip-card icon="bi-bell" variant="info" title="Notificações do sistema" id="tip-campanhas" compact>
                Envie avisos in-app a professores, staff, alunos ou encarregados. Não envia SMS.
            </x-tip-card>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card shadow-soft">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Quando</th>
                                <th>Título</th>
                                <th>Público</th>
                                <th>Turma</th>
                                <th>Enviados</th>
                                <th>Por</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($itens as $item)
                                <tr>
                                    <td class="text-nowrap small">{{ $item->created_at?->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $item->titulo }}</div>
                                        <div class="small text-muted text-truncate" style="max-width:280px">{{ $item->mensagem }}</div>
                                    </td>
                                    <td>{{ $publicos[$item->publico] ?? $item->publico }}</td>
                                    <td>{{ $item->turma?->nome ?? '—' }}</td>
                                    <td>{{ $item->enviados }}</td>
                                    <td>{{ $item->autor?->name ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Ainda sem campanhas enviadas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($itens->hasPages())
                    <div class="p-3">{{ $itens->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
