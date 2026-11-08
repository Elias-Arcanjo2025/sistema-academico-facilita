<x-app-layout>
    <x-slot name="header">Banco de questões</x-slot>

    <x-tip-card icon="bi-collection" variant="info" title="O que é o Banco de questões?" id="tip-banco-questoes-index" compact>
        Repositório reutilizável de perguntas por disciplina. Crie questões uma vez (manual ou com IA) e importe-as depois para várias provas (MAC, MTP, etc.), sem ter de as reescrever.
    </x-tip-card>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="content-card mb-3">
        <div class="content-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="content-card-title mb-0">Questões</h5>
            <div class="d-flex gap-2">
                @can('pedagogico.ia.gerar')
                    @can('pedagogico.banco.gerir')
                        <a href="{{ route('pedagogico.banco.create-ia') }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-stars"></i> Gerar com IA
                        </a>
                    @endcan
                @endcan
                @can('pedagogico.banco.gerir')
                    <a href="{{ route('pedagogico.banco.create') }}" class="btn btn-sm btn-primary">Nova questão</a>
                @endcan
            </div>
        </div>
        <div class="p-3 border-bottom">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <select name="disciplina_id" class="form-select form-select-sm">
                        <option value="">Todas as disciplinas</option>
                        @foreach($disciplinas as $d)
                            <option value="{{ $d->id }}" @selected(($filtros['disciplina_id'] ?? '') == $d->id)>{{ $d->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="estado" class="form-select form-select-sm">
                        <option value="activa" @selected(($filtros['estado'] ?? 'activa') === 'activa')>Activas</option>
                        <option value="arquivada" @selected(($filtros['estado'] ?? '') === 'arquivada')>Arquivadas</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-outline-secondary w-100">Filtrar</button>
                </div>
            </form>
        </div>
        <div class="list-group list-group-flush">
            @forelse($questoes as $q)
                <div class="list-group-item d-flex justify-content-between gap-2">
                    <div>
                        <div class="fw-semibold small">{{ $q->enunciado }}</div>
                        <div class="text-muted" style="font-size:.75rem">
                            {{ $q->disciplina?->nome }} · {{ $tipos[$q->tipo] ?? $q->tipo }}
                            · {{ $dificuldades[$q->dificuldade] ?? $q->dificuldade }} · {{ $q->cotacao }} val.
                        </div>
                    </div>
                    @can('pedagogico.banco.gerir')
                        <form method="POST" action="{{ route('pedagogico.banco.destroy', $q) }}"
                              data-confirm-title="Arquivar questão"
                              data-confirm="Arquivar esta questão do banco?"
                              data-confirm-ok="Sim, arquivar"
                              data-confirm-danger="1">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Arquivar</button>
                        </form>
                    @endcan
                </div>
            @empty
                <div class="p-4 text-muted text-center">Sem questões.</div>
            @endforelse
        </div>
        @if($questoes->hasPages())
            <div class="p-3">{{ $questoes->links() }}</div>
        @endif
    </div>
</x-app-layout>
