<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Editar Associacao</h2>
            <a href="{{ route('associacoes.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body">
                            <x-tip-card icon="bi-pencil" variant="info" title="Editar Associação" id="tip-associacoes-edit" compact>
                                Actualize turma, disciplina ou estado activo/inactivo. Para substituir o professor, desactive esta associação e crie uma nova para o novo docente.
                            </x-tip-card>

                            @if($notasCount > 0)
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    Esta associação tem <strong>{{ $notasCount }} nota(s)</strong> lançada(s).
                                    O professor <strong>não pode ser alterado</strong> — as notas permanecem ligadas a quem as lançou.
                                    Para trocar de docente: desactive esta associação e use <strong>Nova Associação</strong> para o novo professor.
                                </div>
                            @endif

                            <form action="{{ route('associacoes.update', $associacao) }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="mb-3">
                                    <label for="user_id" class="form-label">
                                        <i class="bi bi-person-badge me-1"></i>Professor <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select @error('user_id') is-invalid @enderror" id="user_id" name="user_id" required
                                        @if($notasCount > 0) disabled @endif>
                                        <option value="">Selecione um professor</option>
                                        @foreach($professores as $professor)
                                            <option value="{{ $professor->id }}" {{ old('user_id', $associacao->user_id) == $professor->id ? 'selected' : '' }}>
                                                {{ $professor->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if($notasCount > 0)
                                        <input type="hidden" name="user_id" value="{{ $associacao->user_id }}">
                                    @endif
                                    @error('user_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="turma_id" class="form-label">
                                        <i class="bi bi-people me-1"></i>Turma <span class="text-danger">*</span>
                                        <x-help-icon content="Alterar a turma carregará novamente as disciplinas disponíveis" />
                                    </label>
                                    <select class="form-select @error('turma_id') is-invalid @enderror" id="turma_id" name="turma_id" required>
                                        <option value="">Selecione uma turma</option>
                                        @foreach($turmas as $turma)
                                            <option value="{{ $turma->id }}" {{ old('turma_id', $associacao->turma_id) == $turma->id ? 'selected' : '' }}>
                                                {{ $turma->nome }} - {{ $turma->curso->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('turma_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="disciplina_id" class="form-label">
                                        <i class="bi bi-bookmark me-1"></i>Disciplina <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select @error('disciplina_id') is-invalid @enderror" id="disciplina_id" name="disciplina_id" required>
                                        <option value="">Selecione uma disciplina</option>
                                        @foreach($disciplinas as $disciplina)
                                            <option value="{{ $disciplina->id }}" {{ old('disciplina_id', $associacao->disciplina_id) == $disciplina->id ? 'selected' : '' }}>
                                                {{ $disciplina->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('disciplina_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="ano_lectivo_id" class="form-label">
                                        <i class="bi bi-calendar-event me-1"></i>Ano Lectivo <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select @error('ano_lectivo_id') is-invalid @enderror" id="ano_lectivo_id" name="ano_lectivo_id" required>
                                        @foreach($anosLectivos as $id => $nome)
                                            <option value="{{ $id }}" {{ old('ano_lectivo_id', $associacao->ano_lectivo_id) == $id ? 'selected' : '' }}>
                                                {{ $nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('ano_lectivo_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="hidden" name="activo" value="0">
                                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" {{ old('activo', $associacao->activo) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="activo">
                                            Associacao activa
                                        </label>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('associacoes.index') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-lg me-1"></i>Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i>Actualizar Associação
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('turma_id').addEventListener('change', function() {
            const turmaId = this.value;
            const disciplinaSelect = document.getElementById('disciplina_id');

            disciplinaSelect.innerHTML = '<option value="">A carregar...</option>';

            if (!turmaId) {
                disciplinaSelect.innerHTML = '<option value="">Selecione uma disciplina</option>';
                return;
            }

            fetch(`{{ route('associacoes.disciplinas') }}?turma_id=${turmaId}`)
                .then(response => response.json())
                .then(data => {
                    disciplinaSelect.innerHTML = '<option value="">Selecione uma disciplina</option>';
                    data.forEach(disciplina => {
                        disciplinaSelect.innerHTML += `<option value="${disciplina.id}">${disciplina.nome}</option>`;
                    });
                })
                .catch(error => {
                    console.error('Erro:', error);
                    disciplinaSelect.innerHTML = '<option value="">Erro ao carregar disciplinas</option>';
                });
        });
    </script>
    @endpush
</x-app-layout>
