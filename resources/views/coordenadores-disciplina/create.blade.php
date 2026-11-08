<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Novo Coordenador de Disciplina</h2>
            <a href="{{ route('coordenadores-disciplina.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <div class="card shadow-soft">
                        <div class="card-body">
                            <x-tip-card icon="bi-info-circle" variant="info" title="Atribuir Coordenador" id="tip-coord-disciplina-create" compact>
                                Seleccione o professor, a disciplina e as classes. O coordenador aprova planificações/provas dessas classes e gere o banco de questões da disciplina.
                            </x-tip-card>

                            <form action="{{ route('coordenadores-disciplina.store') }}" method="POST">
                                @csrf

                                <div class="mb-3">
                                    <label for="user_id" class="form-label">
                                        <i class="bi bi-person-badge me-1"></i>Professor <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select select2-single @error('user_id') is-invalid @enderror" id="user_id" name="user_id" required data-placeholder="Seleccione um professor">
                                        <option value=""></option>
                                        @foreach($professores as $professor)
                                            <option value="{{ $professor->id }}" {{ old('user_id') == $professor->id ? 'selected' : '' }}>
                                                {{ $professor->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('user_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="disciplina_id" class="form-label">
                                        <i class="bi bi-journal-text me-1"></i>Disciplina <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select select2-single @error('disciplina_id') is-invalid @enderror" id="disciplina_id" name="disciplina_id" required data-placeholder="Seleccione uma disciplina">
                                        <option value=""></option>
                                        @foreach($disciplinas as $disciplina)
                                            <option value="{{ $disciplina->id }}" {{ old('disciplina_id') == $disciplina->id ? 'selected' : '' }}>
                                                {{ $disciplina->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('disciplina_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="classe_ids" class="form-label">
                                        <i class="bi bi-layers me-1"></i>Classes <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select select2-multiple @error('classe_ids') is-invalid @enderror @error('classe_ids.*') is-invalid @enderror"
                                            id="classe_ids" name="classe_ids[]" multiple required
                                            data-placeholder="Seleccione uma ou mais classes">
                                        @foreach($classes as $classe)
                                            <option value="{{ $classe->id }}" {{ in_array($classe->id, old('classe_ids', [])) ? 'selected' : '' }}>
                                                {{ $classe->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('classe_ids')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    @error('classe_ids.*')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="ano_lectivo_id" class="form-label">
                                        <i class="bi bi-calendar-event me-1"></i>Ano Lectivo <span class="text-danger">*</span>
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
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="hidden" name="activo" value="0">
                                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" {{ old('activo', true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="activo">Activo</label>
                                    </div>
                                </div>

                                <x-info-card type="info" title="Nota">
                                    Uma disciplina pode ter vários coordenadores no mesmo ano. O mesmo professor não pode ser atribuído duas vezes à mesma disciplina (Seleccione todas as classes num único registo). Ex.: coordenar só a 10ª ou só a 11ª.
                                </x-info-card>

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a href="{{ route('coordenadores-disciplina.index') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-lg me-1"></i>Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i>Atribuir Coordenador
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
        $(document).ready(function () {
            $('.select2-single').each(function () {
                var $el = $(this);
                $el.select2({
                    theme: 'bootstrap-5',
                    allowClear: true,
                    width: '100%',
                    placeholder: $el.data('placeholder') || 'Seleccione...',
                    language: {
                        noResults: function () { return 'Nenhum resultado encontrado'; },
                        searching: function () { return 'A procurar...'; }
                    }
                });
            });

            $('.select2-multiple').each(function () {
                var $el = $(this);
                $el.select2({
                    theme: 'bootstrap-5',
                    allowClear: true,
                    width: '100%',
                    placeholder: $el.data('placeholder') || 'Seleccione...',
                    language: {
                        noResults: function () { return 'Nenhum resultado encontrado'; },
                        searching: function () { return 'A procurar...'; }
                    }
                });
            });
        });
    </script>
    @endpush
</x-app-layout>
