<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Pautas</h2>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-file-earmark-text" variant="success" title="Pautas" id="tip-pautas-index">
                As pautas reúnem as notas finais dos alunos. Pode gerar pautas por disciplina, por turma ou a pauta final completa. Exporte para PDF para impressão oficial.
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
                :form-action="route('pautas.index')"
                :limpar-url="route('pautas.index')"
                botao-texto="Visualizar"
                instrucao="Seleccione ano lectivo, turma, disciplina e trimestre para aceder às pautas."
                :resumo="($turmaId && $disciplinaId && $trimestre && ($turma ?? null) && ($disciplina ?? null))
                    ? '<strong>'.e($turma->nome).'</strong> · '.e($disciplina->nome).' · '.e($trimestre).'.º trim. · '.e($anoLectivo)
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
                        <option value="1" {{ $trimestre == 1 ? 'selected' : '' }}>1.º Trimestre</option>
                        <option value="2" {{ $trimestre == 2 ? 'selected' : '' }}>2.º Trimestre</option>
                        <option value="3" {{ $trimestre == 3 ? 'selected' : '' }}>3.º Trimestre</option>
                    </select>
                </div>
            </x-filtros-pauta-card>

            @if($turmaId && (int) $trimestre !== 3)
                <div class="alert alert-info d-print-none mb-3">
                    <i class="bi bi-info-circle"></i>
                    Seleccione o <strong>3.º trimestre</strong> nos filtros para aceder a CAs anteriores, recuperação anual, recurso, melhoria e extraordinário.
                </div>
            @endif

            <!-- Links Rapidos -->
            @if($turmaId)
                <div class="row mb-4 g-3">
                    <div class="col-md-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h6 class="card-title">Mini-Pauta Trimestral</h6>
                                <p class="text-muted small">Ver notas MAC, PP, PT e MT de um trimestre</p>
                                @if($turmaId && $disciplinaId && $trimestre)
                                    <a href="{{ route('pautas.mini-pauta', ['turma_id' => $turmaId, 'disciplina_id' => $disciplinaId, 'trimestre' => $trimestre, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-table"></i> Abrir
                                    </a>
                                @else
                                    <button class="btn btn-sm btn-outline-secondary" disabled>Selecione os filtros</button>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h6 class="card-title">Pauta Final - Disciplina</h6>
                                <p class="text-muted small">Ver MT1, MT2, MT3 e nota final</p>
                                @if($turmaId && $disciplinaId)
                                    <a href="{{ route('pautas.final-disciplina', ['turma_id' => $turmaId, 'disciplina_id' => $disciplinaId, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-file-earmark-text"></i> Abrir
                                    </a>
                                @else
                                    <button class="btn btn-sm btn-outline-secondary" disabled>Selecione turma e disciplina</button>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100 border-success">
                            <div class="card-body text-center">
                                <h6 class="card-title text-success fw-bold">Pauta de Aproveitamento Trimestral</h6>
                                <p class="text-muted small">Layout oficial em Excel — 1 trimestre, todas as disciplinas + APTO/NÃO APTO</p>
                                @if($turmaId && $trimestre)
                                    <a href="{{ route('pautas.trimestral-completa', ['turma_id' => $turmaId, 'trimestre' => $trimestre, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-sm btn-success">
                                        <i class="bi bi-file-earmark-excel"></i> Abrir
                                    </a>
                                @else
                                    <button class="btn btn-sm btn-outline-secondary" disabled>Selecione turma e trimestre</button>
                                @endif
                            </div>
                        </div>
                    </div>
                    @if(in_array($classeNumero, [11, 12], true) && (int) $trimestre === 3)
                    <div class="col-md-3">
                        <div class="card h-100 border-success">
                            <div class="card-body text-center">
                                <h6 class="card-title text-success fw-bold">
                                    <i class="bi bi-calendar-check me-1"></i>CAs Anteriores
                                </h6>
                                <p class="text-muted small">
                                    Lance CAs em falta: <strong>CA-10ª</strong> na 11.ª; <strong>CA-10ª e CA-11ª</strong> na 12.ª. A CFD calcula-se na pauta.
                                </p>
                                <a href="{{ route('cfds-anteriores.index', ['turma_id' => $turmaId, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-sm btn-success">
                                    <i class="bi bi-pencil-square"></i> Gerir
                                </a>
                            </div>
                        </div>
                    </div>
                    @endif
                    @if(in_array($classeNumero, [10, 11], true) && (int) $trimestre === 3)
                    <div class="col-md-3">
                        <div class="card h-100 border-primary">
                            <div class="card-body text-center">
                                <h6 class="card-title text-primary fw-bold">
                                    <i class="bi bi-bandaid me-1"></i>Exame de Recuperação
                                </h6>
                                <p class="text-muted small">
                                    10.ª/11.ª — nota anual que substitui a CFD em disciplinas com deficiência (TRANS. C/DEF.).
                                </p>
                                <a href="{{ route('recuperacoes.index', ['turma_id' => $turmaId, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-pencil-square"></i> Gerir
                                </a>
                            </div>
                        </div>
                    </div>
                    @endif
                    @if(in_array($classeNumero, [10, 11, 12], true) && (int) $trimestre === 3)
                    <div class="col-md-3">
                        <div class="card h-100 border-info">
                            <div class="card-body text-center">
                                <h6 class="card-title text-info fw-bold">
                                    <i class="bi bi-list-check me-1"></i>Lista de Elegíveis
                                </h6>
                                <p class="text-muted small">
                                    @if($classeNumero === 12)
                                        Exame de recurso (12.ª).
                                    @elseif(in_array($classeNumero, [10, 11], true))
                                        Recuperação anual (10.ª/11.ª).
                                    @else
                                        Alunos sujeitos a exames.
                                    @endif
                                </p>
                                <a href="{{ route('exames.elegibilidade', ['turma_id' => $turmaId, 'ano_lectivo' => $anoLectivo, 'trimestre' => 3, 'tipo' => 'todos']) }}" class="btn btn-sm btn-info text-white">
                                    <i class="bi bi-search"></i> Ver lista
                                </a>
                            </div>
                        </div>
                    </div>
                    @endif
                    @if($classeNumero === 12 && (int) $trimestre === 3)
                    <div class="col-md-3">
                        <div class="card h-100 border-danger">
                            <div class="card-body text-center">
                                <h6 class="card-title text-danger fw-bold">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Exame de Recurso
                                </h6>
                                <p class="text-muted small">
                                    12.ª classe — a nota substitui a CFD (Art.º 31.º).
                                </p>
                                <a href="{{ route('recursos.index', ['turma_id' => $turmaId, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-sm btn-danger">
                                    <i class="bi bi-pencil-square"></i> Gerir
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100 border-success">
                            <div class="card-body text-center">
                                <h6 class="card-title text-success fw-bold">
                                    <i class="bi bi-graph-up-arrow me-1"></i>Exame de Melhoria
                                </h6>
                                <p class="text-muted small">
                                    12.ª classe — nota final = MAX(CFD, melhoria) — Art.º 34.º.
                                </p>
                                <a href="{{ route('melhorias.index', ['turma_id' => $turmaId, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-sm btn-success">
                                    <i class="bi bi-pencil-square"></i> Gerir
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100 border-warning">
                            <div class="card-body text-center">
                                <h6 class="card-title text-warning fw-bold">
                                    <i class="bi bi-clipboard-check me-1"></i>Exame Extraordinário
                                </h6>
                                <p class="text-muted small">
                                    12.ª classe — finalistas em condições especiais (Art.º 32.º).
                                </p>
                                <a href="{{ route('extraordinarios.index', ['turma_id' => $turmaId, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-sm btn-warning text-dark">
                                    <i class="bi bi-pencil-square"></i> Gerir
                                </a>
                            </div>
                        </div>
                    </div>
                    @endif
                    <div class="col-md-3">
                        <div class="card h-100 border-primary">
                            <div class="card-body text-center">
                                <h6 class="card-title text-primary">Pauta Final Completa</h6>
                                <p class="text-muted small">Ver todas as disciplinas e trimestres</p>
                                @if($turmaId)
                                    <a href="{{ route('pautas.final-completa-detalhada', ['turma_id' => $turmaId, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-sm btn-primary">
                                        <i class="bi bi-grid-3x3-gap"></i> Abrir
                                    </a>
                                @else
                                    <button class="btn btn-sm btn-outline-secondary" disabled>Selecione uma turma</button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Mini-Pauta -->
            @if($miniPauta && $turma && $disciplina)
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Mini-Pauta: {{ $turma->nome }} - {{ $disciplina->nome }}</h5>
                            <small class="text-muted">{{ $trimestre }}.o Trimestre - Ano Lectivo {{ $anoLectivo }}</small>
                        </div>
                        <div class="btn-group">
                            <span class="badge bg-info d-flex align-items-center">
                                <i class="bi bi-info-circle me-1"></i> MTs calculadas automaticamente
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($estatisticas)
                            <div class="row mb-4">
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <h4>{{ $estatisticas['total_alunos'] }}</h4>
                                        <small class="text-muted">Alunos</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <h4 class="text-success">{{ $estatisticas['aprovados'] }}</h4>
                                        <small class="text-muted">Positivas</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <h4 class="text-danger">{{ $estatisticas['reprovados'] }}</h4>
                                        <small class="text-muted">Negativas</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <h4>{{ $estatisticas['media_turma'] ?? '-' }}</h4>
                                        <small class="text-muted">Media Turma</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <h4>{{ $estatisticas['nota_maxima'] ?? '-' }}</h4>
                                        <small class="text-muted">Nota Max.</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <h4>{{ $estatisticas['nota_minima'] ?? '-' }}</h4>
                                        <small class="text-muted">Nota Min.</small>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-hover table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-nowrap" style="width: 60px;">N.º</th>
                                        <th class="text-nowrap">Nome do Aluno</th>
                                        <th class="text-nowrap text-center" style="width: 80px;">MAC</th>
                                        <th class="text-nowrap text-center" style="width: 80px;">PP</th>
                                        <th class="text-nowrap text-center" style="width: 80px;">PT</th>
                                        <th class="text-nowrap text-center" style="width: 80px;">MT</th>
                                        <th class="text-nowrap text-center" style="width: 100px;">Situação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($miniPauta as $linha)
                                        <tr>
                                            <td class="text-nowrap">{{ $linha['numero_ordem'] }}</td>
                                            <td class="text-nowrap">{{ $linha['nome'] }}</td>
                                            <td class="text-nowrap text-center">
                                                @if($linha['mac'] !== null)
                                                    <span class="{{ $linha['mac'] >= 10 ? 'text-success' : 'text-danger' }}">
                                                        {{ number_format($linha['mac'], 0) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-nowrap text-center">
                                                @if($linha['pp'] !== null)
                                                    <span class="{{ $linha['pp'] >= 10 ? 'text-success' : 'text-danger' }}">
                                                        {{ number_format($linha['pp'], 0) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-nowrap text-center">
                                                @if($linha['pt'] !== null)
                                                    <span class="{{ $linha['pt'] >= 10 ? 'text-success' : 'text-danger' }}">
                                                        {{ number_format($linha['pt'], 0) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-nowrap text-center fw-bold">
                                                @if($linha['mt'] !== null)
                                                    <span class="{{ $linha['mt'] >= 10 ? 'text-success' : 'text-danger' }}">
                                                        {{ number_format($linha['mt'], 0) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-nowrap text-center">
                                                @if($linha['situacao'] === 'aprovado')
                                                    <span class="badge bg-success">Positiva</span>
                                                @elseif($linha['situacao'] === 'reprovado')
                                                    <span class="badge bg-danger">Negativa</span>
                                                @else
                                                    <span class="badge bg-secondary">Pendente</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @elseif(!$turmaId || !$disciplinaId || !$trimestre)
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Seleccione ano lectivo, turma, disciplina e trimestre para aceder às pautas e mini-pautas.
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

            fetch(`{{ route('pautas.disciplinas') }}?turma_id=${turmaId}`)
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
