<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Mapa de Aproveitamento e Estatística</title>
@php
    $pdfPageOrientation = 'landscape';

    $maxTurmas = 0;
    foreach ($mapa['por_curso'] as $cd) {
        $maxTurmas = max($maxTurmas, count($cd['turmas'] ?? []));
    }
    $totalLinhasTabela = ($maxTurmas * 2) + 1;

    if ($totalLinhasTabela <= 11) {
        $pageMargin    = '12mm 14mm 12mm 14mm';
        $sheetPad      = '1mm 0 2mm 0';
        $bodyFont      = '9px';
        $cellFont      = '8.5px';
        $hdrSubFont    = '8px';
        $cellPad       = '5px 4px';
        $hdrTopPad     = '6px 4px';
        $hdrSubPad     = '4px 3px';
        $hdrMb         = '8px';
        $hdrPad        = '6px 10px 7px 10px';
        $logoH         = '44px';
        $logoMb        = '4px';
        $instFont      = '10.5px';
        $tituloFont    = '9.5px';
        $cursoFont     = '9.5px';
        $footerMt      = '14px';
        $dateMb        = '14px';
        $sigGap        = '20px';
        $totalPad      = '6px 4px';
        $mostrarLogo   = true;
    } elseif ($totalLinhasTabela <= 15) {
        $pageMargin    = '9mm 11mm 8mm 11mm';
        $sheetPad      = '0 0 1mm 0';
        $bodyFont      = '8px';
        $cellFont      = '7.5px';
        $hdrSubFont    = '7px';
        $cellPad       = '3px 3px';
        $hdrTopPad     = '4px 3px';
        $hdrSubPad     = '3px 2px';
        $hdrMb         = '5px';
        $hdrPad        = '4px 8px 5px 8px';
        $logoH         = '34px';
        $logoMb        = '2px';
        $instFont      = '9.5px';
        $tituloFont    = '8.5px';
        $cursoFont     = '8.5px';
        $footerMt      = '8px';
        $dateMb        = '8px';
        $sigGap        = '14px';
        $totalPad      = '4px 3px';
        $mostrarLogo   = true;
    } else {
        $pageMargin    = '7mm 9mm 6mm 9mm';
        $sheetPad      = '0';
        $bodyFont      = '7.5px';
        $cellFont      = '7px';
        $hdrSubFont    = '6.5px';
        $cellPad       = '2px 2px';
        $hdrTopPad     = '3px 2px';
        $hdrSubPad     = '2px 2px';
        $hdrMb         = '4px';
        $hdrPad        = '3px 6px 4px 6px';
        $logoH         = '0';
        $logoMb        = '0';
        $instFont      = '9px';
        $tituloFont    = '8px';
        $cursoFont     = '8px';
        $footerMt      = '6px';
        $dateMb        = '6px';
        $sigGap        = '10px';
        $totalPad      = '3px 2px';
        $mostrarLogo   = false;
    }
@endphp
@include('exports.pdf.partials.dynamic-styles')
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

@page {
    size: {{ $pdfPageSize ?? 'A4' }} {{ $pdfPageOrientation ?? 'landscape' }};
    margin: {{ $pageMargin }};
}

body {
    font-family: DejaVu Sans, Arial, sans-serif;
    color: #1a1a1a;
    font-size: {{ $bodyFont }};
    line-height: 1.2;
}

.page-sheet {
    padding: {{ $sheetPad }};
}

.header {
    text-align: center;
    margin-bottom: {{ $hdrMb }};
    padding: {{ $hdrPad }};
    border-bottom: 2px solid #375623;
}
.header-logo img {
    max-height: {{ $logoH }};
    max-width: 110px;
    margin-bottom: {{ $logoMb }};
}
.inst-nome {
    font-size: {{ $instFont }};
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: #000;
    line-height: 1.25;
    padding: 0 6px;
}
.inst-sub {
    font-size: {{ $bodyFont }};
    font-weight: bold;
    text-transform: uppercase;
    margin-top: 2px;
    color: #333;
    padding: 0 6px;
}
.doc-titulo {
    font-size: {{ $tituloFont }};
    font-weight: bold;
    text-transform: uppercase;
    margin-top: 5px;
    text-decoration: underline;
    color: #000;
    line-height: 1.25;
    padding: 0 8px;
}
.doc-curso {
    font-size: {{ $cursoFont }};
    font-weight: bold;
    margin-top: 3px;
    color: #C00000;
    text-transform: uppercase;
    padding: 0 8px;
}

