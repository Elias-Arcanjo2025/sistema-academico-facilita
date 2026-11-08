<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-files me-2 text-primary"></i>Boletins em Lote
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
                            <form method="GET" action="{{ route('relatorios.boletins-lote') }}">
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
                            <h5 class="mb-0"><i class="bi bi-people me-2"></i>Turmas Disponíveis</h5>
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
                                                <th class="text-end" style="width: 220px;">Ações</th>
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
                                                            <form action="{{ route('export.boletins-em-lote') }}" method="POST" class="d-inline">
                                                                @csrf
                                                                <input type="hidden" name="turma_id" value="{{ $turma->id }}">
                                                                <input type="hidden" name="ano_lectivo" value="{{ $anoLectivo }}">
                                                                <button type="submit" class="btn btn-sm btn-primary">
                                                                    <i class="bi bi-download me-1"></i>Gerar ZIP
                                                                </button>
                                                            </form>
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

                    @if($estadoLote)
                        <div class="card shadow-soft fade-in mt-4 border-success">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-check-circle me-2"></i>Boletins Gerados</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-2">
                                    <strong>Turma:</strong> {{ $estadoLote['turma'] }}<br>
                                    <strong>Gerados:</strong> {{ $estadoLote['gerados'] }} boletins<br>
                                    <strong>Criado em:</strong> {{ $estadoLote['criado_em'] }}
                                </p>
                                @if(count($estadoLote['erros']) > 0)
                                    <div class="alert alert-warning mb-3">
                                        <strong>Erros:</strong> {{ count($estadoLote['erros']) }} alunos com erro
                                    </div>
                                @endif
                                <a href="{{ route('export.boletins-em-lote.download', ['turma_id' => $turmaIdSelecionada]) }}"
                                   class="btn btn-success">
                                    <i class="bi bi-download me-1"></i>Descarregar ZIP
                                </a>
                            </div>
                        </div>
                    @endif

                    <!-- Informação -->
                    <div class="alert alert-info mt-4">
                        <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Como funciona?</h6>
                        <ul class="mb-0">
                            <li>Clique em "Gerar ZIP" para criar um ficheiro ZIP com todos os boletins da turma em PDF.</li>
                            <li>A geração pode demorar alguns minutos dependendo do número de alunos.</li>
                            <li>Após a geração, o link para download ficará disponível.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
