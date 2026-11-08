<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Novo Director de Turma</h2>
            <a href="{{ route('directores-turma.index') }}" class="btn btn-outline-secondary">
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

                    <div class="card">
                        <div class="card-body">
                            <x-tip-card icon="bi-info-circle" variant="info" title="Atribuir Director de Turma" id="tip-directores-create" compact>
                                Seleccione o professor e a turma. O director de turma terá acesso aos conselhos de classe e boletins de notas da turma atribuída.
                            </x-tip-card>
                            <form action="{{ route('directores-turma.store') }}" method="POST">
                                @csrf

                                <div class="mb-3">
                                    <label for="user_id" class="form-label">
                                        <i class="bi bi-person-badge me-1"></i>Professor <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select @error('user_id') is-invalid @enderror" id="user_id" name="user_id" required>
                                        <option value="">Selecione um professor</option>
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
                                    <label for="turma_ids" class="form-label">
                                        <i class="bi bi-people me-1"></i>Turmas <span class="text-danger">*</span>
                                        <x-help-icon
                                            type="popover"
                                            title="Selecção Múltipla"
                                            content="Mantenha pressionado Ctrl (Windows) ou Cmd (Mac) para seleccionar múltiplas turmas. Será criada uma atribuição para cada turma seleccionada."
                                        />
                                    </label>
                                    <select class="form-select @error('turma_ids') is-invalid @enderror @error('turma_ids.*') is-invalid @enderror"
                                            id="turma_ids" name="turma_ids[]" multiple required size="10">
                                        @foreach($turmas as $turma)
                                            <option value="{{ $turma->id }}" {{ (is_array(old('turma_ids')) && in_array($turma->id, old('turma_ids'))) ? 'selected' : '' }}>
                                                {{ $turma->nome }} - {{ $turma->curso->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('turma_ids')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    @error('turma_ids.*')
                                        <div class="invalid-feedback">{{ $message }}</div>
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
                                        <label class="form-check-label" for="activo">
                                            Activo
                                        </label>
                                    </div>
                                </div>

                                <x-info-card type="info" title="Importante">
                                    Cada turma pode ter apenas um director de turma activo por ano lectivo. Se seleccionar múltiplas turmas, será criada uma atribuição para cada turma.
                                </x-info-card>

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a href="{{ route('directores-turma.index') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-lg me-1"></i>Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i>Atribuir Director
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
