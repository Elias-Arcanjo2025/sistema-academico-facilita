<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-link-45deg me-2 text-primary"></i>Nova Associação
            </h2>
            <a href="{{ route('associacoes.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <!-- Info Card -->
                    <x-info-card type="info" title="Associação Professor-Disciplina-Turma" class="mb-4">
                        Esta associação permite que um professor lecione uma disciplina numa turma específica durante um ano lectivo.
                    </x-info-card>

                    <div class="card shadow-soft fade-in">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i>Dados da Associação</h5>
                        </div>
                        <div class="card-body">
                            <x-tip-card icon="bi-info-circle" variant="info" title="Criar Associação" id="tip-associacoes-create" compact>
                                Associe um professor a uma turma e disciplina. Esta associação permite ao professor lançar notas para essa turma/disciplina. Certifique-se de que a disciplina pertence ao curso da turma.
                            </x-tip-card>
                            <form action="{{ route('associacoes.store') }}" method="POST">
                                @csrf

                                <div class="mb-3">
                                    <label for="user_id" class="form-label">
                                        <i class="bi bi-person-badge me-1"></i>Professor <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select select2-single @error('user_id') is-invalid @enderror" id="user_id" name="user_id" required>
                                        <option value="">Selecione um professor</option>
                                        @foreach($professores as $professor)
                                            <option value="{{ $professor->id }}" {{ old('user_id') == $professor->id ? 'selected' : '' }}>
                                                {{ $professor->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('user_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="turma_ids" class="form-label">
                                            <i class="bi bi-people me-1"></i>Turmas <span class="text-danger">*</span>
                                            <x-help-icon
                                                type="popover"
                                                title="Selecção de Turmas"
                                                content="Selecione uma ou mais turmas do mesmo curso. As turmas devem ser do mesmo curso para que seja possível associar à mesma disciplina."
                                            />
                                        </label>
                                        <select class="form-select select2-multiple @error('turma_ids') is-invalid @enderror" id="turma_ids" name="turma_ids[]" multiple required>
                                            @foreach($turmas as $turma)
                                                <option value="{{ $turma->id }}" data-curso="{{ $turma->curso_id }}" {{ in_array($turma->id, old('turma_ids', $turmaId ? [$turmaId] : [])) ? 'selected' : '' }}>
                                                    {{ $turma->nome }} - {{ $turma->curso->nome }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('turma_ids')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        @error('turma_ids.*')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="disciplina_id" class="form-label">
                                            <i class="bi bi-bookmark me-1"></i>Disciplina <span class="text-danger">*</span>
                                            <x-help-icon
                                                type="popover"
                                                title="Disciplina"
                                                content="A lista de disciplinas é carregada automaticamente com base no curso das turmas seleccionadas. Seleccione primeiro as turmas."
                                            />
                                        </label>
                                        <select class="form-select @error('disciplina_id') is-invalid @enderror" id="disciplina_id" name="disciplina_id" required>
                                            <option value="">Selecione uma disciplina</option>
                                            @foreach($disciplinas as $disciplina)
                                                <option value="{{ $disciplina->id }}" {{ old('disciplina_id') == $disciplina->id ? 'selected' : '' }}>
                                                    {{ $disciplina->nome }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('disciplina_id')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="ano_lectivo_id" class="form-label">
                                        <i class="bi bi-calendar-event me-1"></i>Ano Lectivo <span class="text-danger">*</span>
                                        <x-help-icon
                                            type="popover"
                                            title="Ano Lectivo"
                                            content="Seleccione o ano lectivo em que o professor leccionará esta disciplina. Por padrão, é seleccionado o ano lectivo activo."
                                        />
                                    </label>
                                    <select class="form-select @error('ano_lectivo_id') is-invalid @enderror" id="ano_lectivo_id" name="ano_lectivo_id" required>
                                        @foreach($anosLectivos as $id => $nome)
                                            <option value="{{ $id }}" {{ old('ano_lectivo_id', \App\Models\AnoLectivo::getActual()?->id) == $id ? 'selected' : '' }}>
                                                {{ $nome }}
                                                @if($nome == $anoLectivoActual)
                                                    (Actual)
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('ano_lectivo_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="activo" value="0">
                                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1"
                                               {{ old('activo', true) ? 'checked' : '' }} style="width: 3em; height: 1.5em;">
                                        <label class="form-check-label ms-2" for="activo">
                                            <strong>Associação Ativa</strong>
                                            <br><small class="text-muted">Associações inativas não permitem lançamento de notas</small>
                                        </label>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('associacoes.index') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-lg me-1"></i>Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i>Guardar Associação
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
        $(document).ready(function() {
            // Initialize Select2 for single selects
            $('.select2-single').select2({
                theme: 'bootstrap-5',
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() {
                        return "Nenhum resultado encontrado";
                    },
                    searching: function() {
                        return "A procurar...";
                    }
                }
            });

            // Initialize Select2 for multiple selects
            $('.select2-multiple').select2({
                theme: 'bootstrap-5',
                allowClear: true,
                width: '100%',
                placeholder: 'Selecione uma ou mais turmas',
                language: {
                    noResults: function() {
                        return "Nenhum resultado encontrado";
                    },
                    searching: function() {
                        return "A procurar...";
                    }
                }
            });

            // Handle turma change to load disciplines
            $('#turma_ids').on('change', function() {
                const turmaIds = $(this).val();
                const disciplinaSelect = document.getElementById('disciplina_id');

                if (!turmaIds || turmaIds.length === 0) {
                    disciplinaSelect.innerHTML = '<option value="">Selecione uma disciplina</option>';
                    return;
                }

                // Pegar o curso da primeira turma selecionada
                const firstTurmaId = turmaIds[0];
                const firstOption = $(this).find(`option[value="${firstTurmaId}"]`);
                const cursoId = firstOption.data('curso');

                // Verificar se todas as turmas são do mesmo curso
                let todosMesmoCurso = true;
                turmaIds.forEach(turmaId => {
                    const option = $(this).find(`option[value="${turmaId}"]`);
                    if (option.data('curso') != cursoId) {
                        todosMesmoCurso = false;
                    }
                });

                if (!todosMesmoCurso) {
                    disciplinaSelect.innerHTML = '<option value="">Selecione turmas do mesmo curso</option>';
                    showNotification('warning', 'Por favor, selecione turmas do mesmo curso para poder associar à mesma disciplina.', 'Atenção');
                    return;
                }

                disciplinaSelect.innerHTML = '<option value="">A carregar...</option>';

                fetch(`{{ route('associacoes.disciplinas') }}?turma_id=${firstTurmaId}`)
                    .then(response => response.json())
                    .then(data => {
                        disciplinaSelect.innerHTML = '<option value="">Selecione uma disciplina</option>';
                        if (data.length === 0) {
                            disciplinaSelect.innerHTML = '<option value="">Nenhuma disciplina disponível para este curso</option>';
                        } else {
                            data.forEach(disciplina => {
                                disciplinaSelect.innerHTML += `<option value="${disciplina.id}">${disciplina.codigo} - ${disciplina.nome}</option>`;
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        disciplinaSelect.innerHTML = '<option value="">Erro ao carregar disciplinas</option>';
                    });
            });
        });
    </script>
    @endpush
</x-app-layout>
