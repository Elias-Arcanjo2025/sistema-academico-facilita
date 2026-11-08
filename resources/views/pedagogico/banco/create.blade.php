<x-app-layout>
    <x-slot name="header">Nova questão</x-slot>

    <div class="mb-3">
        <a href="{{ route('pedagogico.banco.index') }}" class="btn btn-sm btn-outline-secondary">Voltar</a>
    </div>

    <x-tip-card icon="bi-collection" variant="info" title="Banco de questões" id="tip-banco-questoes-create" compact>
        Guarde aqui perguntas prontas a reutilizar. Depois, ao criar uma prova, pode importar questões deste banco em vez de as escrever de novo. Para escolha múltipla, preencha as opções A–D e indique a resposta correcta (ex.: A). Preferir geração automática?
        <a href="{{ route('pedagogico.banco.create-ia') }}">Gerar com IA</a>.
    </x-tip-card>

    <div class="content-card">
        <div class="p-3">
            <form method="POST" action="{{ route('pedagogico.banco.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Disciplina</label>
                        <select name="disciplina_id" class="form-select" required>
                            @foreach($disciplinas as $d)
                                <option value="{{ $d->id }}">{{ $d->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select" required>
                            @foreach($tipos as $v => $l)
                                <option value="{{ $v }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Dificuldade</label>
                        <select name="dificuldade" class="form-select" required>
                            @foreach($dificuldades as $v => $l)
                                <option value="{{ $v }}" @selected($v === 'media')>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-3 mt-3">
                    <label class="form-label">Enunciado</label>
                    <textarea name="enunciado" class="form-control" rows="3" required>{{ old('enunciado') }}</textarea>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-3"><input name="opcao_a" class="form-control" placeholder="Opção A"></div>
                    <div class="col-md-3"><input name="opcao_b" class="form-control" placeholder="Opção B"></div>
                    <div class="col-md-3"><input name="opcao_c" class="form-control" placeholder="Opção C"></div>
                    <div class="col-md-3"><input name="opcao_d" class="form-control" placeholder="Opção D"></div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Resposta / chave</label>
                        <input name="resposta" class="form-control" value="{{ old('resposta') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Cotação</label>
                        <input type="number" step="0.5" name="cotacao" class="form-control" value="{{ old('cotacao', 1) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tags</label>
                        <input name="tags" class="form-control" value="{{ old('tags') }}">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </form>
        </div>
    </div>
</x-app-layout>
