<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Nova Turma</h2>
            <a href="{{ route('turmas.index') }}" class="btn btn-outline-secondary">
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
                            <x-tip-card icon="bi-info-circle" variant="info" title="Criar Turma" id="tip-turmas-create" compact>
                                Seleccione o curso, classe, turno e sala. O nome da turma é gerado automaticamente ou pode ser personalizado. Defina o limite de vagas para controlo de inscrições.
                            </x-tip-card>
                            <form action="{{ route('turmas.store') }}" method="POST">
                                @csrf

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="codigo" class="form-label">
                                            <i class="bi bi-hash me-1"></i>Código <span class="text-danger">*</span>
                                            <x-help-icon content="Código único da turma (ex: 10A-M, 11B-T)" />
                                        </label>
                                        <input type="text" class="form-control @error('codigo') is-invalid @enderror" id="codigo" name="codigo" value="{{ old('codigo') }}" required maxlength="20" placeholder="Ex: 10A-M">
                                        @error('codigo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-8 mb-3">
                                        <label for="nome" class="form-label">
                                            <i class="bi bi-type me-1"></i>Nome <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control @error('nome') is-invalid @enderror" id="nome" name="nome" value="{{ old('nome') }}" required maxlength="255" placeholder="Ex: 10a Classe - Turma A">
                                        @error('nome')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
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

                                    <div class="col-md-6 mb-3">
                                        <label for="classe_id" class="form-label">
                                            <i class="bi bi-mortarboard me-1"></i>Classe <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select @error('classe_id') is-invalid @enderror" id="classe_id" name="classe_id" required>
                                            <option value="">Selecione uma classe</option>
                                            @foreach($classes as $classe)
                                                <option value="{{ $classe->id }}" {{ old('classe_id') == $classe->id ? 'selected' : '' }}>
                                                    {{ $classe->nome }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('classe_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="ano_lectivo_id" class="form-label">
                                        <i class="bi bi-calendar-range me-1"></i>Ano Lectivo <span class="text-danger">*</span>
                                        <x-help-icon
                                            type="popover"
                                            title="Ano Lectivo"
                                            content="O ano lectivo representa o período escolar (ex: 2024/2025). Por padrão, é preenchido com o ano lectivo activo configurado no sistema."
                                        />
                                    </label>
                                    <select class="form-select @error('ano_lectivo_id') is-invalid @enderror" id="ano_lectivo_id" name="ano_lectivo_id" required>
                                        @foreach($anosLectivos as $id => $nome)
                                            <option value="{{ $id }}" {{ old('ano_lectivo_id', \App\Models\AnoLectivo::getActualId()) == $id ? 'selected' : '' }}>
                                                {{ $nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('ano_lectivo_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="turno_id" class="form-label">
                                            <i class="bi bi-clock me-1"></i>Turno <span class="text-muted">(opcional)</span>
                                            <x-help-icon
                                                type="popover"
                                                title="Turnos"
                                                content="O turno define o período de funcionamento da turma (ex: Manhã, Tarde, Noite). Importante para organização de horários e salas."
                                            />
                                        </label>
                                        <select class="form-select @error('turno_id') is-invalid @enderror" id="turno_id" name="turno_id">
                                            <option value="">Selecione um turno</option>
                                            @foreach($turnos as $turno)
                                                <option value="{{ $turno->id }}" {{ old('turno_id') == $turno->id ? 'selected' : '' }}>
                                                    {{ $turno->nome }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('turno_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="sala_id" class="form-label">
                                            <i class="bi bi-door-open me-1"></i>Sala <span class="text-muted">(opcional)</span>
                                            <x-help-icon
                                                type="popover"
                                                title="Sala de Aula"
                                                content="Selecione a sala fixa onde a turma terá as aulas. A capacidade da sala é mostrada entre parênteses para ajudar na escolha adequada."
                                            />
                                        </label>
                                        <select class="form-select @error('sala_id') is-invalid @enderror" id="sala_id" name="sala_id">
                                            <option value="">Selecione uma sala</option>
                                            @foreach($salas as $sala)
                                                <option value="{{ $sala->id }}" {{ old('sala_id') == $sala->id ? 'selected' : '' }}>
                                                    {{ $sala->nome }} {{ $sala->capacidade ? '(' . $sala->capacidade . ' lugares)' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('sala_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="hidden" name="activo" value="0">
                                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" {{ old('activo', true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="activo">
                                            Turma activa
                                        </label>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('turmas.index') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-lg me-1"></i>Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i>Guardar Turma
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
