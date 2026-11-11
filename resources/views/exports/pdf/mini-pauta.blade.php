<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Mini-Pauta - {{ $turma->nome }} - {{ $disciplina->nome }}</title>

@php
    // Escala dinâmica da tabela conforme nº de alunos para garantir 1 página A4
    $totalAlunos = is_countable($alunos) ? count($alunos) : 0;
    $baseTable = (float) ($pdfConfig['fonts']['sizes']['table'] ?? 9.5);
    $baseBody = (float) ($pdfConfig['fonts']['sizes']['body'] ?? 9);

    if ($totalAlunos <= 25) {
        $rowPad = '4px 4px';
        $rowFont = round($baseTable, 1).'px';
        $mtFont = round($baseTable + 1, 1).'px';
        $headFont = round($baseTable - 0.5, 1).'px';
    } elseif ($totalAlunos <= 35) {
        $rowPad = '2.5px 4px';
        $rowFont = round($baseTable - 0.5, 1).'px';
        $mtFont = round($baseTable, 1).'px';
        $headFont = round($baseTable - 1, 1).'px';
    } elseif ($totalAlunos <= 45) {
        $rowPad = '1.6px 3px';
        $rowFont = round($baseTable - 1, 1).'px';
        $mtFont = round($baseTable - 0.5, 1).'px';
        $headFont = round($baseTable - 1.5, 1).'px';
    } else {
        $rowPad = '1px 3px';
        $rowFont = round($baseTable - 2, 1).'px';
        $mtFont = round($baseTable - 1.5, 1).'px';
        $headFont = round($baseTable - 2, 1).'px';
    }
@endphp

@include('exports.pdf.partials.dynamic-styles')

<style>

