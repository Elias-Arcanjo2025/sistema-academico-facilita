<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Nova ocorrência</h2>
            <a href="{{ route('ocorrencias.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container col-lg-7">
            <x-breadcrumb :items="[
                ['label' => 'Assiduidade', 'url' => route('presencas.index')],
                ['label' => 'Ocorrências', 'url' => route('ocorrencias.index')],
                ['label' => 'Nova', 'url' => route('ocorrencias.create')],
            ]" />

            <x-tip-card icon="bi-exclamation-triangle" variant="info" title="Registar ocorrência" id="tip-ocorrencias-create" compact>
                Escolha a turma para carregar os alunos, depois indique tipo, severidade e descrição. Ocorrências graves aparecem no Painel do Inspetor.
            </x-tip-card>

            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('ocorrencias.create') }}" class="mb-3">
                        <div class="mb-3">
                            <x-ano-lectivo-selector
                                :anoLectivoId="$anoLectivoId"
                                :anosLectivos="$anosLectivos"
                                size="sm"
                                label="Ano Lectivo"
                                class="mb-0"
                            />
                        </div>
                        <label class="form-label">Turma</label>
                        <select name="turma_id" class="form-select" onchange="this.form.submit()">
                            <option value="">Seleccione a turma…</option>
                            @foreach($turmas as $turma)
                                <option value="{{ $turma->id }}" @selected((int) $turmaId === (int) $turma->id)>{{ $turma->nome }}</option>
                            @endforeach
                        </select>
                    </form>

                    @if(! $turmaId)
                        <div class="alert alert-info mb-0">Escolha primeiro a turma para carregar os alunos.</div>
                    @elseif($alunos->isEmpty())
                        <div class="alert alert-warning mb-0">Sem alunos activos nesta turma (ou sem permissão).</div>
                    @else
                        <form method="POST" action="{{ route('ocorrencias.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Aluno</label>
                                <select name="aluno_id" class="form-select" required>
                                    @foreach($alunos as $aluno)
                                        <option value="{{ $aluno->id }}" @selected(old('aluno_id') == $aluno->id)>
                                            {{ $aluno->numero_ordem }}. {{ $aluno->nome }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Data</label>
                                    <input type="date" name="data" class="form-control" value="{{ old('data', now()->toDateString()) }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tipo</label>
                                    <select name="tipo" class="form-select" required>
                                        @foreach($tipos as $valor => $label)
                                            <option value="{{ $valor }}" @selected(old('tipo') === $valor)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Severidade</label>
                                    <select name="severidade" class="form-select" required>
                                        @foreach($severidades as $valor => $label)
                                            <option value="{{ $valor }}" @selected(old('severidade', 'media') === $valor)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3 mt-3">
                                <label class="form-label">Descrição</label>
                                <textarea name="descricao" class="form-control" rows="4" required maxlength="2000">{{ old('descricao') }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Medida disciplinar</label>
                                <select name="medida_sugerida" class="form-select mb-2" id="medida_sugerida">
                                    <option value="">— Sem medida pré-definida —</option>
                                    @foreach(\App\Models\Ocorrencia::medidasSugeridas() as $valor => $label)
                                        <option value="{{ $label }}" @selected(old('medida_sugerida') === $label)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <textarea name="medida" class="form-control" rows="2" maxlength="2000"
                                          placeholder="Detalhe da medida / advertência (opcional)">{{ old('medida') }}</textarea>
                                <small class="text-muted">Para advertências, escolha o tipo “Advertência” e indique a medida.</small>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Guardar
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('medida_sugerida')?.addEventListener('change', function () {
            const medida = document.querySelector('textarea[name="medida"]');
            if (this.value && medida && !medida.value) {
                medida.value = this.value;
            }
        });
    </script>
</x-app-layout>
