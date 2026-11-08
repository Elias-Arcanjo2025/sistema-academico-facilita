<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Novo Coordenador de Curso</h2>
            <a href="{{ route('coordenadores-curso.index') }}" class="btn btn-outline-secondary">
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
                            <x-tip-card icon="bi-info-circle" variant="info" title="Atribuir Coordenador" id="tip-coordenadores-create" compact>
                                Seleccione o professor e o curso. O coordenador terá acesso a relatórios e pautas de todas as turmas do curso atribuído.
                            </x-tip-card>
                            <form action="{{ route('coordenadores-curso.store') }}" method="POST">
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
                                    <label for="curso_id" class="form-label">
                                        <i class="bi bi-journal me-1"></i>Curso <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select @error('curso_id') is-invalid @enderror" id="curso_id" name="curso_id" required>
                                        <option value="">Selecione um curso</option>
                                        @foreach($cursos as $curso)
                                            <option value="{{ $curso->id }}" {{ old('curso_id') == $curso->id ? 'selected' : '' }}>
                                                {{ $curso->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('curso_id')
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
                                            Ativo
                                        </label>
                                    </div>
                                </div>

                                <x-info-card type="info" title="Importante">
                                    Cada curso pode ter apenas um coordenador ativo por ano lectivo.
                                </x-info-card>

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a href="{{ route('coordenadores-curso.index') }}" class="btn btn-outline-secondary">
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
</x-app-layout>
