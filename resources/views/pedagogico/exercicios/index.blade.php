<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between">
            <h2 class="h4 mb-0">Exercícios</h2>
            @can('pedagogico.ia.gerar')
                <a href="{{ route('pedagogico.exercicios.create') }}" class="btn btn-primary btn-sm">Gerar Novo</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-journal-text" variant="info" title="Exercícios" id="tip-exercicios-index" compact>
                Gera exercícios de treino com IA a partir da planificação (tema e dificuldade). São distintos das provas oficiais e do banco de questões.
            </x-tip-card>
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

            <form method="GET" class="card mb-4">
                <div class="card-body row g-2">
                    <div class="col-md-6">
                        <select name="planificacao_id" class="form-select" onchange="this.form.submit()">
                            <option value="">Seleccione planificação...</option>
                            @foreach($planificacoes as $p)
                                <option value="{{ $p->id }}" @selected(request('planificacao_id') == $p->id)>
                                    {{ $p->turma->nome ?? '' }} — {{ $p->disciplina->nome ?? '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>

            @if($planificacao)
            <div class="card">
                <table class="table mb-0">
                    <thead><tr><th>Tipo</th><th>Dificuldade</th><th>Quinzena</th><th></th></tr></thead>
                    <tbody>
                        @forelse($exercicios as $ex)
                        <tr>
                            <td>{{ $ex->tipo }}</td>
                            <td>{{ $ex->dificuldade }}</td>
                            <td>{{ $ex->quinzena->tema ?? '-' }}</td>
                            <td class="text-end">
                                <a href="{{ route('pedagogico.exercicios.show', $ex) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                                <a href="{{ route('pedagogico.exercicios.pdf', $ex) }}" class="btn btn-sm btn-outline-secondary">PDF</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted">Sem exercícios.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
