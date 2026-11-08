<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Editar programa</h2>
            <a href="{{ route('pedagogico.conteudos.show', $conteudo) }}" class="btn btn-outline-secondary btn-sm">Voltar</a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container col-lg-7">
            @if($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif

            <div class="card shadow-soft">
                <div class="card-body">
                    <p class="small text-muted">{{ $conteudo->disciplina?->nome }} · {{ $conteudo->classe?->nome }} · v{{ $conteudo->versao }}</p>
                    <form method="POST" action="{{ route('pedagogico.conteudos.update', $conteudo) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label">Título *</label>
                            <input name="titulo" class="form-control" value="{{ old('titulo', $conteudo->titulo) }}" required>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Curso</label>
                                <select name="curso_id" class="form-select">
                                    <option value="">— Transversal —</option>
                                    @foreach($cursos as $c)
                                        <option value="{{ $c->id }}" @selected(old('curso_id', $conteudo->curso_id) == $c->id)>{{ $c->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Ano lectivo</label>
                                <select name="ano_lectivo_id" class="form-select">
                                    <option value="">— Sem ano —</option>
                                    @foreach($anosLectivos as $id => $nome)
                                        <option value="{{ $id }}" @selected(old('ano_lectivo_id', $conteudo->ano_lectivo_id) == $id)>{{ $nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Trimestre</label>
                                <select name="trimestre" class="form-select">
                                    <option value="">— Ano inteiro —</option>
                                    @for($t = 1; $t <= 3; $t++)
                                        <option value="{{ $t }}" @selected(old('trimestre', $conteudo->trimestre) == $t)>{{ $t }}º</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Código oficial</label>
                            <input name="codigo_oficial" class="form-control" value="{{ old('codigo_oficial', $conteudo->codigo_oficial) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao" class="form-control" rows="3">{{ old('descricao', $conteudo->descricao) }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
