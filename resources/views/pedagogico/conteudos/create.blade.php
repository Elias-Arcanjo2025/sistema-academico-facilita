<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Novo conteúdo mínimo</h2>
            <a href="{{ route('pedagogico.conteudos.index') }}" class="btn btn-outline-secondary btn-sm">Voltar</a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container col-lg-7">
            <x-tip-card icon="bi-plus-square" variant="info" title="Criar programa" id="tip-conteudos-create" compact>
                Defina disciplina e classe. Depois adicione unidades e tópicos. Só a direcção pedagógica publica.
            </x-tip-card>

            @if($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif

            <div class="card shadow-soft">
                <div class="card-body">
                    <form method="POST" action="{{ route('pedagogico.conteudos.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Título *</label>
                            <input name="titulo" class="form-control" value="{{ old('titulo') }}" required maxlength="200">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Disciplina *</label>
                                <select name="disciplina_id" class="form-select" required>
                                    <option value="">—</option>
                                    @foreach($disciplinas as $d)
                                        <option value="{{ $d->id }}" @selected(old('disciplina_id') == $d->id)>{{ $d->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Classe *</label>
                                <select name="classe_id" class="form-select" required>
                                    <option value="">—</option>
                                    @foreach($classes as $c)
                                        <option value="{{ $c->id }}" @selected(old('classe_id') == $c->id)>{{ $c->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Curso (opcional)</label>
                                <select name="curso_id" class="form-select">
                                    <option value="">— Transversal —</option>
                                    @foreach($cursos as $c)
                                        <option value="{{ $c->id }}" @selected(old('curso_id') == $c->id)>{{ $c->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Ano lectivo</label>
                                <select name="ano_lectivo_id" class="form-select">
                                    <option value="">— Sem ano (vigente) —</option>
                                    @foreach($anosLectivos as $id => $nome)
                                        <option value="{{ $id }}" @selected(old('ano_lectivo_id') == $id)>{{ $nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Trimestre</label>
                                <select name="trimestre" class="form-select">
                                    <option value="">— Ano inteiro —</option>
                                    @for($t = 1; $t <= 3; $t++)
                                        <option value="{{ $t }}" @selected(old('trimestre') == $t)>{{ $t }}º</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Código oficial</label>
                            <input name="codigo_oficial" class="form-control" value="{{ old('codigo_oficial') }}" maxlength="64">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao" class="form-control" rows="3">{{ old('descricao') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Criar rascunho</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
