<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-list-check me-2 text-primary"></i>Lista de Alunos
            </h2>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <!-- Filtro de Ano Lectivo -->
                    <div class="card shadow-soft fade-in mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filtrar por Ano Lectivo</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="{{ route('relatorios.lista-alunos') }}">
                                <div class="row align-items-end">
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
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Lista de Turmas -->
                    <div class="card shadow-soft fade-in">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-people me-2"></i>Turmas Disponiveis</h5>
                            <span class="badge bg-primary">{{ $turmas->count() }} turmas</span>
                        </div>
                        <div class="card-body p-0">
                            @if($turmas->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Turma</th>
                                                <th>Curso</th>
                                                <th class="text-center">Alunos</th>
                                                <th class="text-end" style="width: 200px;">Exportar</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($turmas as $turma)
                                                <tr class="align-middle">
                                                    <td>
                                                        <strong>{{ $turma->nome }}</strong>
                                                        <br><small class="text-muted">{{ $turma->codigo }}</small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info">{{ $turma->curso->nome }}</span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-purple">
                                                            <i class="bi bi-person me-1"></i>{{ $turma->alunos_count }}
                                                        </span>
                                                    </td>
                                                    <td class="text-end">
                                                        @if($turma->alunos_count > 0)
                                                            <div class="btn-group" role="group">
                                                                <a href="{{ route('export.lista-alunos.pdf', ['turma_id' => $turma->id]) }}"
                                                                   class="btn btn-sm btn-danger" target="_blank">
                                                                    <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                                                                </a>
                                                                <a href="{{ route('export.lista-alunos.excel', ['turma_id' => $turma->id]) }}"
                                                                   class="btn btn-sm btn-success">
                                                                    <i class="bi bi-file-earmark-excel me-1"></i>Excel
                                                                </a>
                                                            </div>
                                                        @else
                                                            <span class="text-muted">Sem alunos</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-5">
                                    <i class="bi bi-inbox display-4 text-muted d-block mb-3"></i>
                                    <p class="text-muted mb-0">Nenhuma turma encontrada para este ano lectivo.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