body { color: #1a1a1a; }

/* ================= RODAPÉ FIXO ================= */

.footer {
    position: fixed;
    bottom: -8mm;
    left: 0;
    right: 0;
    height: 22px;
    font-size: 7px;
    color: #555;
    border-top: 1px solid #bbb;
    padding-top: 3px;
}

.footer-left   { width: 40%; float: left;  text-align: left; }
.footer-center { width: 40%; float: left;  text-align: center; }
.footer-right  { width: 20%; float: right; text-align: right; }

/* ================= CORES DE NOTA ================= */

.nota-azul    { color: #0047AB; font-weight: bold; }
.nota-vermelha{ color: #C00000; font-weight: bold; }
.desistente-row td { background: #f0f0f0 !important; color: #555; font-style: italic; }
.desistente-tag { color: #8a4a00; font-weight: bold; text-transform: uppercase; font-size: 8.5px; }

/* ================= LAYOUT ================= */

.page-wrapper {
    border: 1.5px solid #1a1a1a;
    padding: 8px 12px;
}

.top-bar {
    height: 4px;
    background: #1a1a1a;
    margin-bottom: 6px;
}

.header-logo {
    text-align: center;
    margin-bottom: 3px;
}
.header-logo img {
    height: 50px;
    max-width: 160px;
    display: block;
    margin: 0 auto;
}

.header-institucional {
    text-align: center;
    line-height: 1.15;
}
.republica       { font-size: 9px; letter-spacing: 0.5px; }
.ministerio      { font-size: 10px; font-weight: bold; text-transform: uppercase; }
.instituicao-nome{ font-size: 11px; font-weight: bold; text-transform: uppercase; }

.titulo-box {
    text-align: center;
    margin: 6px 0 6px 0;
    padding: 3px 0;
    border-top: 1.2px solid #1a1a1a;
    border-bottom: 1.2px solid #1a1a1a;
}
.titulo-box h1 { font-size: 12px; margin: 0; text-transform: uppercase; }
.titulo-box h2 { font-size: 9px; margin: 1px 0 0 0; font-weight: normal; }

.info-box {
    border: 1px solid #999;
    padding: 4px 6px;
    margin-bottom: 6px;
    font-size: 8.5px;
}
.info-row {
    display: table;
    width: 100%;
    margin-bottom: 1px;
}
.info-row:last-child { margin-bottom: 0; }
.info-row > div {
    display: table-cell;
    width: 50%;
    padding: 1px 2px;
}

.pauta-table {
    width: 100%;
    border-collapse: collapse;
    border: 1.2px solid #1a1a1a;
    page-break-inside: auto;
}
.pauta-table thead { display: table-header-group; }
.pauta-table tr { page-break-inside: avoid; }

.pauta-table th {
    background: #1a1a1a;
    color: #fff;
    font-size: {{ $headFont }};
    padding: 4px 3px;
    border: 1px solid #333;
    text-transform: uppercase;
}

.pauta-table td {
    border: 1px solid #ccc;
    padding: {{ $rowPad }};
    text-align: center;
    font-size: {{ $rowFont }};
}

.pauta-table td.nome-aluno {
    text-align: left;
    padding-left: 6px;
}

.pauta-table tbody tr:nth-child(even) { background: #f7f7f7; }

.mt-cell {
    font-weight: bold;
    font-size: {{ $mtFont }};
}

/* Assinaturas compactas */
.assinaturas {
    display: table;
    width: 100%;
    margin-top: 10px;
}
.assinatura-cell {
    display: table-cell;
    width: 50%;
    text-align: center;
    padding: 0 8px;
    font-size: 8px;
}
.assinatura-linha {
    border-top: 1px solid #000;
    margin: 18px 8px 2px 8px;
}

</style>
</head>

<body>

<!-- ================= RODAPÉ FIXO ================= -->
<div class="footer">
    <div class="footer-left">
        Gerado em {{ $gerado_em->format('d/m/Y H:i') }}
    </div>
    <div class="footer-center">
        Documento gerado pelo Sistema Facilita – Gestão Escolar
    </div>
    <div class="footer-right">
        <script type="text/php">
            if (isset($pdf)) {
                $font = $fontMetrics->get_font("DejaVu Sans", "normal");
                $size = 7;
                $pageText = "Página " . $PAGE_NUM . " / " . $PAGE_COUNT;
                $y = $pdf->get_height() - 30;
                $x = $pdf->get_width() - 90;
                $pdf->text($x, $y, $pageText, $font, $size, array(0.33, 0.33, 0.33));
            }
        </script>
    </div>
    <div style="clear: both;"></div>
</div>

<div class="page-wrapper">

<div class="top-bar"></div>

@if(!empty($logoBase64))
<div class="header-logo">
    <img src="{{ $logoBase64 }}" alt="Logo">
</div>
@endif

<div class="header-institucional">
    <div class="instituicao-nome">{{ $instituicao['nome'] ?? 'Instituição de Ensino' }}</div>
    <div class="republica">República de Angola</div>
    <div class="ministerio">Ministério da Educação</div>
</div>

<div class="titulo-box">
    <h1>Mini-Pauta Trimestral</h1>
    <h2>{{ $trimestre }}º Trimestre — Ano Lectivo {{ $ano_lectivo }}</h2>
</div>

<div class="info-box">
    <div class="info-row">
        <div><strong>Turma:</strong> {{ $turma->nome }}</div>
        <div><strong>Curso:</strong> {{ $turma->curso->nome ?? '-' }}</div>
    </div>
    <div class="info-row">
        <div><strong>Disciplina:</strong> {{ $disciplina->nome }}</div>
        <div><strong>Classe:</strong> {{ $turma->classe->nome ?? '-' }}</div>
    </div>
</div>

<table class="pauta-table">
<thead>
<tr>
<th style="width: 6%;">Nº</th>
<th style="width: {{ max(20, 46 - count($colunas ?? []) * 3) }}%;">Nome Completo</th>
@foreach($colunas ?? [] as $coluna)
    <th style="width: {{ max(6, floor(40 / max(1, count($colunas ?? [])))) }}%;">{{ $coluna['header'] }}</th>
@endforeach
<th style="width: 10%;">Obs</th>
</tr>
</thead>
<tbody>

@foreach($alunos as $aluno)

@php
    $isDesistente = ($aluno['situacao'] ?? null) === 'desistente';
    $chaveRes = $chave_resultado ?? 'mt';
@endphp

<tr class="{{ $isDesistente ? 'desistente-row' : '' }}">
<td>{{ $aluno['numero_ordem'] }}</td>
<td class="nome-aluno">{{ $aluno['nome'] }}</td>

@if($isDesistente)
    <td colspan="{{ count($colunas ?? []) }}" class="desistente-tag">— Desistente —</td>
@else
    @foreach($colunas ?? [] as $coluna)
        @php
            $valor = isset($aluno[$coluna['key']]) ? round($aluno[$coluna['key']]) : null;
            $destaque = ($coluna['key'] ?? '') === $chaveRes;
        @endphp
        <td class="{{ $destaque ? 'mt-cell' : '' }} {{ $valor !== null && $valor >= 10 ? 'nota-azul' : 'nota-vermelha' }}">
            {{ $valor ?? '-' }}
        </td>
    @endforeach
@endif

<td class="{{ $isDesistente ? 'desistente-tag' : '' }}">
    {{ $isDesistente ? 'Desistente' : ucfirst($aluno['situacao']) }}
</td>
</tr>

@endforeach
</tbody>
</table>

<div class="assinaturas">
    <div class="assinatura-cell">
        <div class="assinatura-linha"></div>
        O Professor
        @if(!empty($professor_nome))
            <br><strong>{{ $professor_nome }}</strong>
        @endif
    </div>
    <div class="assinatura-cell">
        <div class="assinatura-linha"></div>
        O Director de Turma
        @if(!empty($director_turma_nome))
            <br><strong>{{ $director_turma_nome }}</strong>
        @endif
    </div>
</div>

</div>
</body>
</html>
