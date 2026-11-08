<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-0">Pauta Final da Turma</h2>
                <small class="text-muted">{{ $turma->nome }} - {{ $turma->curso->nome }} | Ano Lectivo {{ $anoLectivo }}</small>
            </div>
            <div class="btn-group">
                <a href="{{ route('pautas.index', ['turma_id' => $turma->id]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
                <a href="{{ route('export.pauta-final-turma.excel', ['turma_id' => $turma->id, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-success">
                    <i class="bi bi-file-excel"></i> Excel
                </a>
                <a href="{{ route('export.pauta-final-turma.pdf', ['turma_id' => $turma->id, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-danger" target="_blank">
                    <i class="bi bi-file-pdf"></i> PDF
                </a>
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container-fluid">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Pauta Final Completa
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Cabecalho para impressao -->
                    <div class="d-none d-print-block mb-4">
                        <div class="text-center">
                            <h4>Pauta Final da Turma</h4>
                            <p class="mb-1"><strong>Turma:</strong> {{ $turma->nome }} | <strong>Curso:</strong> {{ $turma->curso->nome }}</p>
                            <p><strong>Ano Lectivo {{ $anoLectivo }}</strong></p>
                        </div>
                    </div>

                    @if(count($alunos) > 0)
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">N.º</th>
                                        <th style="min-width: 200px;">Nome do Aluno</th>
                                        @foreach($disciplinas as $disc)
                                            <th class="text-center" style="min-width: 60px;" title="{{ $disc->nome }}">
                                                {{ Str::limit($disc->codigo ?? $disc->nome, 6) }}
                                            </th>
                                        @endforeach
                                        <th class="text-center" style="width: 70px;">Média</th>
                                        <th class="text-center d-print-none" style="width: 90px;">Situação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($alunos as $alunoData)
                                        <tr>
                                            <td class="text-center">{{ $alunoData['numero_ordem'] }}</td>
                                            <td>{{ $alunoData['nome'] }}</td>
                                            @foreach($disciplinas as $disc)
                                                @php
                                                    $notaDisc = $alunoData['disciplinas'][$disc->id] ?? null;
                                                    $valor = $notaDisc['nota_final'] ?? null;
                                                @endphp
                                                <td class="text-center">
                                                    @if($valor !== null)
                                                        <span class="{{ $valor >= 10 ? 'text-success' : 'text-danger' }} fw-bold">
                                                            {{ number_format($valor, 0) }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td class="text-center">
                                                @if($alunoData['media_geral'] !== null)
                                                    <span class="{{ $alunoData['media_geral'] >= 10 ? 'text-success' : 'text-danger' }} fw-bold">
                                                        {{ number_format($alunoData['media_geral'], 0) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center d-print-none">
                                                @if($alunoData['situacao_geral'] === 'aprovado')
                                                    <span class="badge bg-primary">Aprovado</span>
                                                @elseif($alunoData['situacao_geral'] === 'reprovado')
                                                    <span class="badge bg-danger">Reprovado</span>
                                                @else
                                                    <span class="badge bg-secondary">Pendente</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Legenda -->
                        <div class="mt-3 d-print-none">
                            <small class="text-muted">
                                <strong>Legenda:</strong>
                                @foreach($disciplinas as $disc)
                                    <span class="me-3">{{ Str::limit($disc->codigo ?? $disc->nome, 6) }} = {{ $disc->nome }}</span>
                                @endforeach
                            </small>
                        </div>

                        <!-- Resumo -->
                        <div class="row mt-4 d-print-none">
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center py-2">
                                        <h4 class="mb-0">{{ count($alunos) }}</h4>
                                        <small class="text-muted">Total de Alunos</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success bg-opacity-10">
                                    <div class="card-body text-center py-2">
                                        @php
                                            $aprovados = collect($alunos)->where('situacao_geral', 'aprovado')->count();
                                        @endphp
                                        <h4 class="mb-0 text-success">{{ $aprovados }}</h4>
                                        <small class="text-muted">Aprovados</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-danger bg-opacity-10">
                                    <div class="card-body text-center py-2">
                                        @php
                                            $reprovados = collect($alunos)->where('situacao_geral', 'reprovado')->count();
                                        @endphp
                                        <h4 class="mb-0 text-danger">{{ $reprovados }}</h4>
                                        <small class="text-muted">Reprovados</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-secondary bg-opacity-10">
                                    <div class="card-body text-center py-2">
                                        @php
                                            $pendentes = collect($alunos)->where('situacao_geral', 'pendente')->count();
                                        @endphp
                                        <h4 class="mb-0">{{ $pendentes }}</h4>
                                        <small class="text-muted">Pendentes</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-inbox display-1 text-muted"></i>
                            <p class="text-muted mt-3">Nenhum aluno encontrado nesta turma.</p>
                        </div>
                    @endif

                    <!-- Rodape para impressao -->
                    <div class="d-none d-print-block" style="position: fixed; bottom: 0; left: 0; right: 0; width: 100%; border-top: 1px solid #ddd; padding: 10px 20px; background: white; font-size: 8px;">
                        <div style="width: 35%; float: left; text-align: left;">
                            Gerado em: {{ now()->format('d/m/Y H:i') }}
                        </div>
                        <div style="width: 40%; float: left; text-align: center;">
                            Documento gerado pelo Sistema Facilita – Gestão Escolar
                        </div>
                        <div style="width: 25%; float: right; text-align: right;">
                            <script type="text/php">
                                if (isset($pdf)) {
                                    $font = $fontMetrics->get_font("DejaVu Sans", "normal");
                                    $size = 8;
                                    $pageText = "Página " . $PAGE_NUM . " / " . $PAGE_COUNT;
                                    $y = $pdf->get_height() - 60;
                                    $x = $pdf->get_width() - 100;
                                    $pdf->text($x, $y, $pageText, $font, $size, array(0, 0, 0));
                                }
                            </script>
                        </div>
                        <div style="clear: both;"></div>

                        <!-- Assinaturas -->
                        <div style="width: 100%; margin-top: 20px; display: table;">
                            <div style="display: table-cell; width: 33.33%; text-align: center; padding-top: 15px; border-top: 1px solid #333;">
                                <small>Director de Turma</small>
                            </div>
                            <div style="display: table-cell; width: 33.33%; text-align: center; padding-top: 15px; border-top: 1px solid #333;">
                                <small>Coordenador de Curso</small>
                            </div>
                            <div style="display: table-cell; width: 33.33%; text-align: center; padding-top: 15px; border-top: 1px solid #333;">
                                <small>Director Pedagógico</small>
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
                font-size: 10px;
            }
            body {
                font-size: 10px;
            }
            .container-fluid {
                max-width: 100% !important;
                padding: 0 !important;
            }
        }
    </style>
    @endpush
</x-app-layout>
