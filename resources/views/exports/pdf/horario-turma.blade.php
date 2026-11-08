<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Horário Escolar — {{ $turma->nome }}</title>

@php $pdfPageOrientation = 'portrait'; @endphp
@include('exports.pdf.partials.dynamic-styles')

<style>
@page { margin: 8mm 10mm 8mm 10mm; }

* { box-sizing: border-box; }

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 8.5px;
    color: #111;
    margin: 0;
}

.cabecalho {
    text-align: center;
    margin-bottom: 6px;
}

.logo img {
    max-height: 48px;
    max-width: 120px;
}

.inst-nome {
    font-size: 9px;
    font-weight: bold;
    text-transform: uppercase;
    margin-top: 2px;
}

.inst-sub {
    font-size: 8px;
    text-transform: uppercase;
}

.inst-subdestaque {
    font-size: 10px;
    font-weight: bold;
    text-transform: uppercase;
    margin: 2px 0;
}

.titulo-doc {
    font-size: 10px;
    font-weight: bold;
    color: #1e40af;
    text-transform: uppercase;
    margin: 4px 0 6px;
}

.faixa-meta {
    display: table;
    width: 100%;
    margin-bottom: 6px;
    border-collapse: collapse;
}

.faixa-meta .esq,
.faixa-meta .dir {
    display: table-cell;
    vertical-align: middle;
    padding: 4px 6px;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 7.5px;
}

.faixa-meta .esq {
    background: #f4c89a;
    width: 42%;
    line-height: 1.35;
}

.faixa-meta .dir {
    background: #2563eb;
    color: #fff;
    text-align: center;
    font-size: 8px;
}

.tabela-horario {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 6px;
}

.tabela-horario th,
.tabela-horario td {
    border: 1px solid #222;
    padding: 2px 3px;
    text-align: center;
    vertical-align: middle;
}

.tabela-horario th {
    background: #e8eef8;
    font-weight: bold;
    font-size: 7.5px;
}

.tabela-horario td.tempo,
.tabela-horario td.hora {
    font-weight: bold;
    background: #f8fafc;
    white-space: nowrap;
}

.tabela-horario td.celula-disc {
    font-weight: bold;
    font-size: 8px;
}

.linha-intervalo td {
    font-weight: bold;
    font-size: 7.5px;
    background: #f1f5f9;
    padding: 3px;
}

.tabela-legenda {
    width: 100%;
    border-collapse: collapse;
    margin-top: 4px;
    font-size: 7px;
}

.tabela-legenda th,
.tabela-legenda td {
    border: 1px solid #222;
    padding: 2px 4px;
    text-align: left;
}

.tabela-legenda th {
    background: #e8eef8;
    font-weight: bold;
}

.rodape {
    margin-top: 8px;
    font-size: 7px;
    text-align: center;
}

.rodape .lema {
    font-style: italic;
    margin: 4px 0;
}

.assinatura {
    margin-top: 18px;
    text-align: center;
}

.linha-assinatura {
    border-top: 1px solid #333;
    width: 55%;
    margin: 0 auto 3px;
}
</style>
</head>
<body>

<div class="cabecalho">
    @if(!empty($instituicao['logo']))
        <div class="logo">
            <img src="{{ storage_path('app/public/' . $instituicao['logo']) }}" alt="Logo">
        </div>
    @endif
    <div class="inst-nome">{{ $instituicao['nome'] ?? 'Instituição de Ensino' }}</div>
    <div class="inst-sub">Subdirectção Pedagógica</div>
    <div class="inst-subdestaque">Subdirectção Pedagógica</div>
    <div class="titulo-doc">Horário Escolar para o Ano Lectivo {{ $ano_lectivo_label }}</div>
</div>

<table class="faixa-meta">
<tr>
    <td class="esq">
        {{ $classe_label }}<br>
        {{ $turma_label }}<br>
        {{ $sala_label }}<br>
        {{ $turno_label }}
    </td>
    <td class="dir">{{ $curso_label }}</td>
</tr>
</table>

<table class="tabela-horario">
<thead>
<tr>
    <th style="width:6%">Tempo</th>
    <th style="width:14%">Hora</th>
    @foreach($dias_colunas as $diaNum => $diaNome)
        <th>{{ $diaNome }}</th>
    @endforeach
</tr>
</thead>
<tbody>
@foreach($periodos as $periodo)
    @if(!empty($periodo['intervalo']))
        <tr class="linha-intervalo">
            <td colspan="{{ 2 + count($dias_colunas) }}">{{ $periodo['texto'] ?? 'INTERVALO' }}</td>
        </tr>
    @else
        <tr>
            <td class="tempo">{{ $periodo['numero'] }}º</td>
            <td class="hora">{{ $periodo['hora'] }}</td>
            @foreach($dias_colunas as $diaNum => $diaNome)
                <td class="celula-disc">{{ $periodo['dias'][$diaNum] ?? '' }}</td>
            @endforeach
        </tr>
    @endif
@endforeach
</tbody>
</table>

@if(!empty($legenda))
<table class="tabela-legenda">
<thead>
<tr>
    <th style="width:12%">Abrev</th>
    <th style="width:48%">Designação</th>
    <th style="width:40%">Nome</th>
</tr>
</thead>
<tbody>
@foreach($legenda as $item)
<tr>
    <td>{{ $item['abrev'] }}</td>
    <td>{{ $item['designacao'] }}</td>
    <td>{{ $item['professor'] }}</td>
</tr>
@endforeach
</tbody>
</table>
@endif

<div class="rodape">
    <div>{{ $instituicao['nome'] ?? 'Instituição' }}, em Luanda, aos {{ $gerado_em->translatedFormat('d \d\e F \d\e Y') }}</div>
    @if(!empty($instituicao['lema']))
        <div class="lema">{{ $instituicao['lema'] }}</div>
    @else
        <div class="lema">Formar Para Transformar</div>
    @endif
</div>

<div class="assinatura">
    <div class="linha-assinatura"></div>
    <div>O Subdirector Pedagógico</div>
</div>

<div style="margin-top:6px;font-size:6px;color:#666;text-align:center;">
    Documento gerado em {{ $gerado_em->format('d/m/Y H:i') }} — Sistema Facilita
</div>

</body>
</html>
