<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Editar Director de Turma</h2>
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
                            <x-tip-card icon="bi-pencil" variant="info" title="Editar Director de Turma" id="tip-directores-edit" compact>
                                Actualize a atribuição de director de turma. O professor anterior perderá o acesso privilegiado à turma.
                            </x-tip-card>
                            <form action="{{ route('directores-turma.update', $director) }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="mb-3">
                                    <label for="user_id" class="form-label">
                                        <i class="bi bi-person-badge me-1"></i>Professor <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select @error('user_id') is-invalid @enderror" id="user_id" name="user_id" required>
                                        <option value="">Selecione um professor</option>
                                        @foreach($professores as $professor)
                                            <option value="{{ $professor->id }}" {{ old('user_id', $director->user_id) == $professor->id ? 'selected' : '' }}>
                                                {{ $professor->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('user_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="turma_id" class="form-label">
                                        <i class="bi bi-people me-1"></i>Turma <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select @error('turma_id') is-invalid @enderror" id="turma_id" name="turma_id" required>
                                        <option value="">Selecione uma turma</option>
                                        @foreach($turmas as $turma)
                                            <option value="{{ $turma->id }}" {{ old('turma_id', $director->turma_id) == $turma->id ? 'selected' : '' }}>
                                                {{ $turma->nome }} - {{ $turma->curso->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('turma_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="ano_lectivo" class="form-label">
                                        <i class="bi bi-calendar-event me-1"></i>Ano Lectivo <span class="text-danger">*</span>
                                        <x-help-icon
                                            type="popover"
                                            title="Ano Lectivo Fixo"
                                            content="O ano lectivo não pode ser alterado após a criação. Para mudar o ano lectivo, crie uma nova atribuição."
                                        />
                                    </label>
                                    <input type="text" class="form-control @error('ano_lectivo') is-invalid @enderror" id="ano_lectivo" name="ano_lectivo" value="{{ old('ano_lectivo', $director->ano_lectivo) }}" readonly required>
                                    @error('ano_lectivo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="hidden" name="activo" value="0">
                                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" {{ old('activo', $director->activo) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="activo">
                                            Ativo
                                        </label>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('directores-turma.index') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-lg me-1"></i>Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i>Actualizar
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
