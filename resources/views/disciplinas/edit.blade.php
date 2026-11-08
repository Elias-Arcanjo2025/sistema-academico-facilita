<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-bookmark-check me-2 text-primary"></i>Editar Disciplina
            </h2>
            <a href="{{ route('disciplinas.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow-soft fade-in">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Editar: {{ $disciplina->nome }}</h5>
                        </div>
                        <div class="card-body">
                            <x-tip-card icon="bi-pencil" variant="info" title="Editar Disciplina" id="tip-disciplinas-edit" compact>
                                Actualize os dados da disciplina. Alterações aqui reflectem-se em todas as turmas onde a disciplina está associada.
                            </x-tip-card>
                            <form action="{{ route('disciplinas.update', $disciplina) }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="codigo" class="form-label">
                                            <i class="bi bi-hash me-1"></i>Código <span class="text-danger">*</span>
                                            <x-help-icon content="Código único da disciplina (ex: MAT101, PORT)" />
                                        </label>
                                        <input type="text" class="form-control @error('codigo') is-invalid @enderror"
                                               id="codigo" name="codigo" value="{{ old('codigo', $disciplina->codigo) }}"
                                               maxlength="20" required>
                                        @error('codigo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-8 mb-3">
                                        <label for="nome" class="form-label">
                                            <i class="bi bi-type me-1"></i>Nome <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control @error('nome') is-invalid @enderror"
                                               id="nome" name="nome" value="{{ old('nome', $disciplina->nome) }}"
                                               maxlength="255" required>
                                        @error('nome')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="tipo" class="form-label">
                                            <i class="bi bi-tags me-1"></i>Tipo de Disciplina <span class="text-danger">*</span>
                                            <x-help-icon
                                                type="popover"
                                                title="Tipo de Disciplina"
                                                content="Define a categoria da disciplina para ordenação nos boletins e pautas: Geral → Científica → Técnica → Projeto/Estágio"
                                            />
                                        </label>
                                        <select class="form-select @error('tipo') is-invalid @enderror" id="tipo" name="tipo" required>
                                            <option value="geral" {{ old('tipo', $disciplina->tipo) == 'geral' ? 'selected' : '' }}>Geral (Português, Matemática, etc.)</option>
                                            <option value="cientifica" {{ old('tipo', $disciplina->tipo) == 'cientifica' ? 'selected' : '' }}>Científica (Física, Química, Biologia)</option>
                                            <option value="tecnica" {{ old('tipo', $disciplina->tipo) == 'tecnica' ? 'selected' : '' }}>Técnica (Específica do curso)</option>
                                            <option value="projeto_estagio" {{ old('tipo', $disciplina->tipo) == 'projeto_estagio' ? 'selected' : '' }}>Projeto/Estágio (PAP, Estágios)</option>
                                        </select>
                                        <div class="form-text">
                                            <i class="bi bi-info-circle me-1"></i>
                                            <strong>Geral:</strong> Línguas, Matemática, História, Geografia, Ed. Física |
                                            <strong>Científica:</strong> Física, Química, Biologia |
                                            <strong>Técnica:</strong> Programação, Contabilidade, Eletricidade |
                                            <strong>Projeto:</strong> PAP, Estágios
                                        </div>
                                        @error('tipo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="nivel_dificuldade" class="form-label">
                                            <i class="bi bi-bar-chart-steps me-1"></i>Nível de Dificuldade <span class="text-danger">*</span>
                                            <x-help-icon
                                                type="popover"
                                                title="Dificuldade da prova"
                                                content="Usado na geração automática do calendário de provas: disciplinas difíceis não partilham o dia com outra difícil e, quando emparelhadas, só com disciplinas fáceis."
                                            />
                                        </label>
                                        <select class="form-select @error('nivel_dificuldade') is-invalid @enderror" id="nivel_dificuldade" name="nivel_dificuldade" required>
                                            @foreach(\App\Models\Disciplina::NIVEIS_DIFICULDADE as $valor => $rotulo)
                                                <option value="{{ $valor }}" @selected(old('nivel_dificuldade', $disciplina->nivel_dificuldade ?? 'medio') === $valor)>
                                                    {{ $rotulo }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('nivel_dificuldade')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="ordem_apresentacao" class="form-label">
                                            <i class="bi bi-sort-numeric-down me-1"></i>Ordem de Apresentação <span class="text-danger">*</span>
                                            <x-help-icon
                                                type="popover"
                                                title="Ordem de Apresentação"
                                                content="Número que define a ordem dentro do tipo (1, 2, 3...). Quanto menor o número, mais no início aparece."
                                            />
                                        </label>
                                        <input type="number" class="form-control @error('ordem_apresentacao') is-invalid @enderror"
                                               id="ordem_apresentacao" name="ordem_apresentacao" value="{{ old('ordem_apresentacao', $disciplina->ordem_apresentacao) }}"
                                               min="1" max="999" required>
                                        <div class="form-text">
                                            <i class="bi bi-info-circle me-1"></i>Ex: 1 para primeira, 2 para segunda, etc.
                                        </div>
                                        @error('ordem_apresentacao')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="cursos" class="form-label">
                                            <i class="bi bi-journals me-1"></i>Cursos <span class="text-danger">*</span>
                                            <x-help-icon
                                                type="popover"
                                                title="Cursos Associados"
                                                content="Selecione um ou mais cursos onde esta disciplina será leccionada. Campo obrigatório - pode seleccionar múltiplos cursos."
                                            />
                                        </label>
                                        @php
                                            $cursosSeleccionados = old('cursos', $disciplina->cursos->pluck('id')->toArray());
                                        @endphp
                                        <select class="form-select select2-multiple @error('cursos') is-invalid @enderror @error('cursos.*') is-invalid @enderror"
                                                id="cursos" name="cursos[]" multiple required>
                                            @foreach($cursos as $curso)
                                                <option value="{{ $curso->id }}" {{ in_array($curso->id, $cursosSeleccionados) ? 'selected' : '' }}>
                                                    {{ $curso->nome }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-text">
                                            <i class="bi bi-info-circle me-1"></i>Cursos onde a disciplina e leccionada
                                        </div>
                                        @error('cursos')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        @error('cursos.*')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="classes" class="form-label">
                                            <i class="bi bi-mortarboard me-1"></i>Classes
                                            <x-help-icon
                                                type="popover"
                                                title="Classes (Opcional)"
                                                content="Selecione classes específicas ou deixe vazio para aplicar a todas as classes. Útil quando a disciplina não é leccionada em todos os níveis."
                                            />
                                        </label>
                                        @php
                                            $classesSeleccionadas = old('classes', $disciplina->classes->pluck('id')->toArray());
                                        @endphp
                                        <select class="form-select select2-classes @error('classes') is-invalid @enderror @error('classes.*') is-invalid @enderror"
                                                id="classes" name="classes[]" multiple>
                                            @foreach($classes as $classe)
                                                <option value="{{ $classe->id }}" {{ in_array($classe->id, $classesSeleccionadas) ? 'selected' : '' }}>
                                                    {{ $classe->nome }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-text">
                                            <i class="bi bi-lightbulb me-1"></i>Deixe vazio para todas as classes
                                        </div>
                                        @error('classes')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        @error('classes.*')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="descricao" class="form-label">
                                        <i class="bi bi-text-paragraph me-1"></i>Descrição
                                        <x-help-icon
                                            type="popover"
                                            title="Sobre a Descrição"
                                            content="A descrição é opcional e pode conter informações adicionais sobre a disciplina, como objectivos, conteúdo programático resumido ou observações relevantes."
                                        />
                                    </label>
                                    <textarea class="form-control @error('descricao') is-invalid @enderror"
                                              id="descricao" name="descricao" rows="3"
                                              placeholder="Descrição opcional da disciplina...">{{ old('descricao', $disciplina->descricao) }}</textarea>
                                    @error('descricao')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="activo" value="0">
                                        <input type="checkbox" class="form-check-input" id="activo" name="activo" value="1"
                                               {{ old('activo', $disciplina->activo) ? 'checked' : '' }} style="width: 3em; height: 1.5em;">
                                        <label class="form-check-label ms-2" for="activo">
                                            <strong>Disciplina Ativa</strong>
                                            <br><small class="text-muted">Disciplinas inativas não aparecem nas listagens</small>
                                        </label>
                                    </div>
                                </div>

                                <hr class="my-3">
                                <h6 class="text-primary mb-3">
                                    <i class="bi bi-flag-fill me-1"></i>Disciplina Terminal
                                </h6>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <strong>A "terminalidade" passou a ser definida POR CURSO.</strong>
                                    Esta opção permite que a mesma disciplina seja terminal em classes diferentes consoante o curso (ex.: TIC pode terminar na 10ª num curso e na 11ª noutro).
                                    <br><br>
                                    <strong>Como configurar:</strong> aceda à página de
                                    <a href="{{ route('cursos.index') }}">Cursos</a> →
                                    edite o curso pretendido → secção <em>"Disciplinas do Curso"</em> →
                                    defina <code>Classe de fim</code> para esta disciplina.
                                    Uma disciplina é terminal quando termina antes da última classe do curso.
                                </div>
                                {{-- Compatibilidade: manter os campos antigos como hidden para não perder dados em updates --}}
                                <input type="hidden" name="terminal" value="{{ (int) $disciplina->terminal }}">
                                <input type="hidden" name="classe_terminal_id" value="{{ $disciplina->classe_terminal_id }}">

                                <hr class="my-4">

                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <small class="text-muted">
                                            <i class="bi bi-clock me-1"></i>Criada em {{ $disciplina->created_at->format('d/m/Y H:i') }}
                                        </small>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="{{ route('disciplinas.index') }}" class="btn btn-outline-secondary">
                                            <i class="bi bi-x-lg me-1"></i>Cancelar
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-lg me-1"></i>Actualizar Disciplina
                                        </button>
                                    </div>
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
            $('.select2-multiple').select2({
                theme: 'bootstrap-5',
                placeholder: 'Selecione os cursos...',
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() {
                        return "Nenhum curso encontrado";
                    },
                    searching: function() {
                        return "A procurar...";
                    }
                }
            });

            $('.select2-classes').select2({
                theme: 'bootstrap-5',
                placeholder: 'Selecione as classes...',
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() {
                        return "Nenhuma classe encontrada";
                    },
                    searching: function() {
                        return "A procurar...";
                    }
                }
            });

        });
    </script>
    @endpush
</x-app-layout>
