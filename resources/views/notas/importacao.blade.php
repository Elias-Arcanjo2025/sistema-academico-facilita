<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">
                <i class="bi bi-file-earmark-excel me-2 text-success"></i>Importação de Notas via Excel
            </h2>
            <a href="{{ route('notas.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container-fluid">
            <x-tip-card icon="bi-file-earmark-excel" variant="info" title="Como funciona" id="tip-notas-importacao">
                Descarregue o modelo Excel da turma, preencha as notas (MAC, PP, PT) e carregue o ficheiro. O sistema valida os dados antes de gravar. Não altere a estrutura do modelo nem os números de processo dos alunos.
            </x-tip-card>

            @if($turmas->isEmpty())
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Não tem turmas disponíveis para importação. Esta funcionalidade está disponível para directores de turma e coordenadores de curso.
                </div>
            @else
            <div class="row">
                <!-- Coluna 1: Descarregar modelo -->
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
                                Seleccione a turma e o trimestre para gerar um ficheiro com todos os alunos e as colunas de notas por disciplina.
                            </p>

                            <form action="{{ route('notas.importacao.template') }}" method="GET" id="downloadForm">
                                <div class="mb-3">
                                    <label for="download_turma_id" class="form-label">Turma <span class="text-danger">*</span></label>
                                    <select class="form-select js-sync-turma" id="download_turma_id" name="turma_id" required>
                                        <option value="">Seleccione uma turma</option>
                                        @foreach($turmas as $turma)
                                            <option value="{{ $turma->id }}">
                                                {{ $turma->nome }} — {{ $turma->curso->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="download_trimestre" class="form-label">
                                        Trimestre <span class="text-danger">*</span>
                                        @if(isset($trimestreActual))
                                            <span class="badge bg-info ms-1">Actual: {{ $trimestreActual }}º</span>
                                        @endif
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
                                    <i class="bi bi-download"></i> Descarregar modelo
                                </button>
                            </form>

                            <div class="mt-3 p-3 rounded bg-light small">
                                <strong class="d-block mb-2"><i class="bi bi-list-check me-1"></i>O modelo inclui:</strong>
                                <ul class="mb-0 ps-3">
                                    <li>Lista completa de alunos da turma</li>
                                    <li>Colunas MAC, PP e PT por disciplina</li>
                                    <li>Notas já lançadas (se existirem)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Coluna 2: Importar ficheiro -->
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
                                Após preencher o modelo, seleccione a mesma turma e trimestre e carregue o ficheiro Excel.
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

                            @if(session('comparador_importacao'))
                                @php $cmp = session('comparador_importacao'); @endphp
                                <div class="alert alert-warning">
                                    <strong><i class="bi bi-arrow-left-right me-1"></i>Comparador Facilita vs Excel:</strong>
                                    {{ $cmp['divergencias'] ?? 0 }} valor(es) no ficheiro diferem das notas actuais no sistema.
                                    @if(!empty($cmp['linhas']))
                                        <ul class="mb-0 mt-2 small" style="max-height: 160px; overflow-y: auto;">
                                            @foreach($cmp['linhas'] as $linha)
                                                <li>{{ $linha['aluno'] }} · {{ $linha['disciplina'] }} {{ $linha['tipo'] }}: Facilita {{ $linha['facilita'] }} → Excel {{ $linha['excel'] }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endif

                            <form action="{{ route('notas.importacao.store') }}" method="POST" enctype="multipart/form-data">
                                @csrf

                                <div class="mb-3">
                                    <label for="upload_turma_id" class="form-label">Turma <span class="text-danger">*</span></label>
                                    <select class="form-select js-sync-turma @error('turma_id') is-invalid @enderror" id="upload_turma_id" name="turma_id" required>
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
                                    <label for="upload_trimestre" class="form-label">
                                        Trimestre <span class="text-danger">*</span>
                                        @if(isset($trimestreActual))
                                            <span class="badge bg-info ms-1">Actual: {{ $trimestreActual }}º</span>
                                        @endif
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
                                    @if(!auth()->user()->podeIgnorarTrimestreFechado())
                                        <div class="form-text">Apenas o trimestre actual está disponível para importação.</div>
                                    @endif
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
                                    <li>Não modifique a estrutura do modelo</li>
                                    <li>Notas existentes serão actualizadas</li>
                                    <li>Células vazias serão ignoradas</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Instruções -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light border-0">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Instruções de utilização</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <h6 class="text-primary mb-2"><i class="bi bi-download me-1"></i> Descarregar modelo</h6>
                            <ul class="small text-muted mb-0">
                                <li>Seleccione turma e trimestre</li>
                                <li>Clique em «Descarregar modelo»</li>
                                <li>Abra o ficheiro no Excel ou LibreOffice</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-primary mb-2"><i class="bi bi-pencil-square me-1"></i> Preencher notas</h6>
                            <ul class="small text-muted mb-0">
                                <li>Preencha MAC, PP e PT (0 a 20)</li>
                                <li>Use vírgula ou ponto para decimais</li>
                                <li>Deixe em branco o que não quiser lançar</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-primary mb-2"><i class="bi bi-upload me-1"></i> Importar</h6>
                            <ul class="small text-muted mb-0">
                                <li>Guarde o ficheiro Excel</li>
                                <li>Seleccione a mesma turma e trimestre</li>
                                <li>Carregue o ficheiro e aguarde o processamento</li>
                            </ul>
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
            const trimestreSelects = document.querySelectorAll('.js-sync-trimestre');

            function syncSelects(changed, others) {
                changed.forEach(function (select) {
                    select.addEventListener('change', function () {
                        others.forEach(function (other) {
                            if (other !== select) {
                                other.value = select.value;
                            }
                        });
                    });
                });
            }

            syncSelects([...turmaSelects], [...turmaSelects]);
            syncSelects([...trimestreSelects], [...trimestreSelects]);
        });
    </script>
    @endpush
</x-app-layout>
