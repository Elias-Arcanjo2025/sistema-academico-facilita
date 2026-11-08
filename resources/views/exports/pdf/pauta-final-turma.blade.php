<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Pauta Final - {{ $turma->nome }}</title>
    @php $pdfPageOrientation = 'landscape'; @endphp
    @include('exports.pdf.partials.styles')
    <style>
        /* Compactação para landscape - fontes menores para caber mais colunas */
        body {
            font-size: 7px;
            line-height: 1.3;
            padding: 3mm;
        }

        .republica { font-size: 7px; }
        .ministerio { font-size: 6px; }
        .instituicao-nome { font-size: 11px; }
        .instituicao-contacto { font-size: 6px; }

        .titulo-documento h1 { font-size: 12px; letter-spacing: 1px; }
        .titulo-documento h2 { font-size: 8px; }

        .info-box { font-size: 7px; }
        .info-cell { font-size: 7px; width: 33.33%; }
        .info-label { min-width: 45px; }

        /* Tabela compacta */
        .data-table th,
        .data-table td {
            padding: 4px 2px;
            font-size: 7px;
        }
        .data-table thead th {
            font-size: 6px;
            padding: 5px 2px;
        }

        /* Assinaturas em 3 colunas para landscape */
        .assinatura-box {
            width: 33.33%;
        }

        .assinatura-cargo { font-size: 7px; }
        .footer-contacto { font-size: 6px; }
        .documento-info { font-size: 6px; }
    </style>
</head>
<body>
    <div class="container">
        {{-- Cabeçalho --}}
        @if(!empty($instituicao['logo']))
            <div class="header-logo">
                <img src="{{ storage_path('app/public/' . $instituicao['logo']) }}" alt="Logo">
            </div>
        @endif

        <div class="header-institucional">
            <div class="republica">República de Angola</div>
            <div class="ministerio">Ministério da Educação</div>
            <div class="instituicao-nome">{{ $instituicao['nome'] ?? 'Instituto Politécnico' }}</div>
            @if(!empty($instituicao['endereco']) || !empty($instituicao['telefone']))
                <div class="instituicao-contacto">
                    @if(!empty($instituicao['endereco'])){{ $instituicao['endereco'] }}@endif
                    @if(!empty($instituicao['endereco']) && !empty($instituicao['telefone'])) | @endif
                    @if(!empty($instituicao['telefone']))Tel: {{ $instituicao['telefone'] }}@endif
                </div>
            @endif
        </div>

        <div class="separador-duplo"></div>

        {{-- Título --}}
        <div class="titulo-documento">
            <h1>Pauta Final da Turma</h1>
            <h2>Ano Lectivo {{ $ano_lectivo }}</h2>
        </div>

        {{-- Informações --}}
        <div class="info-box">
            <div class="info-row">
                <div class="info-cell">
                    <span class="info-label">Turma:</span>
                    <strong>{{ $turma->nome }}</strong>
                </div>
                <div class="info-cell">
                    <span class="info-label">Curso:</span>
                    {{ $turma->curso->nome ?? '-' }}
                </div>
                <div class="info-cell">
                    <span class="info-label">Classe:</span>
                    {{ $turma->classe->nome ?? '-' }}
                </div>
            </div>
        </div>

        {{-- Tabela --}}
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 25px;">N.º</th>
                        <th rowspan="2" style="min-width: 120px;">Nome do Aluno</th>
                        @foreach($disciplinas as $disc)
                            <th style="width: 35px;">{{ $disc->codigo ?? substr($disc->nome, 0, 5) }}</th>
                        @endforeach
                        <th rowspan="2" style="width: 35px;">MFT</th>
                        <th rowspan="2" style="width: 60px;">Resultado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($alunos as $aluno)
                        <tr>
                            <td class="numero">{{ $aluno['numero_ordem'] }}</td>
                            <td class="nome-aluno">{{ $aluno['nome'] }}</td>
                            @foreach($disciplinas as $disc)
                                @php
                                    $nota = $aluno['disciplinas'][$disc->id]['nf'] ?? null;
                                @endphp
                                <td class="{{ ($nota ?? 0) >= 10 ? 'nota-positiva' : 'nota-negativa' }}">
                                    {{ $nota !== null ? round($nota) : '-' }}
                                </td>
                            @endforeach
                            <td style="background: #f0f0f0; font-weight: bold;">
                                {{ isset($aluno['mft']) ? round($aluno['mft']) : '-' }}
                            </td>
                            <td style="font-size: 6px;">
                                @if(isset($aluno['resultado']))
                                    {{ ucfirst($aluno['resultado']) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Rodapé Fixo em Todas as Páginas --}}
        <div style="position: fixed; bottom: 0; left: 0; right: 0; width: 100%; border-top: 1px solid #bbb; padding: 8px 20px; background: white; font-size: 6px;">
            <div style="width: 30%; float: left; text-align: left;">
                Gerado em {{ $gerado_em->format('d/m/Y H:i') }}
            </div>
            <div style="width: 45%; float: left; text-align: center;">
                Documento gerado pelo Sistema Facilita – Gestão Escolar
            </div>
            <div style="width: 25%; float: right; text-align: right;">
                <script type="text/php">
                    if (isset($pdf)) {
                        $font = $fontMetrics->get_font("DejaVu Sans", "normal");
                        $size = 6;
                        $pageText = "Página " . $PAGE_NUM . " / " . $PAGE_COUNT;
                        $y = $pdf->get_height() - 25;
                        $x = $pdf->get_width() - 100;
                        $pdf->text($x, $y, $pageText, $font, $size, array(0, 0, 0));
                    }
                </script>
            </div>
            <div style="clear: both;"></div>

            <!-- Assinaturas -->
            <div style="width: 100%; margin-top: 15px; display: table;">
                <div style="display: table-cell; width: 33.33%; text-align: center; padding-top: 12px; border-top: 1px solid #333;">
                    <div style="font-size: 7px;">O Director de Turma</div>
                </div>
                <div style="display: table-cell; width: 33.33%; text-align: center; padding-top: 12px; border-top: 1px solid #333;">
                    <div style="font-size: 7px;">O Coordenador</div>
                </div>
                <div style="display: table-cell; width: 33.33%; text-align: center; padding-top: 12px; border-top: 1px solid #333;">
                    <div style="font-size: 7px;">A Directora Geral</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
