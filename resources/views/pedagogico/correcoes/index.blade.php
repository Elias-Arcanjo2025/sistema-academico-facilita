<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between">
            <h2 class="h4 mb-0">Correcções</h2>
            <a href="{{ route('pedagogico.correcoes.create') }}" class="btn btn-primary btn-sm">Nova</a>
        </div>
    </x-slot>
    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-check2-square" variant="info" title="Correcções" id="tip-correcoes-index" compact>
                Corrija provas com apoio de OCR/IA: a sugestão de nota deve ser revista e confirmada pelo professor antes de ficar definitiva.
            </x-tip-card>
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
            <div class="card">
                <table class="table mb-0">
                    <thead><tr><th>Prova</th><th>Aluno</th><th>Sugestão</th><th>Confirmada</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        @forelse($correcoes as $c)
                        <tr>
                            <td>{{ $c->prova->tipo ?? '' }} — {{ $c->prova->disciplina->nome ?? '' }}</td>
                            <td>{{ $c->aluno->nome ?? '-' }}</td>
                            <td>{{ $c->sugestao_nota ?? '-' }}</td>
                            <td>{{ $c->nota_confirmada ?? '-' }}</td>
                            <td>{{ $c->estado }}</td>
                            <td><a href="{{ route('pedagogico.correcoes.show', $c) }}" class="btn btn-sm btn-outline-primary">Ver</a></td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted">Sem correcções.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if($correcoes->hasPages())<div class="card-footer">{{ $correcoes->links() }}</div>@endif
            </div>
        </div>
    </div>
</x-app-layout>
