<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Arquivar Ficheiro de Pauta</h2>
            <a href="{{ route('pauta-arquivos.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container-fluid">
            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <ul class="nav nav-pills mb-4" id="pautaArquivoTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-sistema" data-bs-toggle="pill" data-bs-target="#painel-sistema" type="button">
                        <i class="bi bi-magic me-1"></i> Gerar do sistema
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-upload" data-bs-toggle="pill" data-bs-target="#painel-upload" type="button">
                        <i class="bi bi-upload me-1"></i> Enviar ficheiro externo
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                {{-- Gerar do sistema --}}
                <div class="tab-pane fade show active" id="painel-sistema" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-7">
                            <div class="card border-primary border-opacity-25 shadow-sm">
                                <div class="card-header bg-primary bg-opacity-10">
                                    <h5 class="mb-0"><i class="bi bi-file-earmark-excel text-success me-2"></i>Gerar e arquivar automaticamente</h5>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small">
                                        O sistema gera o Excel oficial com as notas actuais e guarda-o no arquivo de pautas (versão automática).
                                    </p>

                                    <form action="{{ route('pauta-arquivos.arquivar-sistema') }}" method="POST" id="formSistema">
                                        @csrf

                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Curso <span class="text-danger">*</span></label>
                                                <select class="form-select js-curso" id="sistema_curso_id" required onchange="carregarTurmasSistema(this.value)">
                                                    <option value="">Seleccione</option>
                                                    @foreach($cursos as $curso)
                                                        <option value="{{ $curso->id }}" {{ old('curso_id') == $curso->id ? 'selected' : '' }}>{{ $curso->nome }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Turma <span class="text-danger">*</span></label>
                                                <select class="form-select" name="turma_id" id="sistema_turma_id" required onchange="carregarDisciplinasSistema(this.value)">
                                                    <option value="">Seleccione o curso primeiro</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Ano Lectivo <span class="text-danger">*</span></label>
                                                <select class="form-select" name="ano_lectivo" required>
                                                    @foreach($anosLectivos as $ano)
                                                        <option value="{{ $ano }}" {{ (old('ano_lectivo') ?? $anoLectivoActual) == $ano ? 'selected' : '' }}>{{ $ano }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Trimestre <span class="text-danger">*</span></label>
                                                <select class="form-select" name="trimestre" id="sistema_trimestre" required>
                                                    <option value="0">Anual (pauta final)</option>
                                                    <option value="1" {{ (old('trimestre', $trimestreActual) == 1) ? 'selected' : '' }}>1.º Trimestre</option>
                                                    <option value="2" {{ (old('trimestre', $trimestreActual) == 2) ? 'selected' : '' }}>2.º Trimestre</option>
                                                    <option value="3" {{ (old('trimestre', $trimestreActual) == 3) ? 'selected' : '' }}>3.º Trimestre</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Tipo de Pauta <span class="text-danger">*</span></label>
                                                <select class="form-select" name="tipo_pauta" id="sistema_tipo_pauta" required onchange="actualizarCamposSistema()">
                                                    <option value="">Seleccione</option>
                                                    <option value="mini_pauta" {{ old('tipo_pauta') == 'mini_pauta' ? 'selected' : '' }}>Mini-Pauta</option>
                                                    <option value="completa" {{ old('tipo_pauta') == 'completa' ? 'selected' : '' }}>Pauta Trimestral Completa</option>
                                                    <option value="final_disciplina" {{ old('tipo_pauta') == 'final_disciplina' ? 'selected' : '' }}>Pauta Final de Disciplina</option>
                                                    <option value="final_turma" {{ old('tipo_pauta') == 'final_turma' ? 'selected' : '' }}>Pauta Final de Turma</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6" id="wrap_disciplina_sistema">
                                                <label class="form-label">Disciplina <span class="text-danger" id="disciplina_obrigatoria">*</span></label>
                                                <select class="form-select" name="disciplina_id" id="sistema_disciplina_id">
                                                    <option value="">Seleccione a turma primeiro</option>
                                                </select>
                                                <small class="text-muted" id="hint_disciplina">Obrigatória para mini-pauta e pauta final de disciplina.</small>
                                            </div>
                                        </div>

                                        <div class="mt-4">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-magic"></i> Gerar e arquivar
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="card bg-light border-0 h-100">
                                <div class="card-body">
                                    <h6 class="text-primary"><i class="bi bi-lightbulb me-1"></i> Quando usar</h6>
                                    <ul class="small text-muted mb-0">
                                        <li class="mb-2"><strong>Mini-Pauta</strong> — arquivo oficial por disciplina e trimestre</li>
                                        <li class="mb-2"><strong>Pauta Completa</strong> — todas as disciplinas num trimestre</li>
                                        <li class="mb-2"><strong>Final de Disciplina / Turma</strong> — arquivo anual</li>
                                        <li>Cada geração cria uma nova <strong>versão</strong> (v1, v2…)</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Upload externo --}}
                <div class="tab-pane fade" id="painel-upload" role="tabpanel">
                    <div class="card shadow-sm border-0">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-upload me-2"></i>Enviar ficheiro externo</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Para pautas digitalizadas ou Excel de anos anteriores. Formatos: .xlsx, .xls (máx. 10 MB).
                            </p>

                            <form action="{{ route('pauta-arquivos.store') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="curso_id" class="form-label">Curso <span class="text-danger">*</span></label>
                                        <select class="form-select @error('curso_id') is-invalid @enderror" id="curso_id" name="curso_id" required onchange="carregarTurmasUpload(this.value)">
                                            <option value="">Seleccione</option>
                                            @foreach($cursos as $curso)
                                                <option value="{{ $curso->id }}" {{ old('curso_id') == $curso->id ? 'selected' : '' }}>{{ $curso->nome }}</option>
                                            @endforeach
                                        </select>
                                        @error('curso_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label for="turma_id" class="form-label">Turma</label>
                                        <select class="form-select" id="turma_id" name="turma_id" onchange="carregarDisciplinasUpload(this.value)">
                                            <option value="">Seleccione o curso primeiro</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="ano_lectivo" class="form-label">Ano Lectivo <span class="text-danger">*</span></label>
                                        <select class="form-select" id="ano_lectivo" name="ano_lectivo" required>
                                            @foreach($anosLectivos as $ano)
                                                <option value="{{ $ano }}" {{ (old('ano_lectivo') ?? $anoLectivoActual) == $ano ? 'selected' : '' }}>{{ $ano }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="trimestre" class="form-label">Trimestre <span class="text-danger">*</span></label>
                                        <select class="form-select" id="trimestre" name="trimestre" required>
                                            <option value="0">Anual</option>
                                            @for($t = 1; $t <= 3; $t++)
                                                <option value="{{ $t }}" {{ (old('trimestre', $trimestreActual) == $t) ? 'selected' : '' }}>{{ $t }}.º Trimestre</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="tipo_pauta" class="form-label">Tipo <span class="text-danger">*</span></label>
                                        <select class="form-select" id="tipo_pauta" name="tipo_pauta" required>
                                            @foreach(\App\Models\PautaArquivo::getTiposPauta() as $key => $label)
                                                <option value="{{ $key }}" {{ old('tipo_pauta') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="disciplina_id" class="form-label">Disciplina</label>
                                        <select class="form-select" id="disciplina_id" name="disciplina_id">
                                            <option value="">— Opcional —</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="origem" class="form-label">Origem <span class="text-danger">*</span></label>
                                        <select class="form-select" id="origem" name="origem" required>
                                            @foreach(\App\Models\PautaArquivo::getOrigensArquivo() as $key => $label)
                                                <option value="{{ $key }}" {{ old('origem', 'externa') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="arquivo" class="form-label">Ficheiro Excel <span class="text-danger">*</span></label>
                                        <input type="file" class="form-control @error('arquivo') is-invalid @enderror" id="arquivo" name="arquivo" accept=".xlsx,.xls" required>
                                        @error('arquivo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12">
                                        <label for="descricao" class="form-label">Descrição</label>
                                        <textarea class="form-control" id="descricao" name="descricao" rows="2" maxlength="1000" placeholder="Observações opcionais…">{{ old('descricao') }}</textarea>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" {{ old('activo', true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="activo">Ficheiro activo</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" id="usar_manual" onchange="toggleManual()">
                                    <label class="form-check-label" for="usar_manual">Registo histórico (turma/disciplina não existente no sistema)</label>
                                </div>
                                <div id="campos_manuais" class="row g-3 mt-1 d-none">
                                    <div class="col-md-4">
                                        <label class="form-label">Turma (texto)</label>
                                        <input type="text" class="form-control" name="turma_manual" value="{{ old('turma_manual') }}" placeholder="Ex: TI11AM 2019">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Disciplina (texto)</label>
                                        <input type="text" class="form-control" name="disciplina_manual" value="{{ old('disciplina_manual') }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Sala (texto)</label>
                                        <input type="text" class="form-control" name="sala_manual" value="{{ old('sala_manual') }}">
                                    </div>
                                </div>

                                <hr class="my-4">
                                <button type="submit" class="btn btn-success" id="btnSubmit">
                                    <i class="bi bi-upload"></i> Enviar ficheiro
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const turmasUrl = @json(route('pauta-arquivos.turmas'));
        const disciplinasUrl = @json(route('pauta-arquivos.disciplinas'));

        function carregarTurmas(selectId, cursoId, selectedId) {
            const select = document.getElementById(selectId);
            if (!select) return;
            select.innerHTML = '<option value="">A carregar…</option>';
            if (!cursoId) {
                select.innerHTML = '<option value="">Seleccione o curso primeiro</option>';
                return;
            }
            fetch(`${turmasUrl}?curso_id=${cursoId}`)
                .then(r => r.json())
                .then(data => {
                    select.innerHTML = '<option value="">— Seleccione —</option>';
                    data.forEach(t => {
                        const opt = document.createElement('option');
                        opt.value = t.id;
                        opt.textContent = t.nome;
                        if (selectedId && String(t.id) === String(selectedId)) opt.selected = true;
                        select.appendChild(opt);
                    });
                });
        }

        function carregarDisciplinas(selectId, turmaId, selectedId) {
            const select = document.getElementById(selectId);
            if (!select) return;
            if (!turmaId) {
                select.innerHTML = '<option value="">Seleccione a turma primeiro</option>';
                return;
            }
            fetch(`${disciplinasUrl}?turma_id=${turmaId}`)
                .then(r => r.json())
                .then(data => {
                    select.innerHTML = '<option value="">— Opcional —</option>';
                    data.forEach(d => {
                        const opt = document.createElement('option');
                        opt.value = d.id;
                        opt.textContent = d.nome;
                        if (selectedId && String(d.id) === String(selectedId)) opt.selected = true;
                        select.appendChild(opt);
                    });
                });
        }

        function carregarTurmasSistema(cursoId) {
            carregarTurmas('sistema_turma_id', cursoId, @json(old('turma_id')));
            document.getElementById('sistema_disciplina_id').innerHTML = '<option value="">Seleccione a turma primeiro</option>';
        }
        function carregarDisciplinasSistema(turmaId) {
            carregarDisciplinas('sistema_disciplina_id', turmaId, @json(old('disciplina_id')));
        }
        function carregarTurmasUpload(cursoId) {
            carregarTurmas('turma_id', cursoId, @json(old('turma_id')));
        }
        function carregarDisciplinasUpload(turmaId) {
            carregarDisciplinas('disciplina_id', turmaId, @json(old('disciplina_id')));
        }

        function actualizarCamposSistema() {
            const tipo = document.getElementById('sistema_tipo_pauta').value;
            const disc = document.getElementById('sistema_disciplina_id');
            const wrap = document.getElementById('wrap_disciplina_sistema');
            const precisaDisc = ['mini_pauta', 'final_disciplina'].includes(tipo);
            const precisaTrim = tipo === 'completa';

            wrap.classList.toggle('d-none', tipo === 'final_turma');
            disc.required = precisaDisc;

            const trim = document.getElementById('sistema_trimestre');
            if (tipo === 'final_turma' || tipo === 'final_disciplina') {
                trim.value = '0';
            } else if (precisaTrim && trim.value === '0') {
                trim.value = '{{ $trimestreActual }}';
            }
        }

        function toggleManual() {
            document.getElementById('campos_manuais').classList.toggle('d-none', !document.getElementById('usar_manual').checked);
        }

        document.addEventListener('DOMContentLoaded', function () {
            const cursoUpload = document.getElementById('curso_id');
            if (cursoUpload?.value) carregarTurmasUpload(cursoUpload.value);

            const cursoSistema = document.getElementById('sistema_curso_id');
            if (cursoSistema?.value) {
                carregarTurmasSistema(cursoSistema.value);
                setTimeout(() => {
                    const tid = @json(old('turma_id'));
                    if (tid) carregarDisciplinasSistema(tid);
                }, 400);
            }

            actualizarCamposSistema();

            document.getElementById('uploadForm')?.addEventListener('submit', function () {
                const btn = document.getElementById('btnSubmit');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>A enviar…';
            });
        });
    </script>
    @endpush
</x-app-layout>
