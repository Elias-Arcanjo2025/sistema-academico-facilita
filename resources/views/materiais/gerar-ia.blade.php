<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Gerar material com IA</h2>
            <a href="{{ route('materiais.index') }}" class="btn btn-outline-secondary btn-sm">Voltar</a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container col-lg-7">
            <x-tip-card icon="bi-magic" variant="info" title="Ficha / material de apoio" id="tip-materiais-ia" compact>
                Gera uma ficha ou material de apoio e publica-o na disciplina que coordena. O ficheiro fica disponível em Materiais.
            </x-tip-card>

            @if($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif

            <div class="card shadow-soft">
                <div class="card-body">
                    <form method="POST" action="{{ route('materiais.gerar-ia.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Disciplina *</label>
                            <select name="disciplina_id" class="form-select" required>
                                <option value="">Seleccione</option>
                                @foreach($disciplinas as $d)
                                    <option value="{{ $d->id }}" @selected(old('disciplina_id') == $d->id)>{{ $d->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Tipo *</label>
                                <select name="tipo" class="form-select" required>
                                    <option value="ficha_apoio" @selected(old('tipo') === 'ficha_apoio')>Ficha de apoio</option>
                                    <option value="material_apoio" @selected(old('tipo') === 'material_apoio')>Material de apoio</option>
                                    <option value="resumo_materia" @selected(old('tipo') === 'resumo_materia')>Resumo de matéria</option>
                                    <option value="explicacao" @selected(old('tipo') === 'explicacao')>Explicação do tema</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Classe (texto)</label>
                                <input name="classe" class="form-control" value="{{ old('classe') }}" placeholder="ex.: 10ª Classe">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tema *</label>
                            <input name="tema" class="form-control" value="{{ old('tema') }}" required maxlength="300">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Título (opcional)</label>
                            <input name="titulo" class="form-control" value="{{ old('titulo') }}" maxlength="200">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ano lectivo *</label>
                            <select name="ano_lectivo_id" class="form-select" required>
                                @foreach($anosLectivos as $id => $nome)
                                    <option value="{{ $id }}" @selected(old('ano_lectivo_id', $anoLectivoId) == $id)>{{ $nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-magic me-1"></i> Gerar e publicar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
