<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Pauta Final Completa - {{ $turma->nome }}</title>
    @php $pdfPageOrientation = 'landscape'; @endphp
    @include('exports.pdf.partials.styles')
    <style>
        /* Ultra-compactação para caber dados massivos em landscape */
        body {
            font-size: 5px;
            line-height: 1.1;
        }

        .republica { font-size: 6px; }
        .ministerio { font-size: 5px; }
        .instituicao-nome { font-size: 9px; }
        .instituicao-contacto { font-size: 5px; }

        .titulo-documento h1 { font-size: 10px; letter-spacing: 1px; }
        .titulo-documento h2 { font-size: 7px; }

        /* Info em linha horizontal (específico desta pauta) */
        .info-linha {
            text-align: center;
            margin-bottom: 5px;
            font-size: 6px;
        }
        .info-linha span {
            margin: 0 10px;
        }

        /* Tabela complexa da pauta completa (específica) */
        .pauta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }
        .pauta-table th,
        .pauta-table td {
            border: 0.5px solid #000;
            padding: 1px;
            text-align: center;
            vertical-align: middle;
        }
        .pauta-table thead th {
            font-weight: bold;
            font-size: 4px;
            padding: 2px 1px;
        }
        .pauta-table thead th.disciplina-header {
            background: #b0b0b0;
            font-size: 5px;
            font-weight: bold;
            padding: 3px 1px;
        }
        .pauta-table thead th.mt-header {
            background: #fff3cd;
            font-weight: bold;
        }
        .pauta-table tbody td {
            font-size: 5px;
            padding: 1px;
        }
        .pauta-table td.nome-aluno {
            text-align: left;
            padding-left: 2px;
            font-size: 5px;
            max-width: 80px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pauta-table tr:nth-child(even) {
            background: #f8f8f8;
        }
        .mt-cell {
            background: #fffae6 !important;
            font-weight: bold;
        }

        /* Legenda compacta */
        .legenda {
            font-size: 5px;
        }

        /* Assinaturas em 4 colunas */
        .assinatura-box {
            width: 25%;
        }
        .assinatura-cargo {
            font-size: 6px;
        }

        /* Documento info compacto */
        .documento-info {
            font-size: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Logo da Instituição -->
        @if(!empty($instituicao['logo']))
            <div class="header-logo">
                <img src="{{ storage_path('app/public/' . $instituicao['logo']) }}" alt="Logo">
            </div>
        @endif

        <!-- Cabeçalho Institucional -->
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

        <!-- Título do Documento -->
        <div class="titulo-documento">
            <h1>Pauta Final Completa</h1>
            <h2>Ano Lectivo {{ $ano_lectivo }}</h2>
        </div>

        <!-- Informações da Turma -->
        <div class="info-linha">
            <span><span class="info-label">Turma:</span> {{ $turma->nome }}</span>
            <span><span class="info-label">Classe:</span> {{ $turma->classe->nome ?? '-' }}</span>
            <span><span class="info-label">Curso:</span> {{ $turma->curso->nome }}</span>
        </div>

        <!-- Tabela da Pauta Completa -->
        <table class="pauta-table">
            <thead>
                <!-- Linha 1: Nomes das Disciplinas -->
                <tr>
                    <th rowspan="2" style="width: 12px;">N.º</th>
                    <th rowspan="2" style="width: 18px;">Proc.</th>
                    <th rowspan="2" style="min-width: 60px;">Nome Completo</th>
                    <th rowspan="2" style="width: 12px;">Sexo</th>

                    @foreach($disciplinas as $disc)
                        <th colspan="15" class="disciplina-header" title="{{ $disc->nome }}">
                            {{ mb_substr($disc->nome, 0, 15) }}
                        </th>
                    @endforeach

                    <th rowspan="2" style="width: 35px;">Observ.</th>
                    <th rowspan="2" style="width: 30px;">Result.</th>
                </tr>

                <!-- Linha 2: Trimestres e tipos de notas -->
                <tr>
                    @foreach($disciplinas as $disc)
                        @for($trim = 1; $trim <= 3; $trim++)
                            <th style="width: 10px;">M{{ $trim }}</th>
                            <th style="width: 10px;">P{{ $trim }}</th>
                            <th style="width: 10px;">T{{ $trim }}</th>
                            <th class="mt-header" style="width: 12px;"><strong>MT{{ $trim }}</strong></th>
                            <th style="width: 10px;">F{{ $trim }}</th>
                        @endfor
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($alunos as $alunoData)
                    <tr>
                        <td>{{ $alunoData['numero_ordem'] }}</td>
                        <td>{{ $alunoData['numero_processo'] ?? '-' }}</td>
                        <td class="nome-aluno" title="{{ $alunoData['nome'] }}">{{ $alunoData['nome'] }}</td>
                        <td>{{ strtoupper(substr($alunoData['sexo'] ?? 'N', 0, 1)) }}</td>

                        @foreach($disciplinas as $disc)
                            @php
                                $dadosDisc = $alunoData['disciplinas'][$disc->id] ?? null;
                            @endphp

                            @for($trim = 1; $trim <= 3; $trim++)
                                @php
                                    $trimData = $dadosDisc['trimestres'][$trim] ?? null;
                                @endphp

                                <!-- MAC -->
                                <td>
                                    @if(isset($trimData['mac']) && $trimData['mac'] !== null)
                                        {{ round($trimData['mac']) }}
                                    @else
                                        -
                                    @endif
                                </td>

                                <!-- PP -->
                                <td>
                                    @if(isset($trimData['pp']) && $trimData['pp'] !== null)
                                        {{ round($trimData['pp']) }}
                                    @else
                                        -
                                    @endif
                                </td>

                                <!-- PT -->
                                <td>
                                    @if(isset($trimData['pt']) && $trimData['pt'] !== null)
                                        {{ round($trimData['pt']) }}
                                    @else
                                        -
                                    @endif
                                </td>

                                <!-- MT (destaque) -->
                                <td class="mt-cell {{ (isset($trimData['mt']) && $trimData['mt'] >= 10) ? 'nota-positiva' : 'nota-negativa' }}">
                                    @if(isset($trimData['mt']) && $trimData['mt'] !== null)
                                        {{ round($trimData['mt']) }}
                                    @else
                                        -
                                    @endif
                                </td>

                                <!-- Faltas -->
                                <td>
                                    @if(isset($trimData['faltas']) && $trimData['faltas'] > 0)
                                        {{ $trimData['faltas'] }}
                                    @else
                                        -
                                    @endif
                                </td>
                            @endfor
                        @endforeach

                        <!-- Observações -->
                        <td style="font-size: 4px;">{{ $alunoData['observacoes'] ?? '' }}</td>

                        <!-- Resultado -->
                        <td style="font-size: 5px;">
                            @php
                                if (!empty($alunoData['desistente']) || ($alunoData['resultado'] ?? null) === 'DESISTENTE') {
                                    echo 'Desist.';
                                } else {
                                    $totalDisciplinas = count($disciplinas);
                                    $disciplinasAprovadas = 0;
                                    $disciplinasReprovadas = 0;

                                    foreach($disciplinas as $disc) {
                                        $dadosDisc = $alunoData['disciplinas'][$disc->id] ?? null;
                                        if ($dadosDisc && isset($dadosDisc['nota_final']) && $dadosDisc['nota_final'] !== null) {
                                            if ($dadosDisc['nota_final'] >= 10) {
                                                $disciplinasAprovadas++;
                                            } else {
                                                $disciplinasReprovadas++;
                                            }
                                        }
                                    }

                                    if ($disciplinasAprovadas + $disciplinasReprovadas == $totalDisciplinas) {
                                        if ($disciplinasReprovadas <= 2) {
                                            echo 'Aprov.';
                                        } else {
                                            echo 'Reprov.';
                                        }
                                    } else {
                                        echo 'Pend.';
                                    }
                                }
                            @endphp
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Legenda -->
        <div class="legenda">
            <div class="legenda-titulo">Legenda:</div>
            M = MAC (Média Avaliação Contínua) | P = PP (Prova Professor) | T = PT (Prova Trimestral) | MT = Média Trimestral | F = Faltas
        </div>

        <!-- Rodapé Fixo em Todas as Páginas -->
        <div style="position: fixed; bottom: 0; left: 0; right: 0; width: 100%; border-top: 1px solid #bbb; padding: 5px 15px; background: white; font-size: 5px;">
            <div style="width: 100%; margin-bottom: 8px;">
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
                            $size = 5;
                            $pageText = "Página " . $PAGE_NUM . " / " . $PAGE_COUNT;
                            $y = $pdf->get_height() - 25;
                            $x = $pdf->get_width() - 80;
                            $pdf->text($x, $y, $pageText, $font, $size, array(0, 0, 0));
                        }
                    </script>
                </div>
                <div style="clear: both;"></div>
            </div>

            <!-- Assinaturas -->
            <div style="width: 100%; margin-top: 8px; display: table;">
                <div style="display: table-cell; width: 25%; text-align: center; padding-top: 8px; border-top: 1px solid #333;">
                    <div style="font-size: 6px;">Director de Turma</div>
                </div>
                <div style="display: table-cell; width: 25%; text-align: center; padding-top: 8px; border-top: 1px solid #333;">
                    <div style="font-size: 6px;">Coordenador de Curso</div>
                </div>
                <div style="display: table-cell; width: 25%; text-align: center; padding-top: 8px; border-top: 1px solid #333;">
                    <div style="font-size: 6px;">Subdirector Pedagógico</div>
                </div>
                <div style="display: table-cell; width: 25%; text-align: center; padding-top: 8px; border-top: 1px solid #333;">
                    <div style="font-size: 6px;">Director</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
