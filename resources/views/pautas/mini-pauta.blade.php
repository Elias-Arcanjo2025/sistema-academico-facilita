<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-0">Mini-Pauta Trimestral</h2>
                <small class="text-muted">{{ $turma->nome }} - {{ $disciplina->nome }} | {{ $trimestre }}.o Trimestre {{ $anoLectivo }}</small>
            </div>
            <div class="btn-group">
                <a href="{{ route('pautas.index', ['turma_id' => $turma->id, 'disciplina_id' => $disciplina->id, 'trimestre' => $trimestre]) }}" class="btn btn-outline-secondary">
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

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-table"></i> Mini-Pauta
                    </h5>
                    <div class="btn-group">
                        <a href="{{ route('export.mini-pauta.excel', ['turma_id' => $turma->id, 'disciplina_id' => $disciplina->id, 'trimestre' => $trimestre, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-sm btn-success">
                            <i class="bi bi-file-excel"></i> Excel
                        </a>
                        <a href="{{ route('export.mini-pauta.pdf', ['turma_id' => $turma->id, 'disciplina_id' => $disciplina->id, 'trimestre' => $trimestre, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-sm btn-danger" target="_blank">
                            <i class="bi bi-file-pdf"></i> PDF
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Imprimir
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Cabecalho para impressao -->
                    <div class="d-none d-print-block mb-4">
                        <div class="text-center print-header">
                            <p class="print-republica">Republica de Angola</p>
                            <p class="print-ministerio">Ministério da Educação</p>
                            <h4 class="print-instituicao">{{ \App\Models\Configuracao::get('instituicao_nome', 'Instituto Politécnico') }}</h4>
                            <hr class="print-separador">
                            <h5 class="print-titulo">Mini-Pauta Trimestral</h5>
                            <p class="mb-1"><strong>Turma:</strong> {{ $turma->nome }} | <strong>Curso:</strong> {{ $turma->curso->nome }}</p>
                            <p class="mb-1"><strong>Disciplina:</strong> {{ $disciplina->nome }}</p>
                            <p><strong>{{ $trimestre }}.o Trimestre</strong> - Ano Lectivo {{ $anoLectivo }}</p>
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
                                        <small class="text-muted">Positivas</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card bg-danger bg-opacity-10">
                                    <div class="card-body text-center py-2">
                                        <h4 class="mb-0 text-danger">{{ $estatisticas['reprovados'] }}</h4>
                                        <small class="text-muted">Negativas</small>
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
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 60px;">N.º</th>
                                    <th>Nome do Aluno</th>
                                    <th class="text-center" style="width: 80px;">MAC</th>
                                    <th class="text-center" style="width: 80px;">PP</th>
                                    <th class="text-center" style="width: 80px;">PT</th>
                                    <th class="text-center" style="width: 80px;">MT</th>
                                    <th class="text-center d-print-none" style="width: 100px;">Situação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($miniPauta as $linha)
                                    <tr>
                                        <td class="text-center">{{ $linha['numero_ordem'] }}</td>
                                        <td>{{ $linha['nome'] }}</td>
                                        <td class="text-center">
                                            @if($linha['mac'] !== null)
                                                <span class="{{ round($linha['mac']) >= 10 ? 'text-primary' : 'text-danger' }} fw-bold">
                                                    {{ round($linha['mac']) }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($linha['pp'] !== null)
                                                <span class="{{ round($linha['pp']) >= 10 ? 'text-primary' : 'text-danger' }} fw-bold">
                                                    {{ round($linha['pp']) }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($linha['pt'] !== null)
                                                <span class="{{ round($linha['pt']) >= 10 ? 'text-primary' : 'text-danger' }} fw-bold">
                                                    {{ round($linha['pt']) }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($linha['mt'] !== null)
                                                <span class="{{ round($linha['mt']) >= 10 ? 'text-primary' : 'text-danger' }} fw-bold fs-5">
                                                    {{ round($linha['mt']) }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center d-print-none">
                                            @if($linha['situacao'] === 'aprovado')
                                                <span class="badge bg-success">Positiva</span>
                                            @elseif($linha['situacao'] === 'reprovado')
                                                <span class="badge bg-danger">Negativa</span>
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

                    <!-- Legenda para impressao -->
                    <div class="d-none d-print-block mt-3">
                        <p class="mb-2" style="font-size: 8px; color: #666; border-top: 1px dashed #ccc; padding-top: 5px;">
                            <strong>Legenda:</strong> MAC = Média das Avaliações Contínuas | PP = Prova do Professor | PT = Prova Trimestral | MT = Média Trimestral | Obs. = Observações
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Rodapé fixo para impressão (aparece em todas as páginas) -->
    <div class="footer-print d-none d-print-block">
        <div class="footer-info">
            <div class="footer-left">
                <small>Gerado em {{ now()->format('d/m/Y H:i') }}</small>
            </div>
            <div class="footer-center">
                <small>Documento gerado pelo Sistema Facilita – Gestão Escolar</small>
            </div>
            <div class="footer-right">
                <small>Página <span class="page-number"></span></small>
            </div>
        </div>
        <div class="footer-assinaturas">
            <div class="assinatura-item">
                <div class="assinatura-linha"></div>
                <small>O Professor</small>
            </div>
            <div class="assinatura-item">
                <div class="assinatura-linha"></div>
                <small>O Director de Turma</small>
            </div>
        </div>
    </div>

    @push('styles')
    <style>
        @media print {
            .navbar, .sidebar, .btn, .alert, .d-print-none, .card-header {
                display: none !important;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
            }
            .table {
                font-size: 10px;
            }
            .table th, .table td {
                border: 1px solid #000 !important;
                padding: 4px !important;
            }
            .table thead th {
                background: #e0e0e0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            body {
                font-size: 10px;
                counter-reset: page;
                padding-bottom: 50mm !important;
            }
            .container {
                max-width: 100% !important;
                padding: 0 !important;
            }
            .py-4 {
                padding: 0 !important;
            }
            .card-body {
                padding: 0 !important;
            }
            /* Cabecalho institucional */
            .print-header {
                margin-bottom: 15px;
            }
            .print-republica {
                font-size: 9px;
                text-transform: uppercase;
                margin-bottom: 2px;
            }
            .print-ministerio {
                font-size: 8px;
                margin-bottom: 5px;
            }
            .print-instituicao {
                font-size: 14px;
                font-weight: bold;
                text-transform: uppercase;
                margin-bottom: 3px;
            }
            .print-separador {
                border-top: 2px double #000;
                margin: 8px 0;
            }
            .print-titulo {
                font-size: 14px;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: 1px;
                margin: 10px 0;
            }
            /* Notas - Cores atualizadas */
            .text-primary {
                color: #0066cc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                font-weight: bold;
            }
            .text-danger {
                color: #cc0000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                font-weight: bold;
            }

            /* Rodapé fixo em todas as páginas */
            .footer-print {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                width: 100%;
                padding: 5mm 10mm;
                border-top: 1px solid #333;
                background: white;
            }

            .footer-info {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 8mm;
                font-size: 7px;
            }

            .footer-info .footer-left,
            .footer-info .footer-center,
            .footer-info .footer-right {
                flex: 1;
                text-align: center;
            }

            .footer-info .footer-left {
                text-align: left;
            }

            .footer-info .footer-right {
                text-align: right;
            }

            /* Contador de páginas automático */
            .page-number::before {
                counter-increment: page;
                content: counter(page);
            }

            .footer-assinaturas {
                display: flex;
                justify-content: space-around;
                gap: 10mm;
                margin-top: 5mm;
            }

            .assinatura-item {
                flex: 1;
                text-align: center;
            }

            .assinatura-linha {
                border-top: 1px solid #333;
                margin-bottom: 2mm;
                width: 100%;
            }

            .assinatura-item small {
                font-size: 7px;
                font-weight: bold;
            }
        }
    </style>
    @endpush

</x-app-layout>
