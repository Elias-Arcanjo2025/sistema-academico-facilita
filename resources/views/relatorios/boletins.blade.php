<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-file-person me-2 text-primary"></i>Boletim Individual
            </h2>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-file-earmark-text" variant="primary" title="Boletins de Notas" id="tip-relatorios-boletins">
                Gere boletins individuais de notas para cada aluno. Seleccione o ano lectivo, turma e aluno para gerar o PDF. Pode também gerar boletins em lote para todos os alunos de uma turma.
            </x-tip-card>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow-soft fade-in">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Seleccionar Aluno</h5>
                        </div>
                        <div class="card-body">
                            @can('secretaria.cartao.configurar')
                                <div class="mb-3">
                                    <a href="{{ route('secretaria.boletim.configurar') }}" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-palette"></i> Personalizar modelo e cores
                                    </a>
                                </div>
                            @endcan
                            <form method="GET" action="{{ route('relatorios.boletins') }}" id="filterForm">
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
                                    <div class="col-md-4">
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
                                    <div class="col-md-4">
                                        <label for="trimestre" class="form-label">
                                            <i class="bi bi-calendar3 me-1"></i>Período
                                        </label>
                                        <select class="form-select" id="trimestre" name="trimestre" onchange="this.form.submit()">
                                            <option value="">Anual (Final)</option>
                                            <option value="1" {{ request('trimestre') == '1' ? 'selected' : '' }}>1.º Trimestre</option>
                                            <option value="2" {{ request('trimestre') == '2' ? 'selected' : '' }}>2.º Trimestre</option>
                                            <option value="3" {{ request('trimestre') == '3' ? 'selected' : '' }}>3.º Trimestre</option>
                                        </select>
                                    </div>
                                </div>
                            </form>

                            @if($turmaId && $alunos->count() > 0)
                                <hr class="my-4">

                                {{-- Acção em massa: imprimir todos os boletins da turma em 1 único PDF (2 por folha) --}}
                                <div class="alert alert-primary d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                    <div>
                                        <i class="bi bi-printer-fill me-2 fs-5"></i>
                                        <strong>Imprimir toda a turma:</strong>
                                        gera um único PDF com <strong>2 boletins por folha A4</strong> ({{ $alunos->count() }}
                                        @if($alunos->count() === 1) aluno @else alunos @endif —
                                        cerca de {{ (int) ceil($alunos->count() / 2) }}
                                        @if(ceil($alunos->count() / 2) === 1.0) folha @else folhas @endif).
                                    </div>
                                    <a href="{{ route('export.boletins-turma.pdf', array_merge(['turma_id' => $turmaId, 'ano_lectivo' => $anoLectivo], request('trimestre') ? ['trimestre' => request('trimestre')] : [])) }}"
                                       class="btn btn-primary" target="_blank"
                                       title="Gerar PDF com todos os boletins (2 por folha)">
                                        <i class="bi bi-file-earmark-pdf me-1"></i>
                                        Imprimir Toda a Turma
                                    </a>
                                </div>

                                <h6 class="mb-3"><i class="bi bi-person-badge me-2"></i>Alunos da Turma</h6>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th style="width: 60px;">N.</th>
                                                <th>Nome</th>
                                                <th style="width: 120px;">N. Processo</th>
                                                <th class="text-end" style="width: 180px;">Exportar</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($alunos as $aluno)
                                                <tr class="align-middle">
                                                    <td>
                                                        <span class="badge bg-light text-dark">{{ $aluno->numero_ordem }}</span>
                                                    </td>
                                                    <td><strong>{{ $aluno->nome }}</strong></td>
                                                    <td>
                                                        <code>{{ $aluno->numero_processo }}</code>
                                                    </td>
                                                    <td class="text-end">
                                                        <div class="btn-group" role="group">
                                                            <a href="{{ route('export.boletim.pdf', array_merge(['aluno' => $aluno, 'ano_lectivo' => $anoLectivo], request('trimestre') ? ['trimestre' => request('trimestre')] : [])) }}"
                                                               class="btn btn-sm btn-danger" target="_blank"
                                                               title="Exportar PDF" data-bs-toggle="tooltip">
                                                                <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                                                            </a>
                                                            <a href="{{ route('export.boletim.excel', array_merge(['aluno' => $aluno, 'ano_lectivo' => $anoLectivo], request('trimestre') ? ['trimestre' => request('trimestre')] : [])) }}"
                                                               class="btn btn-sm btn-success"
                                                               title="Exportar Excel" data-bs-toggle="tooltip">
                                                                <i class="bi bi-file-earmark-excel me-1"></i>Excel
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @elseif($turmaId)
                                <div class="alert alert-info mt-4 mb-0">
                                    <i class="bi bi-info-circle me-2"></i>Nenhum aluno ativo encontrado nesta turma.
                                </div>
                            @else
                                <div class="alert alert-secondary mt-4 mb-0">
                                    <i class="bi bi-arrow-up me-2"></i>Selecione uma turma para ver os alunos.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>
    @endpush
</x-app-layout>
