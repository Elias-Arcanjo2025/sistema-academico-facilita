<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between">
            <h2 class="h4 mb-0">Instrumentos de prova</h2>
            @can('pedagogico.ia.gerar')
                <a href="{{ route('pedagogico.provas.create') }}" class="btn btn-primary btn-sm">Gerar instrumento</a>
            @endcan
        </div>
    </x-slot>
    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-file-earmark-text" variant="info" title="Instrumentos de prova" id="tip-provas-index" compact>
                Gere instrumentos com IA a partir de uma planificação activa, ou importe questões do banco. Depois de criar, submeta para aprovação do coordenador antes de publicar.
            </x-tip-card>
            @foreach (['success' => 'success', 'error' => 'danger'] as $key => $variant)
                @if(session($key))<div class="alert alert-{{ $variant }}">{{ session($key) }}</div>@endif
            @endforeach
            <form method="GET" class="card mb-4">
                <div class="card-body">
                    <select name="planificacao_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Seleccione planificação...</option>
                        @foreach($planificacoes as $p)
                            <option value="{{ $p->id }}" @selected(request('planificacao_id') == $p->id)>
                                {{ $p->turma->nome ?? '' }} — {{ $p->disciplina->nome ?? '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
            @if($planificacao)
            <div class="card">
                <table class="table mb-0">
                    <thead><tr><th>Tipo</th><th>Turma</th><th>Estado</th><th>Cotação</th><th></th></tr></thead>
                    <tbody>
                        @forelse($provas as $prova)
                        <tr>
                            <td>{{ $prova->tipo }}</td>
                            <td>{{ $prova->turma->nome ?? '-' }}</td>
                            <td>{{ $prova->estado }}</td>
                            <td>{{ $prova->cotacao_total }}</td>
                            <td class="text-end">
                                <a href="{{ route('pedagogico.provas.show', $prova) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted">Sem provas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
