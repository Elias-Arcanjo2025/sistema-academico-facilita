<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">Nova orientação metodológica</h2>
    </x-slot>

    <div class="py-4">
        <div class="container" style="max-width: 720px;">
            <div class="card shadow-soft">
                <div class="card-body">
                    <form method="POST" action="{{ route('pedagogico.orientacoes.store') }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Disciplina</label>
                                <select name="disciplina_id" class="form-select" required>
                                    <option value="">Seleccione</option>
                                    @foreach($disciplinas as $d)
                                        <option value="{{ $d->id }}" @selected(old('disciplina_id') == $d->id)>{{ $d->nome }}</option>
                                    @endforeach
                                </select>
                                @error('disciplina_id')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Classe</label>
                                <select name="classe_id" class="form-select" required>
                                    <option value="">Seleccione</option>
                                    @foreach($classes as $c)
                                        <option value="{{ $c->id }}" @selected(old('classe_id') == $c->id)>{{ $c->nome }}</option>
                                    @endforeach
                                </select>
                                @error('classe_id')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ano lectivo</label>
                                <select name="ano_lectivo_id" class="form-select">
                                    <option value="">—</option>
                                    @foreach($anosLectivos as $id => $nome)
                                        <option value="{{ $id }}" @selected(old('ano_lectivo_id', $anoActualId) == $id)>{{ $nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Trimestre (opcional)</label>
                                <select name="trimestre" class="form-select">
                                    <option value="">—</option>
                                    @for($t = 1; $t <= 3; $t++)
                                        <option value="{{ $t }}" @selected(old('trimestre') == $t)>{{ $t }}º</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Título</label>
                                <input type="text" name="titulo" class="form-control" value="{{ old('titulo') }}" maxlength="200" required>
                                @error('titulo')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">Conteúdo</label>
                                <textarea name="conteudo" class="form-control" rows="10" required>{{ old('conteudo') }}</textarea>
                                @error('conteudo')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="mt-3 d-flex gap-2">
                            <button class="btn btn-primary">Guardar rascunho</button>
                            <a href="{{ route('pedagogico.orientacoes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
