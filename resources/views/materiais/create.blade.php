<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Carregar material</h2>
            <a href="{{ route('materiais.index') }}" class="btn btn-outline-secondary btn-sm">Voltar</a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container col-lg-7">
            <x-tip-card icon="bi-upload" variant="info" title="Novo material" id="tip-materiais-create" compact>
                PDF, Office ou imagem até 10 MB. Associe a uma disciplina e/ou turma para limitar o acesso dos alunos.
            </x-tip-card>

            @if($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif

            <div class="card shadow-soft">
                <div class="card-body">
                    <form method="POST" action="{{ route('materiais.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Título *</label>
                            <input name="titulo" class="form-control" value="{{ old('titulo') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao" class="form-control" rows="2">{{ old('descricao') }}</textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Disciplina</label>
                                <select name="disciplina_id" class="form-select">
                                    <option value="">— Geral —</option>
                                    @foreach($disciplinas as $d)
                                        <option value="{{ $d->id }}" @selected(old('disciplina_id') == $d->id)>{{ $d->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Turma</label>
                                <select name="turma_id" class="form-select">
                                    <option value="">— Todas / geral —</option>
                                    @foreach($turmas as $t)
                                        <option value="{{ $t->id }}" @selected(old('turma_id') == $t->id)>{{ $t->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ano lectivo *</label>
                            <select name="ano_lectivo_id" class="form-select" required>
                                @foreach($anosLectivos as $id => $nome)
                                    <option value="{{ $id }}" @selected(old('ano_lectivo_id', $anoLectivoId) == $id)>{{ $nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ficheiro *</label>
                            <input type="file" name="ficheiro" class="form-control" required>
                        </div>
                        <div class="form-check mb-3">
                            <input type="hidden" name="publicado" value="0">
                            <input class="form-check-input" type="checkbox" name="publicado" value="1" id="publicado" @checked(old('publicado', true))>
                            <label class="form-check-label" for="publicado">Publicado (visível aos alunos)</label>
                        </div>
                        <button type="submit" class="btn btn-primary">Carregar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
