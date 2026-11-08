<x-app-layout>
    <x-slot name="header">Aprovações pedagógicas</x-slot>

    <x-tip-card icon="bi-clipboard-check" variant="info" title="Aprovações" id="tip-aprovacoes-index" compact>
        Aqui aparecem planificações e provas submetidas no seu escopo (disciplina e classes que coordena). Não vê nem aprova o seu próprio trabalho — outro coordenador ou a direcção trata desses casos.
    </x-tip-card>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="content-card">
                <div class="content-card-header"><h5 class="content-card-title mb-0">Planificações pendentes</h5></div>
                <div class="list-group list-group-flush">
                    @forelse($planificacoes as $p)
                        <a href="{{ route('pedagogico.planificacoes.show', $p) }}" class="list-group-item list-group-item-action">
                            <div class="fw-semibold">{{ $p->disciplina?->nome }} · {{ $p->turma?->nome }}</div>
                            <div class="small text-muted">{{ $p->professor?->name }} · {{ $p->submetido_em?->diffForHumans() }}</div>
                        </a>
                    @empty
                        <div class="p-3 text-muted small">Nenhuma planificação pendente.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="content-card">
                <div class="content-card-header"><h5 class="content-card-title mb-0">Provas pendentes</h5></div>
                <div class="list-group list-group-flush">
                    @forelse($provas as $prova)
                        <a href="{{ route('pedagogico.provas.show', $prova) }}" class="list-group-item list-group-item-action">
                            <div class="fw-semibold">{{ $prova->tipo }} · {{ $prova->disciplina?->nome }}</div>
                            <div class="small text-muted">{{ $prova->turma?->nome }} · {{ $prova->submetido_em?->diffForHumans() }}</div>
                        </a>
                    @empty
                        <div class="p-3 text-muted small">Nenhuma prova pendente.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
