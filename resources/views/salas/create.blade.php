<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Nova Sala</h2>
            <a href="{{ route('salas.index') }}" class="btn btn-outline-secondary">
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
                            <x-tip-card icon="bi-info-circle" variant="info" title="Criar Sala" id="tip-salas-create" compact>
                                Defina o nome/número da sala e a sua capacidade máxima. Estas informações ajudam no planeamento da ocupação de salas.
                            </x-tip-card>
                            <form action="{{ route('salas.store') }}" method="POST">
                                @csrf

                                <div class="mb-3">
                                    <label for="codigo" class="form-label">
                                        <i class="bi bi-hash me-1"></i>Código <span class="text-danger">*</span>
                                        <x-help-icon content="Código único da sala (ex: S01, LAB-INFO)" />
                                    </label>
                                    <input type="text" class="form-control @error('codigo') is-invalid @enderror" id="codigo" name="codigo" value="{{ old('codigo') }}" required maxlength="20">
                                    @error('codigo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="nome" class="form-label">
                                        <i class="bi bi-type me-1"></i>Nome <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control @error('nome') is-invalid @enderror" id="nome" name="nome" value="{{ old('nome') }}" required maxlength="255">
                                    @error('nome')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="capacidade" class="form-label">
                                        <i class="bi bi-people me-1"></i>Capacidade
                                        <x-help-icon
                                            type="popover"
                                            title="Capacidade da Sala"
                                            content="Número máximo de alunos que a sala comporta. Este valor é opcional mas recomendado para melhor gestão de ocupação."
                                        />
                                    </label>
                                    <input type="number" class="form-control @error('capacidade') is-invalid @enderror" id="capacidade" name="capacidade" value="{{ old('capacidade') }}" min="1" placeholder="Ex: 30">
                                    @error('capacidade')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="hidden" name="activo" value="0">
                                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" {{ old('activo', true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="activo">
                                            Sala ativa
                                        </label>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('salas.index') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-lg me-1"></i>Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i>Guardar Sala
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
