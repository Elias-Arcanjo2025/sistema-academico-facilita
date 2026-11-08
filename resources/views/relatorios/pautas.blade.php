<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-file-earmark-spreadsheet me-2 text-primary"></i>Exportar Pautas
            </h2>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-file-earmark-text" variant="primary" title="Relatório de Pautas" id="tip-relatorios-pautas">
                Gere relatórios de pautas por turma ou disciplina. Seleccione os filtros desejados e exporte para PDF. As pautas incluem todas as notas lançadas no período seleccionado.
            </x-tip-card>

            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <!-- Filtros -->
                    <div class="card shadow-soft fade-in mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Seleccionar Turma</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="{{ route('relatorios.pautas') }}" id="filterForm">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="ano_lectivo" class="form-label">
                                            <i class="bi bi-calendar-event me-1"></i>Ano Lectivo
                                        </label>
                                        <select class="form-select" id="ano_lectivo" name="ano_lectivo" onchange="this.form.submit()">
                                            @foreach($anosLectivos as $ano)
                                                <option value="{{ $ano }}" {{ $anoLectivo == $ano ? 'selected' : '' }}>
                                                    {{ $ano }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-8">
                                        <label for="turma_id" class="form-label">
                                            <i class="bi bi-people me-1"></i>Turma
                                        </label>
                                        <select class="form-select" id="turma_id" name="turma_id" onchange="this.form.submit()">
                                            <option value="">Selecione uma turma...</option>
                                            @foreach($turmas as $turma)
                                                <option value="{{ $turma->id }}" {{ $turmaId == $turma->id ? 'selected' : '' }}>
                                                    {{ $turma->nome }} - {{ $turma->curso->nome }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    @if($turmaId)
                        <!-- Pauta Final da Turma -->
                        <div class="card shadow-soft fade-in mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-table me-2"></i>Pauta Final da Turma</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">
                                    Exportar pauta completa com todas as disciplinas e notas finais de todos os alunos.
                                </p>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('export.pauta-final-turma.pdf', ['turma_id' => $turmaId, 'ano_lectivo' => $anoLectivo]) }}"
                                       class="btn btn-danger" target="_blank">
                                        <i class="bi bi-file-earmark-pdf me-1"></i>Exportar PDF
                                    </a>
                                    <a href="{{ route('export.pauta-final-turma.excel', ['turma_id' => $turmaId, 'ano_lectivo' => $anoLectivo]) }}"
                                       class="btn btn-success">
                                        <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Pauta Final por Disciplina -->
                        @if($disciplinas->count() > 0)
                            <div class="card shadow-soft fade-in mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="bi bi-bookmark me-2"></i>Pauta Final por Disciplina</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Disciplina</th>
                                                    <th class="text-end" style="width: 200px;">Exportar</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($disciplinas as $disciplina)
                                                    <tr class="align-middle">
                                                        <td>
                                                            <span class="badge bg-info me-2">{{ $disciplina->codigo }}</span>
                                                            <strong>{{ $disciplina->nome }}</strong>
                                                        </td>
                                                        <td class="text-end">
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <a href="{{ route('export.pauta-final-disciplina.pdf', ['turma_id' => $turmaId, 'disciplina_id' => $disciplina->id, 'ano_lectivo' => $anoLectivo]) }}"
                                                                   class="btn btn-outline-danger" target="_blank">
                                                                    <i class="bi bi-file-pdf"></i> PDF
                                                                </a>
                                                                <a href="{{ route('export.pauta-final-disciplina.excel', ['turma_id' => $turmaId, 'disciplina_id' => $disciplina->id, 'ano_lectivo' => $anoLectivo]) }}"
                                                                   class="btn btn-outline-success">
                                                                    <i class="bi bi-file-excel"></i> Excel
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Mini-Pautas por Trimestre / Anual -->
                            <div class="card shadow-soft fade-in">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="bi bi-file-text me-2"></i>Mini-Pautas (trimestral e anual)</h5>
                                    <small class="text-muted">A coluna Anual exporta os 3 trimestres num único PDF/Excel.</small>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Disciplina</th>
                                                    <th class="text-center">1. Trimestre</th>
                                                    <th class="text-center">2. Trimestre</th>
                                                    <th class="text-center">3. Trimestre</th>
                                                    <th class="text-center">Anual</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($disciplinas as $disciplina)
                                                    <tr class="align-middle">
                                                        <td>
                                                            <strong>{{ $disciplina->nome }}</strong>
                                                        </td>
                                                        @for($trim = 1; $trim <= 3; $trim++)
                                                            <td class="text-center">
                                                                <div class="btn-group btn-group-sm" role="group">
                                                                    <a href="{{ route('export.mini-pauta.pdf', ['turma_id' => $turmaId, 'disciplina_id' => $disciplina->id, 'trimestre' => $trim, 'ano_lectivo' => $anoLectivo]) }}"
                                                                       class="btn btn-outline-danger btn-sm" target="_blank" title="PDF">
                                                                        <i class="bi bi-file-pdf"></i>
                                                                    </a>
                                                                    <a href="{{ route('export.mini-pauta.excel', ['turma_id' => $turmaId, 'disciplina_id' => $disciplina->id, 'trimestre' => $trim, 'ano_lectivo' => $anoLectivo]) }}"
                                                                       class="btn btn-outline-success btn-sm" title="Excel">
                                                                        <i class="bi bi-file-excel"></i>
                                                                    </a>
                                                                </div>
                                                            </td>
                                                        @endfor
                                                        <td class="text-center">
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <a href="{{ route('export.mini-pauta-anual.pdf', ['turma_id' => $turmaId, 'disciplina_id' => $disciplina->id, 'ano_lectivo' => $anoLectivo]) }}"
                                                                   class="btn btn-danger btn-sm" target="_blank" title="PDF anual (1.º+2.º+3.º)">
                                                                    <i class="bi bi-file-pdf"></i>
                                                                </a>
                                                                <a href="{{ route('export.mini-pauta-anual.excel', ['turma_id' => $turmaId, 'disciplina_id' => $disciplina->id, 'ano_lectivo' => $anoLectivo]) }}"
                                                                   class="btn btn-success btn-sm" title="Excel anual (3 folhas)">
                                                                    <i class="bi bi-file-excel"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>O curso desta turma não tem disciplinas associadas.
                            </div>
                        @endif
                    @else
                        <div class="alert alert-secondary">
                            <i class="bi bi-arrow-up me-2"></i>Selecione uma turma para ver as opções de exportação.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
