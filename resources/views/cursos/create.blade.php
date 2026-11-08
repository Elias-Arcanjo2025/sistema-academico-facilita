<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-journal-plus me-2 text-primary"></i>Novo Curso
            </h2>
            <a href="{{ route('cursos.index') }}" class="btn btn-outline-secondary">
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
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informações do Curso</h5>
                        </div>
                        <div class="card-body">
                            <x-tip-card icon="bi-info-circle" variant="info" title="Criar Curso" id="tip-cursos-create" compact>
                                Preencha o código (sigla única), nome e seleccione a classe. Campos com <strong>*</strong> são obrigatórios.
                            </x-tip-card>
                            <form action="{{ route('cursos.store') }}" method="POST">
                                @csrf

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="codigo" class="form-label">
                                            <i class="bi bi-hash me-1"></i>Código <span class="text-danger">*</span>
                                            <x-help-icon content="Código único do curso (ex: INFO, CONT)" />
                                        </label>
                                        <input type="text" class="form-control @error('codigo') is-invalid @enderror"
                                               id="codigo" name="codigo" value="{{ old('codigo') }}"
                                               maxlength="20" required placeholder="Ex: INFO">
                                        @error('codigo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-8 mb-3">
                                        <label for="nome" class="form-label">
                                            <i class="bi bi-type me-1"></i>Nome <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control @error('nome') is-invalid @enderror"
                                               id="nome" name="nome" value="{{ old('nome') }}"
                                               maxlength="255" required placeholder="Ex: Informatica">
                                        @error('nome')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="descricao" class="form-label">
                                        <i class="bi bi-text-paragraph me-1"></i>Descrição
                                        <x-help-icon
                                            type="popover"
                                            title="Sobre a Descrição"
                                            content="A descrição é opcional e pode conter informações adicionais sobre o curso. Será exibida nas listagens e relatórios."
                                        />
                                    </label>
                                    <textarea class="form-control @error('descricao') is-invalid @enderror"
                                              id="descricao" name="descricao" rows="3"
                                              placeholder="Descrição opcional do curso...">{{ old('descricao') }}</textarea>
                                    @error('descricao')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="classe_max" class="form-label">
                                            <i class="bi bi-mortarboard me-1"></i>Última Classe do Curso
                                            <x-help-icon
                                                type="popover"
                                                title="Classe Máxima"
                                                content="N.º da última classe leccionada pelo curso (ex.: 12 para técnicos, 9 para básico). Usado para detectar disciplinas terminais."
                                            />
                                        </label>
                                        <select class="form-select @error('classe_max') is-invalid @enderror"
                                                id="classe_max" name="classe_max">
                                            <option value="">— Não definido —</option>
                                            @foreach([9, 10, 11, 12, 13] as $cn)
                                                <option value="{{ $cn }}" {{ (int) old('classe_max', 12) === $cn ? 'selected' : '' }}>
                                                    {{ $cn }}ª Classe
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('classe_max')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-8 mb-3">
                                        <label for="regime_calculo" class="form-label">
                                            <i class="bi bi-calculator me-1"></i>Regime de Cálculo da Pauta <span class="text-danger">*</span>
                                            <x-help-icon
                                                type="popover"
                                                title="Sobre o Regime"
                                                content="Decide as fórmulas e o layout da pauta:&#10;&#10;• Politécnico: T2 calcula MFT2=(MT1+MT2)/2; T3 usa MFT2, CF e carry-overs de CA (11ª/12ª).&#10;&#10;• Administração: Trimestres totalmente independentes (sem MFT2); CF=(MT1+MT2+MT3)/3; pauta final mostra apenas CF|PG|CA; sem carry-overs."
                                            />
                                        </label>
                                        <select class="form-select @error('regime_calculo') is-invalid @enderror"
                                                id="regime_calculo" name="regime_calculo" required>
                                            @foreach(\App\Models\Curso::regimesDisponiveis() as $valor => $label)
                                                <option value="{{ $valor }}" {{ old('regime_calculo', 'politecnico') === $valor ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('regime_calculo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="activo" value="0">
                                        <input type="checkbox" class="form-check-input" id="activo" name="activo" value="1"
                                               {{ old('activo', true) ? 'checked' : '' }} style="width: 3em; height: 1.5em;">
                                        <label class="form-check-label ms-2" for="activo">
                                            <strong>Curso Ativo</strong>
                                            <br><small class="text-muted">Cursos inativos não aparecem nas listagens</small>
                                        </label>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('cursos.index') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-lg me-1"></i>Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i>Guardar Curso
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
