<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0"><i class="bi bi-list-check me-2"></i>Conteúdos mínimos</h2>
            @if($podeGerir)
                <a href="{{ route('pedagogico.conteudos.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Novo programa</a>
            @endif
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-list-check" variant="info" title="Conteúdos mínimos oficiais" id="tip-conteudos-index" compact>
                Referencial oficial por disciplina e classe. O coordenador de disciplina elabora o rascunho; a direcção pedagógica publica. Não substitui a planificação do professor.
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
                    @forelse($programas as $p)
                        <a href="{{ route('pedagogico.conteudos.show', $p) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-semibold">{{ $p->titulo }}</div>
                                <div class="small text-muted">
                                    {{ $p->disciplina?->nome }} · {{ $p->classe?->nome }}
                                    @if($p->curso) · {{ $p->curso->nome }} @endif
                                    · v{{ $p->versao }}
                                </div>
                            </div>
                            <span class="badge bg-{{ $p->estado === 'publicado' ? 'success' : ($p->estado === 'rascunho' ? 'secondary' : 'warning') }}">
                                {{ $estados[$p->estado] ?? $p->estado }}
                            </span>
                        </a>
                    @empty
                        <div class="p-4 text-muted text-center">Sem programas neste filtro.</div>
                    @endforelse
                </div>
                @if($programas->hasPages())
                    <div class="p-3">{{ $programas->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
