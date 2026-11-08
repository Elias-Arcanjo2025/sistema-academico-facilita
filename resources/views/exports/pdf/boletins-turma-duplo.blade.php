<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Boletins — {{ $turma->nome }} — {{ $ano_lectivo }}</title>
@include('exports.pdf.partials.dynamic-styles')
@include('exports.pdf.partials.boletim-modelo-styles')
<style>
/* 2 boletins por folha — margens compactas (sobrepor configuração global) */
@page {
    size: A4 landscape;
    margin: 3mm 4mm;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body { color: #222; }

/* ─── PAR DE BOLETINS LADO-A-LADO NA MESMA FOLHA A4 LANDSCAPE ── */
table.par {
    width: 100%;
    height: 200mm;
    border-collapse: collapse;
    page-break-after: always;
    table-layout: fixed;
}
table.par.last {
    page-break-after: auto;
}

td.boletim-cell {
    width: 49.7%;
    vertical-align: top;
    padding: 3mm;
    border: 1px dashed #aaa;
}

td.separador-cell {
    width: 0.6%;
    padding: 0;
    border: 0;
}

/* ─── CABEÇALHO COMPACTO ─────────────────────────────────────── */
.top-bar {
    border-bottom: 1.5px solid #1e3a8a;
    padding-bottom: 4px;
    margin-bottom: 6px;
    text-align: center;
}

.logo-instituicao {
    max-height: 42px;
    max-width: 130px;
    margin: 0 auto 2px;
    display: block;
}

.instituicao {
    font-size: 10.5px;
    font-weight: bold;
    color: #1e3a8a;
    line-height: 1.2;
    text-align: center;
    width: 100%;
    margin: 0 auto;
    padding: 0 4px;
    word-wrap: break-word;
}

.info-instituicao {
    font-size: 8.5px;
    color: #555;
    line-height: 1.2;
    text-align: center;
}

.subtitulo {
    font-size: 11px;
    font-weight: bold;
    margin-top: 3px;
    color: #1e3a8a;
    text-align: center;
}

/* ─── RESUMO ESTATÍSTICAS ────────────────────────────────────── */
.resumo-box {
    background: #f4f6f9;
    border-left: 3px solid #1e3a8a;
    padding: 5px 8px;
    margin-bottom: 6px;
    font-size: 10px;
    text-align: center;
    line-height: 1.4;
}

.resumo-box .label {
    color: #555;
}

.resumo-box .val {
    font-weight: bold;
    color: #1e3a8a;
}

.resumo-box .val.ok    { color: #0066cc; }
.resumo-box .val.fail  { color: #cc0000; }
.resumo-box .val.warn  { color: #b45309; }

/* ─── INFO ALUNO ─────────────────────────────────────────────── */
.info-card {
    background: #f4f6f9;
    padding: 6px 8px;
    margin-bottom: 6px;
    border-radius: 3px;
    font-size: 10.5px;
    line-height: 1.45;
}

.info-card strong {
    color: #1e3a8a;
}

/* ─── TABELA DE DISCIPLINAS ──────────────────────────────────── */
.data-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    margin-bottom: 6px;
}

.data-table th {
    background: #1e3a8a;
    color: #fff;
    padding: 5px 3px;
    font-size: 9px;
    font-weight: normal;
    border: 1px solid #1e3a8a;
}

.data-table td {
    padding: 5px 4px;
    font-size: 9px;
    border-bottom: 1px solid #e2e8f0;
    text-align: center;
    line-height: 1.2;
}

.disciplina {
    text-align: left !important;
    padding-left: 5px;
}

.nota-final {
    font-weight: bold;
    background: #f9fafb;
}

.aprovado    { color: #0066cc; font-weight: bold; }
.reprovado   { color: #cc0000; font-weight: bold; }
.pendente    { color: #9ca3af; }

/* ─── RESULTADO FINAL ────────────────────────────────────────── */
.resultado-final {
    background: #eef2ff;
    padding: 7px 8px;
    border-left: 3px solid #1e3a8a;
    margin-bottom: 6px;
    text-align: center;
    font-size: 11.5px;
    font-weight: bold;
}

/* ─── ASSINATURAS ────────────────────────────────────────────── */
.assinaturas {
    width: 100%;
    margin-top: 14px;
    border-collapse: collapse;
}

.assinaturas td {
    text-align: center;
    padding-top: 16px;
    font-size: 9.5px;
    line-height: 1;
}

.linha-assinatura {
    border-top: 1px solid #444;
    width: 75%;
    margin: 0 auto 2px;
}
</style>

</head>
<body>

@php
    // Agrupa boletins de 2 em 2; se sobrar 1, fica sozinho à esquerda
    $pares = array_chunk($boletins, 2);
    $totalPares = count($pares);
@endphp

@foreach($pares as $parIndex => $par)
<table class="par {{ $parIndex === $totalPares - 1 ? 'last' : '' }}">
    <tr>
        {{-- BOLETIM DA ESQUERDA --}}
        <td class="boletim-cell">
            @include('exports.pdf.partials.boletim-meio', ['b' => $par[0], 'logoBase64' => $logoBase64, 'instituicao' => $instituicao])
        </td>

        <td class="separador-cell">&nbsp;</td>

        {{-- BOLETIM DA DIREITA --}}
        @if(isset($par[1]))
        <td class="boletim-cell">
            @include('exports.pdf.partials.boletim-meio', ['b' => $par[1], 'logoBase64' => $logoBase64, 'instituicao' => $instituicao])
        </td>
        @else
        <td class="boletim-cell" style="border:none;">&nbsp;</td>
        @endif
    </tr>
</table>
@endforeach

</body>
</html>
