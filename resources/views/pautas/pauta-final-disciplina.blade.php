<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-0">Pauta Final - Disciplina</h2>
                <small class="text-muted">{{ $turma->nome }} - {{ $disciplina->nome }} | Ano Lectivo {{ $anoLectivo }}</small>
            </div>
            <div class="btn-group">
                <a href="{{ route('pautas.index', ['turma_id' => $turma->id, 'disciplina_id' => $disciplina->id]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="bi bi-file-earmark-text"></i> Pauta Final
                        </h5>
                        <div class="btn-group">
                            <a href="{{ route('export.pauta-final-disciplina.excel', ['turma_id' => $turma->id, 'disciplina_id' => $disciplina->id, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-sm btn-success">
                                <i class="bi bi-file-excel"></i> Excel
                            </a>
                            <a href="{{ route('export.pauta-final-disciplina.pdf', ['turma_id' => $turma->id, 'disciplina_id' => $disciplina->id, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-sm btn-danger" target="_blank">
                                <i class="bi bi-file-pdf"></i> PDF
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">
                                <i class="bi bi-printer"></i> Imprimir
                            </button>
                        </div>
                    </div>

                    <!-- Botões de Cálculo - Regras Oficiais -->
                    <div class="d-flex flex-column gap-2">
                        <x-recalcular-medias-disciplina
                            :turma-id="$turma->id"
                            :disciplina-id="$disciplina->id"
                        />
                        <div class="d-flex gap-2 flex-wrap">
                        <small class="text-muted align-self-center me-2">Finais:</small>
                        <form action="{{ route('pautas.calcular-cf') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="turma_id" value="{{ $turma->id }}">
                            <input type="hidden" name="disciplina_id" value="{{ $disciplina->id }}">
                            <button type="submit" class="btn btn-sm btn-outline-primary" onclick="event.preventDefault(); handleCalcularCF(this);" title="Classificação Final">
                                <i class="bi bi-calculator"></i> CF
                            </button>
                        </form>
                        <form action="{{ route('pautas.calcular-ca') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="turma_id" value="{{ $turma->id }}">
                            <input type="hidden" name="disciplina_id" value="{{ $disciplina->id }}">
                            <button type="submit" class="btn btn-sm btn-outline-success" onclick="event.preventDefault(); handleCalcularCA(this);" title="Classificação Anual">
                                <i class="bi bi-calculator"></i> CA
                            </button>
                        </form>
                        <form action="{{ route('pautas.calcular-cfd') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="turma_id" value="{{ $turma->id }}">
                            <input type="hidden" name="disciplina_id" value="{{ $disciplina->id }}">
                            <button type="submit" class="btn btn-sm btn-success" onclick="event.preventDefault(); handleCalcularCFD(this);" title="Classificação Final da Disciplina">
                                <i class="bi bi-calculator"></i> CFD
                            </button>
                        </form>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Cabecalho para impressao -->
                    <div class="d-none d-print-block mb-4">
                        <div class="text-center">
                            <h4>Pauta Final</h4>
                            <p class="mb-1"><strong>Turma:</strong> {{ $turma->nome }} | <strong>Curso:</strong> {{ $turma->curso->nome }}</p>
                            <p class="mb-1"><strong>Disciplina:</strong> {{ $disciplina->nome }}</p>
                            <p><strong>Ano Lectivo {{ $anoLectivo }}</strong></p>
                        </div>
                    </div>

                    <!-- Estatisticas -->
                    @if($estatisticas)
                        <div class="row mb-4 d-print-none">
                            <div class="col-md-2">
                                <div class="card bg-light">
                                    <div class="card-body text-center py-2">
                                        <h4 class="mb-0">{{ $estatisticas['total_alunos'] }}</h4>
                                        <small class="text-muted">Alunos</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card bg-success bg-opacity-10">
                                    <div class="card-body text-center py-2">
                                        <h4 class="mb-0 text-success">{{ $estatisticas['aprovados'] }}</h4>
                                        <small class="text-muted">Aprovados</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card bg-danger bg-opacity-10">
                                    <div class="card-body text-center py-2">
                                        <h4 class="mb-0 text-danger">{{ $estatisticas['reprovados'] }}</h4>
                                        <small class="text-muted">Reprovados</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card bg-primary bg-opacity-10">
                                    <div class="card-body text-center py-2">
                                        <h4 class="mb-0">{{ $estatisticas['media_turma'] ?? '-' }}</h4>
                                        <small class="text-muted">Media</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card bg-light">
                                    <div class="card-body text-center py-2">
                                        <h4 class="mb-0">{{ $estatisticas['nota_maxima'] ?? '-' }}</h4>
                                        <small class="text-muted">Maxima</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card bg-light">
                                    <div class="card-body text-center py-2">
                                        <h4 class="mb-0">{{ $estatisticas['nota_minima'] ?? '-' }}</h4>
                                        <small class="text-muted">Minima</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Tabela -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 50px;" rowspan="2">N.º</th>
                                    <th rowspan="2">Nome do Aluno</th>
                                    <th class="text-center" colspan="3">Trimestres</th>
                                    <th class="text-center" colspan="5">Classificações Oficiais</th>
                                    <th class="text-center d-print-none" rowspan="2">Situação</th>
                                </tr>
                                <tr>
                                    <th class="text-center" style="width: 60px;">MT1</th>
                                    <th class="text-center" style="width: 60px;">MT2</th>
                                    <th class="text-center" style="width: 60px;">MT3</th>
                                    <th class="text-center" style="width: 60px;" title="Média Final dos 2 Trimestres">MFT2</th>
                                    <th class="text-center" style="width: 60px;" title="Classificação Final">CF</th>
                                    <th class="text-center" style="width: 60px;" title="Prova Global">PG</th>
                                    <th class="text-center" style="width: 60px;" title="Classificação Anual">CA</th>
                                    <th class="text-center" style="width: 70px;" title="Classificação Final da Disciplina">CFD</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pautaFinal as $linha)
                                    <tr>
                                        <td class="text-center">{{ $linha['numero_ordem'] }}</td>
                                        <td>{{ $linha['nome'] }}</td>
                                        <!-- MT1 -->
                                        <td class="text-center">
                                            @if($linha['mt1'] !== null)
                                                <span class="{{ $linha['mt1'] >= 10 ? 'text-success' : 'text-danger' }}">
                                                    {{ number_format($linha['mt1'], 0) }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <!-- MT2 -->
                                        <td class="text-center">
                                            @if($linha['mt2'] !== null)
                                                <span class="{{ $linha['mt2'] >= 10 ? 'text-success' : 'text-danger' }}">
                                                    {{ number_format($linha['mt2'], 0) }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <!-- MT3 -->
                                        <td class="text-center">
                                            @if($linha['mt3'] !== null)
                                                <span class="{{ $linha['mt3'] >= 10 ? 'text-success' : 'text-danger' }}">
                                                    {{ number_format($linha['mt3'], 0) }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <!-- MFT2 -->
                                        <td class="text-center bg-light">
                                            @if($linha['mft2'] !== null)
                                                <span class="{{ $linha['mft2'] >= 10 ? 'text-success' : 'text-danger' }} fw-semibold">
                                                    {{ number_format($linha['mft2'], 0) }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <!-- CF -->
                                        <td class="text-center bg-light">
                                            @if($linha['cf'] !== null)
                                                <span class="{{ $linha['cf'] >= 10 ? 'text-success' : 'text-danger' }} fw-semibold">
                                                    {{ number_format($linha['cf'], 0) }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <!-- PG -->
                                        <td class="text-center bg-warning bg-opacity-10">
                                            @if($linha['pg'] !== null)
                                                <span class="{{ $linha['pg'] >= 10 ? 'text-success' : 'text-danger' }} fw-semibold">
                                                    {{ number_format($linha['pg'], 0) }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <!-- CA -->
                                        <td class="text-center bg-primary bg-opacity-10">
                                            @if($linha['ca'] !== null)
                                                <span class="{{ $linha['ca'] >= 10 ? 'text-success' : 'text-danger' }} fw-bold">
                                                    {{ number_format($linha['ca'], 0) }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <!-- CFD -->
                                        <td class="text-center bg-success bg-opacity-25">
                                            @if($linha['cfd'] !== null)
                                                <span class="{{ $linha['cfd'] >= 10 ? 'text-success' : 'text-danger' }} fw-bold fs-6">
                                                    {{ number_format($linha['cfd'], 0) }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <!-- Situação -->
                                        <td class="text-center d-print-none">
                                            @if($linha['situacao'] === 'aprovado')
                                                <span class="badge bg-primary">Aprovado</span>
                                            @elseif($linha['situacao'] === 'reprovado')
                                                <span class="badge bg-danger">Reprovado</span>
                                            @else
                                                <span class="badge bg-secondary">Pendente</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            Nenhum aluno encontrado nesta turma.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Rodape para impressao -->
                    <div class="d-none d-print-block mt-4">
                        <div class="row">
                            <div class="col-6">
                                <p class="mb-0"><small>Gerado em: {{ now()->format('d/m/Y H:i') }}</small></p>
                            </div>
                            <div class="col-6 text-end">
                                <p class="mb-0"><small>Pagina 1 de 1</small></p>
                            </div>
                        </div>
                        <div class="row mt-5">
                            <div class="col-4">
                                <div class="border-top pt-2">
                                    <p class="mb-0 text-center"><small>Professor</small></p>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border-top pt-2">
                                    <p class="mb-0 text-center"><small>Director de Turma</small></p>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border-top pt-2">
                                    <p class="mb-0 text-center"><small>Coordenador de Curso</small></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('styles')
    <style>
        @media print {
            .navbar, .sidebar, .btn, .alert, .d-print-none {
                display: none !important;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
            }
            .card-header {
                background: none !important;
                border: none !important;
            }
            .table {
                font-size: 12px;
            }
            body {
                font-size: 12px;
            }
        }
    </style>
    @endpush

    @push('scripts')
    <script>
        // Handler para confirmação de cálculo de MFT2
        async function handleCalcularMFT2(button) {
            const confirmed = await confirmAction({
                title: 'Calcular MFT2?',
                html: '<p>Deseja calcular a <strong>Média Final dos 2 Trimestres (MFT2)</strong> para todos os alunos?</p>',
                icon: 'question',
                confirmText: '<i class="bi bi-calculator"></i> Sim, calcular MFT2'
            });

            if (confirmed) {
                showLoading('Calculando MFT2...');
                button.closest('form').submit();
            }
        }

        // Handler para confirmação de cálculo de CF
        async function handleCalcularCF(button) {
            const confirmed = await confirmAction({
                title: 'Calcular CF?',
                html: '<p>Deseja calcular a <strong>Classificação Final (CF)</strong> para todos os alunos?</p>',
                icon: 'question',
                confirmText: '<i class="bi bi-calculator"></i> Sim, calcular CF'
            });

            if (confirmed) {
                showLoading('Calculando CF...');
                button.closest('form').submit();
            }
        }

        // Handler para confirmação de cálculo de CA
        async function handleCalcularCA(button) {
            const confirmed = await confirmAction({
                title: 'Calcular CA?',
                html: '<p>Deseja calcular a <strong>Classificação Anual (CA)</strong> para todos os alunos?</p><p class="text-warning"><small><i class="bi bi-exclamation-triangle"></i> Requer CF e PG já lançadas.</small></p>',
                icon: 'question',
                confirmText: '<i class="bi bi-calculator"></i> Sim, calcular CA'
            });

            if (confirmed) {
                showLoading('Calculando CA...');
                button.closest('form').submit();
            }
        }

        // Handler para confirmação de cálculo de CFD
        async function handleCalcularCFD(button) {
            const confirmed = await confirmAction({
                title: 'Calcular CFD?',
                html: '<p>Deseja calcular a <strong>Classificação Final da Disciplina (CFD)</strong> para todos os alunos?</p><p class="text-info"><small><i class="bi bi-info-circle"></i> Apenas para disciplinas terminais.</small></p>',
                icon: 'question',
                confirmText: '<i class="bi bi-calculator"></i> Sim, calcular CFD'
            });

            if (confirmed) {
                showLoading('Calculando CFD...');
                button.closest('form').submit();
            }
        }

    </script>
    @endpush
</x-app-layout>
