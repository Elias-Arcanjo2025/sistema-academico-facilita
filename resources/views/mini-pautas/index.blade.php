<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Mini-Pautas</h2>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-file-text" variant="success" title="Mini-Pautas" id="tip-minipautas-index">
                As mini-pautas mostram as notas e médias por trimestre, alinhadas ao boletim. Escolha um trimestre ou a opção <strong>Anual</strong> para extrair os três trimestres lado a lado num único PDF (paisagem).
            </x-tip-card>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <!-- Filtros -->
            @php $anoLectivoActualId = \App\Models\Configuracao::getAnoLectivoActualId(); @endphp
            <x-filtros-pauta-card
                titulo="Filtros de consulta"
                :form-action="route('mini-pautas.index')"
                :limpar-url="route('mini-pautas.index')"
                botao-texto="Visualizar"
                instrucao="Seleccione ano lectivo, turma, disciplina e trimestre (ou Anual) para visualizar a mini-pauta."
                :resumo="($turmaId && $disciplinaId && $trimestre && ($turma ?? null) && ($disciplina ?? null))
                    ? '<strong>'.e($turma->nome).'</strong> · '.e($disciplina->nome).' · '.($trimestre === 'anual' ? 'Anual (1.º–3.º)' : e($trimestre).'.º trim.').' · '.e($anoLectivo)
                    : null"
            >
                <div class="col-sm-6 col-lg-3">
                    <label for="ano_lectivo" class="form-label">
                        <i class="bi bi-calendar-range me-1"></i>Ano Lectivo
                    </label>
                    <select class="form-select form-select-sm" id="ano_lectivo" name="ano_lectivo" onchange="this.form.submit()">
                        @foreach($anosLectivos as $id => $nome)
                            <option value="{{ $nome }}" {{ $anoLectivo == $nome ? 'selected' : '' }}>
                                {{ $nome }}@if($id == $anoLectivoActualId) (Actual)@endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="turma_id" class="form-label">
                        <i class="bi bi-people me-1"></i>Turma
                    </label>
                    <select class="form-select form-select-sm" id="turma_id" name="turma_id"
                            onchange="document.getElementById('disciplina_id').value=''; carregarDisciplinas(this.value);">
                        <option value="">— Seleccione —</option>
                        @foreach($turmas as $t)
                            <option value="{{ $t->id }}" {{ $turmaId == $t->id ? 'selected' : '' }}>
                                {{ $t->nome }} · {{ $t->curso->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="disciplina_id" class="form-label">
                        <i class="bi bi-journal-text me-1"></i>Disciplina
                    </label>
                    <select class="form-select form-select-sm" id="disciplina_id" name="disciplina_id"
                            {{ !$turmaId ? 'disabled' : '' }}>
                        <option value="">{{ $turmaId ? '— Seleccione —' : 'Seleccione a turma primeiro' }}</option>
                        @foreach($disciplinas as $d)
                            <option value="{{ $d->id }}" {{ $disciplinaId == $d->id ? 'selected' : '' }}>
                                {{ $d->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="trimestre" class="form-label">
                        <i class="bi bi-calendar-week me-1"></i>Trimestre
                    </label>
                    <select class="form-select form-select-sm" id="trimestre" name="trimestre">
                        <option value="">— Seleccione —</option>
                        <option value="1" {{ (string) $trimestre === '1' ? 'selected' : '' }}>1.º Trimestre</option>
                        <option value="2" {{ (string) $trimestre === '2' ? 'selected' : '' }}>2.º Trimestre</option>
                        <option value="3" {{ (string) $trimestre === '3' ? 'selected' : '' }}>3.º Trimestre</option>
                        <option value="anual" {{ $trimestre === 'anual' ? 'selected' : '' }}>Anual (1.º + 2.º + 3.º)</option>
                    </select>
                </div>
            </x-filtros-pauta-card>

            <!-- Link Rápido para Mini-Pauta -->
            @if($turmaId && $disciplinaId && $trimestre)
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                @if($ehAnual ?? false)
                                    <h6 class="card-title text-primary">Mini-Pauta Anual</h6>
                                    <p class="text-muted small mb-3">Os três trimestres num único documento</p>
                                    <div class="d-flex flex-wrap justify-content-center gap-2">
                                        <a href="{{ route('mini-pautas.show', ['turma_id' => $turmaId, 'disciplina_id' => $disciplinaId, 'trimestre' => 'anual', 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-primary">
                                            <i class="bi bi-table"></i> Abrir anual
                                        </a>
                                        <a href="{{ route('export.mini-pauta-anual.pdf', ['turma_id' => $turmaId, 'disciplina_id' => $disciplinaId, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-danger" target="_blank">
                                            <i class="bi bi-file-pdf"></i> PDF anual
                                        </a>
                                        <a href="{{ route('export.mini-pauta-anual.excel', ['turma_id' => $turmaId, 'disciplina_id' => $disciplinaId, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-success">
                                            <i class="bi bi-file-excel"></i> Excel anual
                                        </a>
                                    </div>
                                @else
                                    <h6 class="card-title text-primary">Mini-Pauta Trimestral</h6>
                                    <p class="text-muted small">Ver notas MAC, PP, PT e MT do {{ $trimestre }}.º Trimestre</p>
                                    <a href="{{ route('mini-pautas.show', ['turma_id' => $turmaId, 'disciplina_id' => $disciplinaId, 'trimestre' => $trimestre, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-primary">
                                        <i class="bi bi-table"></i> Abrir Mini-Pauta
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Mini-Pauta Anual (pré-visualização) -->
            @if(($ehAnual ?? false) && !empty($miniPautasAnuais) && $turma && $disciplina)
                <div class="card mb-4">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h5 class="mb-0">Mini-Pauta Anual: {{ $turma->nome }} — {{ $disciplina->nome }}</h5>
                            <small class="text-muted">1.º + 2.º + 3.º trimestre · Ano Lectivo {{ $anoLectivo }}</small>
                        </div>
                        <div class="btn-group">
                            <a href="{{ route('export.mini-pauta-anual.excel', ['turma_id' => $turmaId, 'disciplina_id' => $disciplinaId, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-sm btn-success">
                                <i class="bi bi-file-excel"></i> Excel
                            </a>
                            <a href="{{ route('export.mini-pauta-anual.pdf', ['turma_id' => $turmaId, 'disciplina_id' => $disciplinaId, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-sm btn-danger" target="_blank">
                                <i class="bi bi-file-pdf"></i> PDF
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @foreach($miniPautasAnuais as $t => $bloco)
                            <h6 class="mt-{{ $t === 1 ? '0' : '4' }} mb-2">{{ $t }}.º Trimestre</h6>
                            @include('mini-pautas.partials.tabela', [
                                'colunas' => $bloco['colunas'],
                                'miniPauta' => $bloco['miniPauta'],
                                'chaveResultado' => $bloco['chaveResultado'],
                            ])
                        @endforeach
                    </div>
                </div>
            @elseif($miniPauta && $turma && $disciplina)
                @include('mini-pautas.partials.importacao-excel', compact('turmaId', 'disciplinaId', 'trimestre', 'anoLectivo', 'turma'))

                <div class="card">
                    <div class="card-header">
                        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
                            <div>
                                <h5 class="mb-0">Mini-Pauta: {{ $turma->nome }} - {{ $disciplina->nome }}</h5>
                                <small class="text-muted">{{ $trimestre }}.o Trimestre - Ano Lectivo {{ $anoLectivo }}</small>
                                <span class="badge bg-info ms-0 ms-lg-2 mt-1 mt-lg-0">
                                    <i class="bi bi-info-circle me-1"></i> MTs calculadas automaticamente
                                </span>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <a href="{{ route('export.mini-pauta-anual.pdf', ['turma_id' => $turmaId, 'disciplina_id' => $disciplinaId, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-sm btn-outline-danger" target="_blank" title="PDF com os 3 trimestres">
                                    <i class="bi bi-file-earmark-pdf"></i> PDF anual
                                </a>
                                <x-recalcular-medias-disciplina
                                    :turma-id="$turmaId"
                                    :disciplina-id="$disciplinaId"
                                    :mostrar-mt1="true"
                                    :mostrar-mt2="(int) $trimestre >= 2"
                                    :mostrar-mft2="(int) $trimestre >= 2"
                                    :mostrar-mt3="(int) $trimestre >= 3"
                                    :mostrar-cf="(int) $trimestre >= 3"
                                    :mostrar-ca="(int) $trimestre >= 3"
                                    :rotulo-ca="\App\Support\BoletimColunas::rotuloCa((int) ($turma->classe->codigo ?? 12))"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($estatisticas)
                            <div class="row mb-4">
                                <div class="col-md-2 col-4">
                                    <div class="text-center">
                                        <h4>{{ $estatisticas['total_alunos'] }}</h4>
                                        <small class="text-muted">Alunos</small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-4">
                                    <div class="text-center">
                                        <h4 class="text-success">{{ $estatisticas['aprovados'] }}</h4>
                                        <small class="text-muted">Positivas</small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-4">
                                    <div class="text-center">
                                        <h4 class="text-danger">{{ $estatisticas['reprovados'] }}</h4>
                                        <small class="text-muted">Negativas</small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-4">
                                    <div class="text-center">
                                        <h4 class="text-warning-emphasis">{{ $estatisticas['desistentes'] ?? 0 }}</h4>
                                        <small class="text-muted">Desistentes</small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-4">
                                    <div class="text-center">
                                        <h4>{{ $estatisticas['media_turma'] ?? '-' }}</h4>
                                        <small class="text-muted">Media Turma</small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-4">
                                    <div class="text-center">
                                        <h4>{{ $estatisticas['nota_maxima'] ?? '-' }} / {{ $estatisticas['nota_minima'] ?? '-' }}</h4>
                                        <small class="text-muted">Max / Min</small>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @include('mini-pautas.partials.tabela', compact('colunas', 'miniPauta', 'chaveResultado'))
                    </div>
                </div>
            @elseif(!$turmaId || !$disciplinaId || !$trimestre)
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Selecione uma turma, disciplina e trimestre (ou Anual) para visualizar a mini-pauta.
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function carregarDisciplinas(turmaId) {
            const select = document.getElementById('disciplina_id');
            select.innerHTML = '<option value="">A carregar...</option>';
            select.disabled = true;

            if (!turmaId) {
                select.innerHTML = '<option value="">Seleccione a turma primeiro</option>';
                return;
            }

            fetch(`{{ route('mini-pautas.disciplinas') }}?turma_id=${turmaId}`)
                .then(response => response.json())
                .then(data => {
                    select.innerHTML = '<option value="">— Seleccione —</option>';
                    select.disabled = false;
                    data.forEach(disc => {
                        select.innerHTML += `<option value="${disc.id}">${disc.nome}</option>`;
                    });
                })
                .catch(error => {
                    select.innerHTML = '<option value="">Erro ao carregar</option>';
                    console.error('Erro:', error);
                });
        }
    </script>
    @endpush
</x-app-layout>