.table-frame {
    border: 2px solid #1a1a1a;
    margin-top: 2px;
    margin-bottom: 3px;
    padding: 0;
}

table.mapa {
    width: 100%;
    border-collapse: collapse;
    border-spacing: 0;
    table-layout: fixed;
}

table.mapa th,
table.mapa td {
    border: 1px solid #1a1a1a;
    padding: {{ $cellPad }};
    text-align: center;
    vertical-align: middle;
    line-height: 1.15;
    font-size: {{ $cellFont }};
}

table.mapa thead tr.header-top th {
    background-color: #375623;
    color: #fff;
    font-weight: bold;
    font-size: {{ $cellFont }};
    padding: {{ $hdrTopPad }};
    border: 1px solid #1a1a1a;
}

table.mapa thead tr.header-sub th {
    background-color: #E2EFDA;
    color: #000;
    font-size: {{ $hdrSubFont }};
    font-weight: bold;
    padding: {{ $hdrSubPad }};
    border: 1px solid #1a1a1a;
}

td.col-nivel {
    text-align: left;
    font-weight: bold;
    white-space: nowrap;
    width: 48px;
    padding-left: 5px !important;
}
td.col-classe {
    text-align: left;
    white-space: nowrap;
    width: 72px;
    padding-left: 5px !important;
}

