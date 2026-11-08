<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0"><i class="bi bi-people me-2"></i>Distribuir alunos</h2>
    </x-slot>

    <div class="py-4">
        <div class="container col-lg-8">
            <x-tip-card icon="bi-arrows-move" variant="info" title="Distribuição por turma" id="tip-distribuicao" compact>
                Seleccione a turma de origem, marque os alunos e indique a turma de destino. Entre cursos diferentes exige confirmação explícita.
            </x-tip-card>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="card shadow-soft mb-3">
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label">Turma de origem</label>
                            <select name="turma_origem_id" class="form-select" onchange="this.form.submit()">
                                <option value="">Seleccione</option>
                                @foreach($turmas as $t)
                                    <option value="{{ $t->id }}" @selected($turmaOrigemId == $t->id)>
                                        {{ $t->nome }} @if($t->curso)— {{ $t->curso->nome }}@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            @if($turmaOrigemId)
                <div class="card shadow-soft">
                    <div class="card-body">
                        <form method="POST" action="{{ route('secretaria.distribuicao.store') }}">
                            @csrf
                            <input type="hidden" name="turma_origem_id" value="{{ $turmaOrigemId }}">

                            <div class="mb-3">
                                <label class="form-label">Turma de destino *</label>
                                <select name="turma_destino_id" id="turma_destino_id" class="form-select" required
                                        data-curso-origem="{{ $turmas->firstWhere('id', $turmaOrigemId)?->curso_id }}">
                                    <option value="">Seleccione</option>
                                    @foreach($turmas->where('id', '!=', $turmaOrigemId) as $t)
                                        <option value="{{ $t->id }}" data-curso-id="{{ $t->curso_id }}">
                                            {{ $t->nome }} @if($t->curso)— {{ $t->curso->nome }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div id="alerta-curso" class="alert alert-warning d-none">
                                Destino noutro curso. As notas não serão movidas automaticamente.
                                @if(auth()->user()->podeTransferirEntreCursos())
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="confirmar_troca_curso" value="1" id="confirmar_troca_curso">
                                        <label class="form-check-label" for="confirmar_troca_curso">Confirmo a distribuição entre cursos</label>
                                    </div>
                                @endif
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Motivo *</label>
                                <textarea name="motivo" class="form-control" rows="2" required maxlength="500">{{ old('motivo') }}</textarea>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label mb-0">Alunos ({{ $alunos->count() }})</label>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="toggle-all">Marcar todos</button>
                                </div>
                                <div class="border rounded p-2" style="max-height: 320px; overflow-y: auto;">
                                    @forelse($alunos as $aluno)
                                        <div class="form-check">
                                            <input class="form-check-input aluno-check" type="checkbox" name="aluno_ids[]" value="{{ $aluno->id }}" id="a{{ $aluno->id }}">
                                            <label class="form-check-label" for="a{{ $aluno->id }}">
                                                {{ $aluno->numero_ordem ? $aluno->numero_ordem.'. ' : '' }}{{ $aluno->nome }}
                                                <small class="text-muted">({{ $aluno->numero_processo }})</small>
                                            </label>
                                        </div>
                                    @empty
                                        <p class="text-muted mb-0">Sem alunos activos nesta turma.</p>
                                    @endforelse
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" @disabled($alunos->isEmpty())>Distribuir seleccionados</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('turma_destino_id')?.addEventListener('change', function () {
            const origem = this.dataset.cursoOrigem;
            const destino = this.selectedOptions[0]?.dataset.cursoId;
            const alerta = document.getElementById('alerta-curso');
            if (alerta) alerta.classList.toggle('d-none', !destino || String(origem) === String(destino));
        });
        document.getElementById('toggle-all')?.addEventListener('click', function () {
            const boxes = document.querySelectorAll('.aluno-check');
            const all = [...boxes].every(b => b.checked);
            boxes.forEach(b => b.checked = !all);
        });
    </script>
    @endpush
</x-app-layout>
