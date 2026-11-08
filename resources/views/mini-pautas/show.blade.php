<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-0">Mini-Pauta Trimestral</h2>
                <small class="text-muted">{{ $turma->nome }} - {{ $disciplina->nome }} | {{ $trimestre }}.o Trimestre {{ $anoLectivo }}</small>
            </div>
            <div class="btn-group">
                <a href="{{ route('mini-pautas.index', ['turma_id' => $turma->id, 'disciplina_id' => $disciplina->id, 'trimestre' => $trimestre]) }}" class="btn btn-outline-secondary">
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

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @include('mini-pautas.partials.importacao-excel', [
                'turmaId' => $turma->id,
                'disciplinaId' => $disciplina->id,
                'trimestre' => $trimestre,
                'anoLectivo' => $anoLectivo,
                'turma' => $turma,
            ])

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
                        <a href="{{ route('export.mini-pauta-anual.pdf', ['turma_id' => $turma->id, 'disciplina_id' => $disciplina->id, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-sm btn-outline-danger" target="_blank" title="PDF com os 3 trimestres">
                            <i class="bi bi-file-earmark-pdf"></i> Anual
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Imprimir
                        </button>
                    </div>
                    <x-recalcular-medias-disciplina
                        :turma-id="$turma->id"
                        :disciplina-id="$disciplina->id"
                        :mostrar-mt1="true"
                        :mostrar-mt2="(int) $trimestre >= 2"
                        :mostrar-mft2="(int) $trimestre >= 2"
                        :mostrar-mt3="(int) $trimestre >= 3"
                        :mostrar-cf="(int) $trimestre >= 3"
                        :mostrar-ca="(int) $trimestre >= 3"
                        :rotulo-ca="\App\Support\BoletimColunas::rotuloCa((int) ($turma->classe->codigo ?? 12))"
                        class="mt-2"
                    />
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
                        <div class="row g-2 mb-4 d-print-none">
                            <div class="col-md-2 col-6">
                                <div class="card bg-light h-100">
                                    <div class="card-body text-center py-2">
                                        <h4 class="mb-0">{{ $estatisticas['total_alunos'] }}</h4>
                                        <small class="text-muted">Alunos</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-6">
                                <div class="card bg-success bg-opacity-10 h-100">
                                    <div class="card-body text-center py-2">
                                        <h4 class="mb-0 text-success">{{ $estatisticas['aprovados'] }}</h4>
                                        <small class="text-muted">Positivas</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-6">
                                <div class="card bg-danger bg-opacity-10 h-100">
                                    <div class="card-body text-center py-2">
                                        <h4 class="mb-0 text-danger">{{ $estatisticas['reprovados'] }}</h4>
                                        <small class="text-muted">Negativas</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-6">
                                <div class="card bg-warning bg-opacity-10 h-100">
                                    <div class="card-body text-center py-2">
                                        <h4 class="mb-0 text-warning-emphasis">{{ $estatisticas['desistentes'] ?? 0 }}</h4>
                                        <small class="text-muted">Desistentes</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-6">
                                <div class="card bg-primary bg-opacity-10 h-100">
                                    <div class="card-body text-center py-2">
                                        <h4 class="mb-0">{{ $estatisticas['media_turma'] ?? '-' }}</h4>
                                        <small class="text-muted">Media</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-6">
                                <div class="card bg-light h-100">
                                    <div class="card-body text-center py-2">
                                        <h4 class="mb-0">{{ $estatisticas['nota_maxima'] ?? '-' }} / {{ $estatisticas['nota_minima'] ?? '-' }}</h4>
                                        <small class="text-muted">Max / Min</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Tabela -->
                    @include('mini-pautas.partials.tabela', compact('colunas', 'miniPauta', 'chaveResultado'))

                    <!-- Legenda para impressao -->
                    <div class="d-none d-print-block mt-3">
                        <p class="mb-2" style="font-size: 8px; color: #666; border-top: 1px dashed #ccc; padding-top: 5px;">
                            <strong>Legenda:</strong>
                            @if($trimestre == 3)
                                MFT2 = Média dos 2 trimestres | MAC3/PP3 = Avaliações do 3.º trimestre (sem PT3) |
                                MT3 = (MAC3+PP3)/2 | CF = (MFT2+MT3)/2 | PG = Prova Global | CA = 0,6×CF + 0,4×PG
                            @elseif($trimestre == 2)
                                MT1 = Média do 1.º trimestre | MAC2/PP2/PT2 = Avaliações do 2.º trimestre |
                                MT2 = média trimestral | MFT2 = (MT1+MT2)/2 — resultado baseado em MFT2
                            @else
                                MAC = Média das Avaliações Contínuas | PP = Prova do Professor |
                                PT = Prova Trimestral | MT = Média Trimestral
                            @endif
                        </p>
                    </div>

                    <!-- Rodape para impressao -->
                    <div class="d-none d-print-block mt-4 print-footer">
                        <div class="row mt-5 print-assinaturas">
                            <div class="col-6 print-assinatura">
                                <div class="print-assinatura-linha">
                                    <p class="mb-0 text-center"><strong>O Professor</strong></p>
                                    <p class="mb-0 text-center text-muted">_______________________________</p>
                                </div>
                            </div>
                            <div class="col-6 print-assinatura">
                                <div class="print-assinatura-linha">
                                    <p class="mb-0 text-center"><strong>O Director de Turma</strong></p>
                                    <p class="mb-0 text-center text-muted">_______________________________</p>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-6">
                                <p class="mb-0" style="font-size: 8px; color: #666;">Documento gerado em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</p>
                            </div>
                            <div class="col-6 text-end">
                                <p class="mb-0" style="font-size: 8px; color: #666;">Página 1 de 1</p>
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
            /* Notas */
            .text-success {
                color: #000 !important;
                font-weight: bold;
            }
            .text-danger {
                color: #cc0000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                font-weight: bold;
            }
            /* Rodape */
            .print-footer {
                margin-top: 30px;
                page-break-inside: avoid;
            }
            .print-assinaturas {
                display: flex;
                justify-content: space-around;
                margin-top: 50px;
            }
            .print-assinatura {
                text-align: center;
                width: 45%;
            }
            .print-assinatura-linha {
                border-top: 1px solid #000;
                padding-top: 5px;
                margin-top: 30px;
            }
        }
    </style>
    @endpush

</x-app-layout>
