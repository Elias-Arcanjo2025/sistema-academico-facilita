<x-app-layout>
    <x-slot name="header"><h2 class="h4 mb-0">Gerar Exercício</h2></x-slot>
    <div class="py-4">
        <div class="container col-lg-6">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('pedagogico.exercicios.gerar') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Planificação</label>
                            <select name="planificacao_id" class="form-select" required>
                                @foreach($planificacoes as $p)
                                    <option value="{{ $p->id }}" @selected($planificacaoId == $p->id)>
                                        {{ $p->turma->nome ?? '' }} — {{ $p->disciplina->nome ?? '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select" required>
                                <option value="ficha_trabalho" @selected(old('tipo') === 'ficha_trabalho')>Ficha de trabalho</option>
                                <option value="exercicio" @selected(old('tipo', 'exercicio') === 'exercicio')>Exercício</option>
                                <option value="material_apoio" @selected(old('tipo') === 'material_apoio')>Material de apoio</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Dificuldade</label>
                            <select name="dificuldade" class="form-select">
                                <option value="facil">Fácil</option>
                                <option value="medio" selected>Médio</option>
                                <option value="dificil">Difícil</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Enfileirar Geração</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
