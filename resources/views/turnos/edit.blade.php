<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Editar Turno</h2>
            <a href="{{ route('turnos.index') }}" class="btn btn-outline-secondary">
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
                            <x-tip-card icon="bi-pencil" variant="info" title="Editar Turno" id="tip-turnos-edit" compact>
                                Actualize os dados do turno. As turmas associadas a este turno manterão a associação após a edição.
                            </x-tip-card>
                            <form action="{{ route('turnos.update', $turno) }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="codigo" class="form-label">
                                            <i class="bi bi-hash me-1"></i>Código <span class="text-danger">*</span>
                                            <x-help-icon content="Código único do turno (ex: M, T, N)" />
                                        </label>
                                        <input type="text" class="form-control @error('codigo') is-invalid @enderror" id="codigo" name="codigo" value="{{ old('codigo', $turno->codigo) }}" required maxlength="20">
                                        @error('codigo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-8 mb-3">
                                        <label for="nome" class="form-label">
                                            <i class="bi bi-type me-1"></i>Nome <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control @error('nome') is-invalid @enderror" id="nome" name="nome" value="{{ old('nome', $turno->nome) }}" required maxlength="255">
                                        @error('nome')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="hora_inicio" class="form-label">
                                            <i class="bi bi-clock me-1"></i>Hora de Início
                                            <x-help-icon
                                                type="popover"
                                                title="Horário do Turno"
                                                content="Define o horário de funcionamento do turno. Estes campos são opcionais mas úteis para organização e relatórios."
                                            />
                                        </label>
                                        <input type="time" class="form-control @error('hora_inicio') is-invalid @enderror" id="hora_inicio" name="hora_inicio" value="{{ old('hora_inicio', $turno->hora_inicio ? \Carbon\Carbon::parse($turno->hora_inicio)->format('H:i') : '') }}">
                                        @error('hora_inicio')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="hora_fim" class="form-label">
                                            <i class="bi bi-clock-fill me-1"></i>Hora de Fim
                                        </label>
                                        <input type="time" class="form-control @error('hora_fim') is-invalid @enderror" id="hora_fim" name="hora_fim" value="{{ old('hora_fim', $turno->hora_fim ? \Carbon\Carbon::parse($turno->hora_fim)->format('H:i') : '') }}">
                                        @error('hora_fim')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="hidden" name="activo" value="0">
                                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" {{ old('activo', $turno->activo) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="activo">
                                            Turno activo
                                        </label>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('turnos.index') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-lg me-1"></i>Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i>Actualizar Turno
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
