<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Notas</h2>
            @can('create', App\Models\Nota::class)
                <a href="{{ route('notas.lancar') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Lançar Notas
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-list-check" variant="success" title="Consulta de Notas" id="tip-notas-index">
                Consulte as notas lançadas por turma e disciplina. Utilize os filtros para seleccionar o ano lectivo, trimestre, turma e disciplina desejados. Pode exportar os dados para Excel ou PDF.
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

            @if($modoConsulta ?? false)
                <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Modo Consulta:</strong> Ano lectivo anterior seleccionado — a visualizar notas desse período.
                    <a href="{{ route('notas.index') }}" class="alert-link">Voltar ao ano actual</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <!-- Filtros -->
            <x-filtros-pauta-card
                titulo="Filtros de consulta"
                :form-action="route('notas.index')"
                :limpar-url="route('notas.index')"
                botao-texto="Consultar"
                instrucao="Seleccione ano lectivo, turma, disciplina e trimestre para ver as notas."
                :resumo="($turmaId && $disciplinaId && ($turma ?? null) && ($disciplina ?? null))
                    ? '<strong>'.e($turma->nome).'</strong> · '.e($disciplina->nome).($trimestre ? ' · '.e($trimestre).'.º trim.' : '').' · '.e($anosLectivos[$anoLectivoId] ?? '')
                    : null"
            >
                <div class="col-sm-6 col-lg-3">
                    <x-ano-lectivo-selector
                        :anoLectivoId="$anoLectivoId"
                        :anosLectivos="$anosLectivos"
                        size="sm"
                        label="Ano Lectivo"
                        class="mb-0"
                    />
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="turma_id" class="form-label"><i class="bi bi-people me-1"></i>Turma</label>
                    <select class="form-select form-select-sm" id="turma_id" name="turma_id" onchange="document.getElementById('disciplina_id').value=''; this.form.submit();">
                        <option value="">— Seleccione —</option>
                        @foreach($turmas as $turma)
                            <option value="{{ $turma->id }}" {{ $turmaId == $turma->id ? 'selected' : '' }}>
                                {{ $turma->nome }} · {{ $turma->curso->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="disciplina_id" class="form-label"><i class="bi bi-journal-text me-1"></i>Disciplina</label>
                    <select class="form-select form-select-sm" id="disciplina_id" name="disciplina_id"
                            onchange="this.form.submit()" {{ !$turmaId ? 'disabled' : '' }}>
                        <option value="">{{ $turmaId ? '— Seleccione —' : 'Seleccione a turma primeiro' }}</option>
                        @foreach($disciplinas as $disciplina)
                            <option value="{{ $disciplina->id }}" {{ $disciplinaId == $disciplina->id ? 'selected' : '' }}>
                                {{ $disciplina->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="trimestre" class="form-label"><i class="bi bi-calendar-week me-1"></i>Trimestre</label>
                    <select class="form-select form-select-sm" id="trimestre" name="trimestre" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        <option value="1" {{ $trimestre == 1 ? 'selected' : '' }}>1.º Trimestre</option>
                        <option value="2" {{ $trimestre == 2 ? 'selected' : '' }}>2.º Trimestre</option>
                        <option value="3" {{ $trimestre == 3 ? 'selected' : '' }}>3.º Trimestre</option>
                    </select>
                </div>
            </x-filtros-pauta-card>

            @if($dadosAgrupados && $dadosAgrupados->count() > 0)
                <!-- Estatisticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center py-2">
                                <h4 class="mb-0">{{ $dadosAgrupados->count() }}</h4>
                                <small class="text-muted">Total Alunos</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success bg-opacity-10">
                            <div class="card-body text-center py-2">
                                <h4 class="mb-0 text-success">{{ $dadosAgrupados->filter(fn($l) => $l['mac'] !== null && $l['pp'] !== null && $l['pt'] !== null)->count() }}</h4>
                                <small class="text-muted">Notas Completas</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary bg-opacity-10">
                            <div class="card-body text-center py-2">
                                @php
                                    $notasMT = $dadosAgrupados->pluck('mt')->filter();
                                    $mediaTurma = $notasMT->count() > 0 ? round($notasMT->avg(), 1) : null;
                                @endphp
                                <h4 class="mb-0">{{ $mediaTurma ?? '-' }}</h4>
                                <small class="text-muted">Média Turma</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center py-2">
                                @php
                                    $notaMax = $notasMT->max();
                                    $notaMin = $notasMT->min();
                                @endphp
                                <h4 class="mb-0">{{ $notaMax ? round($notaMax, 1) : '-' }} / {{ $notaMin ? round($notaMin, 1) : '-' }}</h4>
                                <small class="text-muted">Máxima / Mínima</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabela de Notas -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">
                                <i class="bi bi-table"></i> Notas da Turma
                            </h5>
                            <small class="text-muted">
                                {{ $turma->nome }} - {{ $disciplina->nome }} | {{ $trimestre }}.º Trimestre | {{ $anosLectivos[$anoLectivoId] ?? '' }}
                            </small>
                        </div>
                        @can('create', App\Models\Nota::class)
                            <a href="{{ route('notas.lancar', ['turma_id' => $turmaId, 'disciplina_id' => $disciplinaId, 'trimestre' => $trimestre]) }}"
                               class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-circle"></i> Lançar Notas
                            </a>
                        @endcan
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-nowrap" style="width: 50px;">N.º</th>
                                        <th class="text-nowrap">Nome do Aluno</th>
                                        <th class="text-nowrap text-center" style="width: 80px;">MAC</th>
                                        <th class="text-nowrap text-center" style="width: 80px;">PP</th>
                                        <th class="text-nowrap text-center" style="width: 80px;">PT</th>
                                        <th class="text-nowrap text-center" style="width: 80px;">MT</th>
                                        <th class="text-nowrap text-center" style="width: 120px;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($dadosAgrupados as $linha)
                                        <tr>
                                            <td class="text-nowrap text-center">{{ $linha['numero_ordem'] }}</td>
                                            <td class="text-nowrap">{{ $linha['nome'] }}</td>

                                            <!-- MAC -->
                                            <td class="text-nowrap text-center">
                                                @if($linha['mac'] !== null)
                                                    <span class="badge {{ $linha['mac'] >= 10 ? 'bg-success' : 'bg-danger' }}">
                                                        {{ number_format($linha['mac'], 0) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>

                                            <!-- PP -->
                                            <td class="text-nowrap text-center">
                                                @if($linha['pp'] !== null)
                                                    <span class="badge {{ $linha['pp'] >= 10 ? 'bg-success' : 'bg-danger' }}">
                                                        {{ number_format($linha['pp'], 0) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>

                                            <!-- PT -->
                                            <td class="text-nowrap text-center">
                                                @if($linha['pt'] !== null)
                                                    <span class="badge {{ $linha['pt'] >= 10 ? 'bg-success' : 'bg-danger' }}">
                                                        {{ number_format($linha['pt'], 0) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>

                                            <!-- MT -->
                                            <td class="text-nowrap text-center">
                                                @if($linha['mt'] !== null)
                                                    <span class="badge {{ $linha['mt'] >= 10 ? 'bg-success' : 'bg-danger' }} fs-6">
                                                        {{ number_format($linha['mt'], 0) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>

                                            <!-- Ações -->
                                            <td class="text-nowrap text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    @if($linha['nota_mac'])
                                                        @can('update', $linha['nota_mac'])
                                                            <a href="{{ route('notas.edit', $linha['nota_mac']->id) }}"
                                                               class="btn btn-outline-primary"
                                                               title="Editar MAC">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        @endcan
                                                    @endif
                                                    @if($linha['nota_pp'])
                                                        @can('update', $linha['nota_pp'])
                                                            <a href="{{ route('notas.edit', $linha['nota_pp']->id) }}"
                                                               class="btn btn-outline-primary"
                                                               title="Editar PP">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        @endcan
                                                    @endif
                                                    @if($linha['nota_pt'])
                                                        @can('update', $linha['nota_pt'])
                                                            <a href="{{ route('notas.edit', $linha['nota_pt']->id) }}"
                                                               class="btn btn-outline-primary"
                                                               title="Editar PT">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        @endcan
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @elseif($turmaId && $disciplinaId && $trimestre)
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Nenhuma nota lançada para esta turma, disciplina e trimestre.
                </div>
            @else
                <div class="alert alert-secondary">
                    <i class="bi bi-arrow-up"></i>
                    Selecione uma turma, disciplina e trimestre para visualizar as notas.
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
