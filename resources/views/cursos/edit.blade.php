<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-journal-check me-2 text-primary"></i>Editar Curso
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
                            <h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Editar: {{ $curso->nome }}</h5>
                        </div>
                        <div class="card-body">
                            <x-tip-card icon="bi-pencil" variant="info" title="Editar Curso" id="tip-cursos-edit" compact>
                                Actualize os dados do curso conforme necessário. O código deve permanecer único no sistema.
                            </x-tip-card>
                            <form action="{{ route('cursos.update', $curso) }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="codigo" class="form-label">
                                            <i class="bi bi-hash me-1"></i>Código <span class="text-danger">*</span>
                                            <x-help-icon content="Código único do curso (ex: INFO, CONT)" />
                                        </label>
                                        <input type="text" class="form-control @error('codigo') is-invalid @enderror"
                                               id="codigo" name="codigo" value="{{ old('codigo', $curso->codigo) }}"
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
                                               id="nome" name="nome" value="{{ old('nome', $curso->nome) }}"
                                               maxlength="255" required>
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
                                              placeholder="Descrição opcional do curso...">{{ old('descricao', $curso->descricao) }}</textarea>
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
                                                content="N.º da última classe leccionada pelo curso (ex.: 12 para técnicos, 9 para básico, 13 para alguns cursos PT). Usado para detectar disciplinas terminais (que terminam antes desta classe)."
                                            />
                                        </label>
                                        <select class="form-select @error('classe_max') is-invalid @enderror"
                                                id="classe_max" name="classe_max">
                                            <option value="">— Não definido —</option>
                                            @foreach([9, 10, 11, 12, 13] as $cn)
                                                <option value="{{ $cn }}" {{ (int) old('classe_max', $curso->classe_max) === $cn ? 'selected' : '' }}>
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
                                                content="Decide as fórmulas e o layout da pauta:&#10;&#10;• Politécnico: T2 calcula MFT2=(MT1+MT2)/2; T3 usa MFT2, CF e carry-overs de CA (11ª/12ª).&#10;&#10;• Administração: Trimestres totalmente independentes (sem MFT2); CF=(MT1+MT2+MT3)/3; pauta final mostra apenas CF|PG|CA; sem carry-overs.&#10;&#10;ATENÇÃO: mudar este valor afecta a interpretação de TODAS as notas existentes deste curso."
                                            />
                                        </label>
                                        <select class="form-select @error('regime_calculo') is-invalid @enderror"
                                                id="regime_calculo" name="regime_calculo" required>
                                            @foreach(\App\Models\Curso::regimesDisponiveis() as $valor => $label)
                                                <option value="{{ $valor }}" {{ old('regime_calculo', $curso->regime_calculo ?? 'politecnico') === $valor ? 'selected' : '' }}>
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
                                               {{ old('activo', $curso->activo) ? 'checked' : '' }} style="width: 3em; height: 1.5em;">
                                        <label class="form-check-label ms-2" for="activo">
                                            <strong>Curso Ativo</strong>
                                            <br><small class="text-muted">Cursos inativos não aparecem nas listagens</small>
                                        </label>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <small class="text-muted">
                                            <i class="bi bi-clock me-1"></i>Criado em {{ $curso->created_at->format('d/m/Y H:i') }}
                                        </small>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="{{ route('cursos.index') }}" class="btn btn-outline-secondary">
                                            <i class="bi bi-x-lg me-1"></i>Cancelar
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-lg me-1"></i>Actualizar Curso
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============================================================
                 SECÇÃO: DISCIPLINAS DO CURSO (gestão de classe_inicio/fim/PG)
                 Plano B — fonte única de verdade para "disciplina terminal".
                 ============================================================ --}}
            <div class="row justify-content-center mt-4">
                <div class="col-lg-10">
                    <div class="card shadow-soft fade-in">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-book me-2 text-success"></i>Disciplinas do Curso
                            </h5>
                            <span class="badge bg-secondary">{{ $curso->disciplinas->count() }} disciplina(s)</span>
                        </div>
                        <div class="card-body">
                            <x-tip-card icon="bi-info-circle" variant="warning" title="Disciplina Terminal" id="tip-curso-disc" compact>
                                Define em que classe cada disciplina <strong>começa</strong> e <strong>termina</strong> neste curso.
                                Uma disciplina é considerada <strong>terminal</strong> (gera CFD) quando termina antes da última classe do curso
                                (<code>{{ $curso->classe_max ?? '?' }}ª</code>). Exemplo: TIC com fim na <code>10ª</code> num curso até à <code>12ª</code> → terminal na 10ª.
                            </x-tip-card>

                            @if($curso->disciplinas->isEmpty())
                                <div class="alert alert-info mb-0">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Este curso ainda não tem disciplinas associadas.
                                    Associe disciplinas a partir da página de cada <a href="{{ route('disciplinas.index') }}">disciplina</a>.
                                </div>
                            @else
                                <form action="{{ route('cursos.disciplinas.actualizar', $curso) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Disciplina</th>
                                                    <th style="width:120px;">Classe início</th>
                                                    <th style="width:120px;">Classe fim</th>
                                                    <th style="width:90px;" class="text-center">Prova Global?</th>
                                                    <th style="width:90px;" class="text-center">
                                                        Blindada?
                                                        <x-help-icon
                                                            type="popover"
                                                            title="Disciplina Blindada"
                                                            content="Disciplina cuja reprovação simultânea com TODAS as outras blindadas (ex.: LP + Mat) faz o aluno perder o direito ao Exame de Recurso. Regra MED Art.º 31.º."
                                                        />
                                                    </th>
                                                    <th style="width:120px;">Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($curso->disciplinas as $disc)
                                                    @php
                                                        $cmax = (int) ($curso->classe_max ?? 12);
                                                        $cfim = (int) ($disc->pivot->classe_fim ?? 0);
                                                        $isTerminal = $cfim > 0 && $cfim < $cmax;
                                                    @endphp
                                                    <tr>
                                                        <td>
                                                            <strong>{{ $disc->codigo ?: '—' }}</strong>
                                                            <small class="text-muted d-block">{{ $disc->nome }}</small>
                                                        </td>
                                                        <td>
                                                            <select name="disciplinas[{{ $disc->id }}][classe_inicio]"
                                                                    class="form-select form-select-sm">
                                                                <option value="">—</option>
                                                                @foreach([7,8,9,10,11,12,13] as $cn)
                                                                    <option value="{{ $cn }}"
                                                                        {{ (int) $disc->pivot->classe_inicio === $cn ? 'selected' : '' }}>
                                                                        {{ $cn }}ª
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <select name="disciplinas[{{ $disc->id }}][classe_fim]"
                                                                    class="form-select form-select-sm">
                                                                <option value="">—</option>
                                                                @foreach([7,8,9,10,11,12,13] as $cn)
                                                                    <option value="{{ $cn }}"
                                                                        {{ (int) $disc->pivot->classe_fim === $cn ? 'selected' : '' }}>
                                                                        {{ $cn }}ª
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="form-check form-switch d-inline-flex">
                                                                <input type="hidden" name="disciplinas[{{ $disc->id }}][permite_pg]" value="0">
                                                                <input type="checkbox" class="form-check-input"
                                                                       name="disciplinas[{{ $disc->id }}][permite_pg]" value="1"
                                                                       {{ $disc->pivot->permite_pg ? 'checked' : '' }}>
                                                            </div>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="form-check form-switch d-inline-flex">
                                                                <input type="hidden" name="disciplinas[{{ $disc->id }}][blindada]" value="0">
                                                                <input type="checkbox" class="form-check-input"
                                                                       name="disciplinas[{{ $disc->id }}][blindada]" value="1"
                                                                       {{ $disc->pivot->blindada ? 'checked' : '' }}>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            @if($isTerminal)
                                                                <span class="badge bg-warning text-dark" title="Termina antes da última classe do curso">
                                                                    <i class="bi bi-flag-fill me-1"></i>Terminal na {{ $cfim }}ª
                                                                </span>
                                                            @elseif($cfim > 0)
                                                                <span class="badge bg-light text-muted">Até ao fim</span>
                                                            @else
                                                                <span class="badge bg-danger-subtle text-danger">Sem fim definido</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-end mt-3">
                                        <button type="submit" class="btn btn-success">
                                            <i class="bi bi-check2-square me-1"></i>Guardar Disciplinas
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
