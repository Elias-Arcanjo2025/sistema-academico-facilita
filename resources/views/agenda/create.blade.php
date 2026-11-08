<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Novo evento</h2>
            <a href="{{ route('agenda.index') }}" class="btn btn-outline-secondary btn-sm">Voltar</a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container col-lg-7">
            <x-tip-card icon="bi-calendar-plus" variant="info" title="Publicar evento" id="tip-agenda-create" compact>
                Escolha a visibilidade: escola, curso, turma ou disciplina (só professores dessa disciplina). Coordenadores de disciplina usam “Disciplina” para reuniões de área.
            </x-tip-card>

            @if($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif

            <div class="card shadow-soft">
                <div class="card-body">
                    <form method="POST" action="{{ route('agenda.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Título *</label>
                            <input name="titulo" class="form-control" value="{{ old('titulo') }}" required maxlength="200">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Tipo *</label>
                                <select name="tipo" class="form-select" required>
                                    @foreach($tipos as $v => $l)
                                        <option value="{{ $v }}" @selected(old('tipo', 'reuniao') === $v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Início *</label>
                                <input type="datetime-local" name="inicia_em" class="form-control" value="{{ old('inicia_em') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Fim</label>
                                <input type="datetime-local" name="termina_em" class="form-control" value="{{ old('termina_em') }}">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Visibilidade *</label>
                                <select name="visibilidade" id="visibilidade" class="form-select" required>
                                    @foreach($visibilidades as $v => $l)
                                        @if($soEscopo && $v === 'escola')
                                            @continue
                                        @endif
                                        @if($soEscopo && !($ehCoordDisciplina ?? false) && $v === 'disciplina')
                                            @continue
                                        @endif
                                        <option value="{{ $v }}" @selected(old('visibilidade', ($ehCoordDisciplina ?? false) ? 'disciplina' : ($soEscopo ? 'turma' : 'escola')) === $v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4" id="wrap-turma">
                                <label class="form-label">Turma</label>
                                <select name="turma_id" class="form-select">
                                    <option value="">—</option>
                                    @foreach($turmas as $t)
                                        <option value="{{ $t->id }}" @selected(old('turma_id') == $t->id)>{{ $t->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4" id="wrap-curso">
                                <label class="form-label">Curso</label>
                                <select name="curso_id" class="form-select">
                                    <option value="">—</option>
                                    @foreach($cursos as $c)
                                        <option value="{{ $c->id }}" @selected(old('curso_id') == $c->id)>{{ $c->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4" id="wrap-disciplina">
                                <label class="form-label">Disciplina</label>
                                <select name="disciplina_id" class="form-select">
                                    <option value="">—</option>
                                    @foreach($disciplinas as $d)
                                        <option value="{{ $d->id }}" @selected(old('disciplina_id') == $d->id)>{{ $d->nome }}</option>
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
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao" class="form-control" rows="3">{{ old('descricao') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Publicar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function syncVis() {
            const v = document.getElementById('visibilidade')?.value;
            document.getElementById('wrap-turma').style.display = v === 'turma' ? '' : 'none';
            document.getElementById('wrap-curso').style.display = v === 'curso' ? '' : 'none';
            document.getElementById('wrap-disciplina').style.display = v === 'disciplina' ? '' : 'none';
        }
        document.getElementById('visibilidade')?.addEventListener('change', syncVis);
        syncVis();
    </script>
    @endpush
</x-app-layout>
