<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0"><i class="bi bi-journal-bookmark me-2"></i>Orientações metodológicas</h2>
            @if($podeGerir)
                <a href="{{ route('pedagogico.orientacoes.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Nova orientação
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-lightbulb" variant="info" title="Orientações da disciplina" id="tip-orientacoes" compact>
                Orientações do coordenador para os professores da disciplina/classe. Professores só vêem as publicadas no seu âmbito.
            </x-tip-card>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card shadow-soft mb-3">
                <div class="card-body py-3">
                    <form method="GET" class="row g-2">
                        <div class="col-md-3">
                            <select name="disciplina_id" class="form-select form-select-sm">
                                <option value="">Todas as disciplinas</option>
                                @foreach($disciplinas as $d)
                                    <option value="{{ $d->id }}" @selected(($filtros['disciplina_id'] ?? '') == $d->id)>{{ $d->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="classe_id" class="form-select form-select-sm">
                                <option value="">Todas as classes</option>
                                @foreach($classes as $c)
                                    <option value="{{ $c->id }}" @selected(($filtros['classe_id'] ?? '') == $c->id)>{{ $c->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="estado" class="form-select form-select-sm">
                                <option value="">Todos os estados</option>
                                @foreach($estados as $v => $l)
                                    <option value="{{ $v }}" @selected(($filtros['estado'] ?? '') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-outline-secondary w-100">Filtrar</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-soft">
                <div class="list-group list-group-flush">
                    @forelse($itens as $item)
                        <a href="{{ route('pedagogico.orientacoes.show', $item) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-semibold">{{ $item->titulo }}</div>
                                <div class="small text-muted">
                                    {{ $item->disciplina?->nome }} · {{ $item->classe?->nome }}
                                    @if($item->autor) · {{ $item->autor->name }} @endif
                                </div>
                            </div>
                            <span class="badge bg-{{ $item->estado === 'publicado' ? 'success' : ($item->estado === 'rascunho' ? 'secondary' : 'warning') }}">
                                {{ $estados[$item->estado] ?? $item->estado }}
                            </span>
                        </a>
                    @empty
                        <div class="p-4 text-muted text-center">Sem orientações neste filtro.</div>
                    @endforelse
                </div>
                @if($itens->hasPages())
                    <div class="p-3">{{ $itens->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