tr.row-turma td { background: #fff; }

tr.row-subtotal td {
    background: #F2F2F2;
    font-weight: bold;
    color: #333;
}
tr.row-subtotal td.label-sub {
    text-align: left;
    padding-left: 6px !important;
    color: #555;
    font-style: italic;
}

tr.row-total td {
    background: #4472C4;
    color: #fff;
    font-weight: bold;
    border: 1px solid #2F5496;
    padding: {{ $totalPad }};
}
tr.row-total td.label-total {
    text-align: left;
    padding-left: 6px !important;
}

.footer-section {
    margin-top: {{ $footerMt }};
    padding: 0 10px 2px 10px;
}
.city-date {
    font-size: {{ $bodyFont }};
    margin-bottom: {{ $dateMb }};
    text-align: center;
    padding: 0 12px;
}
.signatures {
    width: 100%;
    display: table;
}
.sig-col {
    display: table-cell;
    width: 50%;
    text-align: center;
    vertical-align: top;
    padding: 0 24px;
}
.sig-title {
    font-size: {{ $bodyFont }};
    font-weight: bold;
    text-transform: uppercase;
    margin-bottom: {{ $sigGap }};
    padding: 0 6px;
}
.sig-line {
    border-top: 1px solid #333;
    margin: 0 16px 4px 16px;
}
.sig-name {
    font-size: {{ $bodyFont }};
    font-weight: bold;
    padding: 0 6px;
}

.page-break { page-break-after: always; }
</style>
</head>
<body>

@foreach($mapa['por_curso'] as $cursoNome => $cursoDados)
<div class="page-sheet {{ !$loop->first ? 'page-break' : '' }}">

    <div class="header">
        @if($mostrarLogo && !empty($logoBase64))
            <div class="header-logo">
                <img src="{{ $logoBase64 }}" alt="">
            </div>
        @endif
        <div class="inst-nome">{{ $instituicao['nome'] ?? 'Sistema de Gestão Escolar' }}</div>
        @if(!empty($instituicao['endereco']))
            <div class="inst-sub">{{ $instituicao['endereco'] }}</div>
        @endif
        <div class="doc-titulo">{{ $tituloMapa ?? \App\Services\EstatisticasService::tituloMapaPdf($periodo ?? $trimestre, $anoLectivo) }}</div>
        <div class="doc-curso">CURSOS: {{ strtoupper($cursoNome) }}</div>
    </div>

    <div class="table-frame">
    <table class="mapa" cellspacing="0" cellpadding="0">
        <thead>
            <tr class="header-top">
                <th rowspan="2">NÍVEL</th>
                <th rowspan="2">CLASSE</th>
                <th colspan="2">MATRICULADOS</th>
                <th colspan="2">DESISTENTES</th>
                <th colspan="2">AVALIADOS</th>
                <th colspan="3">C/APROVEITAMENTO</th>
                <th colspan="3">S/APROVEITAMENTO</th>
                <th colspan="2">Nº DE PROFESSORES</th>
            </tr>
            <tr class="header-sub">
                <th>MF</th><th>F</th>
                <th>MF</th><th>F</th>
                <th>MF</th><th>F</th>
                <th>MF</th><th>F</th><th>%</th>
                <th>MF</th><th>F</th><th>%</th>
                <th>MF</th><th>F</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cursoDados['turmas'] as $turma)
                <tr class="row-turma">
                    <td class="col-nivel">{{ $turma['nivel'] }}</td>
                    <td class="col-classe">{{ $turma['turma_nome'] }}</td>
                    <td>{{ $turma['matriculados_mf'] }}</td>
                    <td>{{ $turma['matriculados_f'] }}</td>
                    <td>{{ $turma['desistentes_mf'] }}</td>
                    <td>{{ $turma['desistentes_f'] }}</td>
                    <td>{{ $turma['avaliados_mf'] }}</td>
                    <td>{{ $turma['avaliados_f'] }}</td>
                    <td>{{ $turma['com_mf'] }}</td>
                    <td>{{ $turma['com_f'] }}</td>
                    <td>{{ number_format($turma['com_pct'], 1, ',', '') }}</td>
                    <td>{{ $turma['sem_mf'] }}</td>
                    <td>{{ $turma['sem_f'] }}</td>
                    <td>{{ number_format($turma['sem_pct'], 1, ',', '') }}</td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="row-subtotal">
                    <td colspan="2" class="label-sub">SUB-TOTAL</td>
                    <td>{{ $turma['matriculados_mf'] }}</td>
                    <td>{{ $turma['matriculados_f'] }}</td>
                    <td>{{ $turma['desistentes_mf'] }}</td>
                    <td>{{ $turma['desistentes_f'] }}</td>
                    <td>{{ $turma['avaliados_mf'] }}</td>
                    <td>{{ $turma['avaliados_f'] }}</td>
                    <td>{{ $turma['com_mf'] }}</td>
                    <td>{{ $turma['com_f'] }}</td>
                    <td>{{ number_format($turma['com_pct'], 1, ',', '') }}</td>
                    <td>{{ $turma['sem_mf'] }}</td>
                    <td>{{ $turma['sem_f'] }}</td>
                    <td>{{ number_format($turma['sem_pct'], 1, ',', '') }}</td>
                    <td>{{ $turma['professores_mf'] }}</td>
                    <td>{{ $turma['professores_f'] }}</td>
                </tr>
            @endforeach

            <tr class="row-total">
                <td colspan="2" class="label-total">TOTAL GERAL</td>
                <td>{{ $cursoDados['total']['matriculados_mf'] }}</td>
                <td>{{ $cursoDados['total']['matriculados_f'] }}</td>
                <td>{{ $cursoDados['total']['desistentes_mf'] }}</td>
                <td>{{ $cursoDados['total']['desistentes_f'] }}</td>
                <td>{{ $cursoDados['total']['avaliados_mf'] }}</td>
                <td>{{ $cursoDados['total']['avaliados_f'] }}</td>
                <td>{{ $cursoDados['total']['com_mf'] }}</td>
                <td>{{ $cursoDados['total']['com_f'] }}</td>
                <td>{{ number_format($cursoDados['total']['com_pct'], 1, ',', '') }}</td>
                <td>{{ $cursoDados['total']['sem_mf'] }}</td>
                <td>{{ $cursoDados['total']['sem_f'] }}</td>
                <td>{{ number_format($cursoDados['total']['sem_pct'], 1, ',', '') }}</td>
                <td>{{ $cursoDados['total']['professores_mf'] }}</td>
                <td>{{ $cursoDados['total']['professores_f'] }}</td>
            </tr>
        </tbody>
    </table>
    </div>

    <div class="footer-section">
        <div class="city-date">
            LUANDA, _______ DE ______________________________ DE {{ now()->year }}
        </div>
        <div class="signatures">
            <div class="sig-col">
                <div class="sig-title">O COORDENADOR DO CURSO</div>
                <div class="sig-line"></div>
                <div class="sig-name">{{ $coordenador ?? '____________________________' }}</div>
            </div>
            <div class="sig-col">
                <div class="sig-title">O SUBDIRECTOR PEDAGÓGICO</div>
                <div class="sig-line"></div>
                <div class="sig-name">{{ $subdirector?->name ?? '____________________________' }}</div>
            </div>
        </div>
    </div>

</div>
@endforeach

</body>
</html>
