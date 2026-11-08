<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">
                <i class="bi bi-file-earmark-excel me-2 text-success"></i>Importação de Mini-Pauta via Excel
            </h2>
            <a href="{{ route('mini-pautas.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container-fluid">
            <x-tip-card icon="bi-file-earmark-excel" variant="info" title="Como funciona" id="tip-minipauta-importacao">
                Descarregue o modelo Excel oficial da mini-pauta (uma disciplina), preencha as notas lançáveis (MAC, PP, PT ou MAC3, PP3, PG no 3.º trimestre) e carregue o ficheiro. As médias são recalculadas automaticamente. Não altere a estrutura do modelo nem a ordem dos alunos.
            </x-tip-card>

            @if($turmas->isEmpty())
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Não tem turmas disponíveis para importação. Verifique as suas associações de professor/disciplina/turma.
                </div>
            @else
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm h-100 border-0">
                        <div class="card-header bg-primary bg-opacity-10 border-0">
                            <h5 class="mb-0">
                                <span class="badge bg-primary me-2">1</span>
                                <i class="bi bi-download me-1"></i>Descarregar modelo Excel
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Seleccione turma, disciplina e trimestre para gerar a mini-pauta oficial com todos os alunos.
                            </p>

                            <form action="{{ route('export.mini-pauta.excel') }}" method="GET" id="downloadForm">
                                <input type="hidden" name="ano_lectivo" value="{{ $anoLectivo }}">

                                <div class="mb-3">
                                    <label for="download_turma_id" class="form-label">Turma <span class="text-danger">*</span></label>
                                    <select class="form-select js-sync-turma js-carregar-disciplinas" id="download_turma_id" name="turma_id" required>
                                        <option value="">Seleccione uma turma</option>
                                        @foreach($turmas as $turma)
                                            <option value="{{ $turma->id }}">
                                                {{ $turma->nome }} — {{ $turma->curso->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="download_disciplina_id" class="form-label">Disciplina <span class="text-danger">*</span></label>
                                    <select class="form-select js-sync-disciplina" id="download_disciplina_id" name="disciplina_id" required disabled>
                                        <option value="">Seleccione a turma primeiro</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="download_trimestre" class="form-label">
                                        Trimestre <span class="text-danger">*</span>
                                        <span class="badge bg-info ms-1">Actual: {{ $trimestreActual }}º</span>
                                    </label>
                                    <select class="form-select js-sync-trimestre" id="download_trimestre" name="trimestre" required>
                                        @for($t = 1; $t <= 3; $t++)
                                            @php
                                                $status = $trimestresStatus[$t] ?? ['aberto' => true, 'actual' => false];
                                                $isAberto = $status['aberto'];
                                                $isActual = $status['actual'];
                                                $ignoraTrimestreFechado = auth()->user()->podeIgnorarTrimestreFechado();
                                                $disabled = !$ignoraTrimestreFechado && !$isAberto;
                                                $label = "{$t}.º Trimestre";
                                                if ($isActual) $label .= ' (Actual)';
                                                if (!$isAberto && !$ignoraTrimestreFechado) $label .= ' — FECHADO';
                                            @endphp
                                            <option value="{{ $t }}"
                                                    {{ $isActual ? 'selected' : '' }}
                                                    {{ $disabled ? 'disabled' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endfor
                                    </select>
                                    @if(!auth()->user()->podeIgnorarTrimestreFechado())
                                        <div class="form-text">Apenas o trimestre actual está disponível para importação.</div>
                                    @endif
                                </div>

                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-download"></i> Descarregar mini-pauta
                                </button>
                            </form>

                            <div class="mt-3 p-3 rounded bg-light small">
                                <strong class="d-block mb-2"><i class="bi bi-list-check me-1"></i>O modelo inclui:</strong>
                                <ul class="mb-0 ps-3">
                                    <li>Formato oficial da escola (cabeçalho, logo, estatísticas)</li>
                                    <li>Lista de alunos da turma na disciplina</li>
                                    <li>Colunas de notas do trimestre seleccionado</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm h-100 border-0">
                        <div class="card-header bg-success bg-opacity-10 border-0">
                            <h5 class="mb-0">
                                <span class="badge bg-success me-2">2</span>
                                <i class="bi bi-upload me-1"></i>Importar ficheiro preenchido
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Após preencher o modelo, seleccione os mesmos dados e carregue o ficheiro Excel.
                            </p>

                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible fade show">
                                    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            @if(session('warning'))
                                <div class="alert alert-warning alert-dismissible fade show">
                                    <i class="bi bi-exclamation-triangle me-2"></i>{{ session('warning') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            @if(session('error'))
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <i class="bi bi-x-circle me-2"></i>{{ session('error') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            @if(session('erros_importacao'))
                                <div class="alert alert-danger">
                                    <strong>Erros encontrados durante a importação:</strong>
                                    <ul class="mb-0 mt-2" style="max-height: 200px; overflow-y: auto;">
                                        @foreach(session('erros_importacao') as $erro)
                                            <li><small>{{ $erro }}</small></li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form action="{{ route('mini-pautas.importacao.store') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="ano_lectivo" value="{{ $anoLectivo }}">

                                <div class="mb-3">
                                    <label for="upload_turma_id" class="form-label">Turma <span class="text-danger">*</span></label>
                                    <select class="form-select js-sync-turma js-carregar-disciplinas @error('turma_id') is-invalid @enderror" id="upload_turma_id" name="turma_id" required>
                                        <option value="">Seleccione uma turma</option>
                                        @foreach($turmas as $turma)
                                            <option value="{{ $turma->id }}" {{ old('turma_id') == $turma->id ? 'selected' : '' }}>
                                                {{ $turma->nome }} — {{ $turma->curso->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('turma_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="upload_disciplina_id" class="form-label">Disciplina <span class="text-danger">*</span></label>
                                    <select class="form-select js-sync-disciplina @error('disciplina_id') is-invalid @enderror" id="upload_disciplina_id" name="disciplina_id" required {{ old('turma_id') ? '' : 'disabled' }}>
                                        <option value="">{{ old('turma_id') ? 'A carregar...' : 'Seleccione a turma primeiro' }}</option>
                                    </select>
                                    @error('disciplina_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="upload_trimestre" class="form-label">
                                        Trimestre <span class="text-danger">*</span>
                                        <span class="badge bg-info ms-1">Actual: {{ $trimestreActual }}º</span>
                                    </label>
                                    <select class="form-select js-sync-trimestre @error('trimestre') is-invalid @enderror" id="upload_trimestre" name="trimestre" required>
                                        @for($t = 1; $t <= 3; $t++)
                                            @php
                                                $status = $trimestresStatus[$t] ?? ['aberto' => true, 'actual' => false];
                                                $isAberto = $status['aberto'];
                                                $isActual = $status['actual'];
                                                $ignoraTrimestreFechado = auth()->user()->podeIgnorarTrimestreFechado();
                                                $disabled = !$ignoraTrimestreFechado && !$isAberto;
                                                $label = "{$t}.º Trimestre";
                                                if ($isActual) $label .= ' (Actual)';
                                                if (!$isAberto && !$ignoraTrimestreFechado) $label .= ' — FECHADO';
                                            @endphp
                                            <option value="{{ $t }}"
                                                    {{ (old('trimestre', $trimestreActual) == $t) ? 'selected' : '' }}
                                                    {{ $disabled ? 'disabled' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endfor
                                    </select>
                                    @error('trimestre')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="arquivo" class="form-label">Ficheiro Excel <span class="text-danger">*</span></label>
                                    <input type="file"
                                           class="form-control @error('arquivo') is-invalid @enderror"
                                           id="arquivo"
                                           name="arquivo"
                                           accept=".xlsx,.xls"
                                           required>
                                    @error('arquivo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">Formatos aceites: .xlsx, .xls (máx. 10 MB)</div>
                                </div>

                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-upload"></i> Importar notas
                                </button>
                            </form>

                            <div class="mt-3 p-3 rounded border border-warning border-opacity-25 bg-warning bg-opacity-10 small">
                                <strong class="d-block mb-2"><i class="bi bi-exclamation-triangle me-1"></i>Atenção:</strong>
                                <ul class="mb-0 ps-3">
                                    <li>Use o modelo descarregado deste sistema</li>
                                    <li>Não edite colunas calculadas (MT, MFT2, CF, CA)</li>
                                    <li>Células vazias são ignoradas</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const turmaSelects = document.querySelectorAll('.js-sync-turma');
            const disciplinaSelects = document.querySelectorAll('.js-sync-disciplina');
            const trimestreSelects = document.querySelectorAll('.js-sync-trimestre');
            const disciplinasUrl = @json(route('mini-pautas.disciplinas'));
            const oldDisciplinaId = @json(old('disciplina_id'));
            const oldTurmaId = @json(old('turma_id'));

            function syncSelects(selects) {
                selects.forEach(function (select) {
                    select.addEventListener('change', function () {
                        selects.forEach(function (other) {
                            if (other !== select) {
                                other.value = select.value;
                            }
                        });
                    });
                });
            }

            syncSelects([...turmaSelects]);
            syncSelects([...disciplinaSelects]);
            syncSelects([...trimestreSelects]);

            function preencherDisciplinas(turmaId, selectedId) {
                disciplinaSelects.forEach(function (select) {
                    if (!turmaId) {
                        select.innerHTML = '<option value="">Seleccione a turma primeiro</option>';
                        select.disabled = true;
                        return;
                    }

                    select.innerHTML = '<option value="">A carregar...</option>';
                    select.disabled = true;

                    fetch(`${disciplinasUrl}?turma_id=${turmaId}`)
                        .then(response => response.json())
                        .then(data => {
                            select.innerHTML = '<option value="">— Seleccione —</option>';
                            data.forEach(function (disc) {
                                const opt = document.createElement('option');
                                opt.value = disc.id;
                                opt.textContent = disc.nome;
                                if (selectedId && String(disc.id) === String(selectedId)) {
                                    opt.selected = true;
                                }
                                select.appendChild(opt);
                            });
                            select.disabled = false;
                        })
                        .catch(function () {
                            select.innerHTML = '<option value="">Erro ao carregar</option>';
                        });
                });
            }

            document.querySelectorAll('.js-carregar-disciplinas').forEach(function (select) {
                select.addEventListener('change', function () {
                    preencherDisciplinas(this.value, null);
                });
            });

            if (oldTurmaId) {
                preencherDisciplinas(oldTurmaId, oldDisciplinaId);
            }
        });
    </script>
    @endpush
</x-app-layout>
