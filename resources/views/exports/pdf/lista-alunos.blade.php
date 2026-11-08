<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Lista Nominal - {{ $turma->nome }}</title>

@php
    $totalAlunos = $alunos->count();
    $linhasVazias = 0;
    $baseBody = (float) ($pdfConfig['fonts']['sizes']['body'] ?? 10);

    if ($totalAlunos <= 25) {
        $cellFont = round($baseBody, 1).'px';
        $cellPad = '2px 3px';
        $lineHeight = '1.15';
        $bodyFont = round($baseBody - 0.5, 1).'px';
    } elseif ($totalAlunos <= 35) {
        $cellFont = round($baseBody - 1, 1).'px';
        $cellPad = '1.5px 3px';
        $lineHeight = '1.1';
        $bodyFont = round($baseBody - 1, 1).'px';
    } elseif ($totalAlunos <= 45) {
        $cellFont = round($baseBody - 1.5, 1).'px';
        $cellPad = '1px 2px';
        $lineHeight = '1.05';
        $bodyFont = round($baseBody - 1.5, 1).'px';
    } else {
        $cellFont = round($baseBody - 2, 1).'px';
        $cellPad = '0.5px 2px';
        $lineHeight = '1';
        $bodyFont = round($baseBody - 2, 1).'px';
    }
@endphp

@php $pdfPageOrientation = 'portrait'; @endphp
@include('exports.pdf.partials.dynamic-styles')

<style>
@page { margin: 7mm 8mm 6mm 8mm; }

* { box-sizing: border-box; }

body {
    font-size: {{ $bodyFont }};
    margin: 0;
    color: #2c2c2c;
}

.top-bar {
    border-bottom: 1.5px solid var(--pdf-primary);
    padding-bottom: 3px;
    margin-bottom: 4px;
}

.instituicao {
    font-size: 10px;
    font-weight: bold;
    color: #1e3a8a;
}

.subtitulo {
    font-size: 8.5px;
    margin-top: 1px;
    color: #555;
    font-weight: bold;
}

.info-card {
    background: #f4f6f9;
    padding: 3px 6px;
    border-radius: 2px;
    margin-bottom: 4px;
    font-size: {{ $bodyFont }};
    line-height: 1.2;
}

.info-label {
    font-weight: bold;
    color: #1e3a8a;
}

.lista-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    margin-bottom: 4px;
}

.lista-table thead {
    display: table-header-group;
}

.lista-table th,
.lista-table td {
    padding: {{ $cellPad }};
    font-size: {{ $cellFont }};
    line-height: {{ $lineHeight }};
    vertical-align: middle;
}

.lista-table th {
    background: #1e3a8a;
    color: #fff;
    font-weight: bold;
    text-transform: uppercase;
}

.lista-table td {
    border-bottom: 1px solid #e5e5e5;
}

.lista-table th.col-ordem,
.lista-table td.col-ordem,
.lista-table th.col-processo,
.lista-table td.col-processo {
    text-align: center;
    white-space: nowrap;
}

.lista-table th.col-nome,
.lista-table td.col-nome {
    text-align: left;
    padding-left: 4px !important;
    word-wrap: break-word;
}

col.col-ordem, .lista-table .col-ordem { width: 6%; }
col.col-processo, .lista-table .col-processo { width: 12%; }
col.col-nome, .lista-table .col-nome { width: 82%; }

.rodape-documento {
    margin-top: 4px;
    page-break-inside: avoid;
}

.resumo-box {
    background: #eef2ff;
    padding: 2px 6px;
    border-left: 3px solid #1e3a8a;
    margin-bottom: 3px;
    font-weight: bold;
    font-size: {{ $bodyFont }};
}

.assinaturas {
    width: 100%;
    border-collapse: collapse;
}

.assinaturas td {
    width: 50%;
    text-align: center;
    font-size: 7.5px;
    vertical-align: top;
}

.linha-assinatura {
    border-top: 1px solid #444;
    width: 65%;
    margin: 8px auto 2px auto;
}

.footer-info {
    margin-top: 2px;
    font-size: 6px;
    color: #666;
    text-align: center;
}
</style>
</head>

<body>

<div class="top-bar">
    @if(!empty($instituicao['logo']))
        <div style="text-align: center; margin-bottom: 1px;">
            <img src="{{ storage_path('app/public/' . $instituicao['logo']) }}" alt="Logo" style="max-height: 32px; max-width: 100px;">
        </div>
    @endif
    <div style="text-align: center;">
        <div class="instituicao">{{ $instituicao['nome'] ?? 'Instituição de Ensino' }}</div>
        @if(!empty($instituicao['endereco']) || !empty($instituicao['telefone']))
            <div style="font-size: 6px; color: #666; line-height: 1.1;">
                @if(!empty($instituicao['endereco'])){{ $instituicao['endereco'] }}@endif
                @if(!empty($instituicao['telefone'])) — Tel: {{ $instituicao['telefone'] }}@endif
            </div>
        @endif
    </div>
    <div class="subtitulo" style="text-align: center;">
        Lista Nominal de Alunos — {{ $turma->nome }}
    </div>
</div>

<div class="info-card">
    <span class="info-label">Turma:</span> {{ $turma->nome }}
    &nbsp;|&nbsp;
    <span class="info-label">Curso:</span> {{ $curso->nome }}
    &nbsp;|&nbsp;
    <span class="info-label">Classe:</span> {{ $turma->classe->nome ?? '-' }}
    &nbsp;|&nbsp;
    <span class="info-label">Sala:</span> {{ $turma->sala->nome ?? '-' }}
    &nbsp;|&nbsp;
    <span class="info-label">Ano Lectivo:</span>
    @if(isset($turma->ano_lectivo) && is_numeric($turma->ano_lectivo))
        {{ $turma->ano_lectivo }}/{{ $turma->ano_lectivo + 1 }}
    @else
        {{ $turma->ano_lectivo ?? date('Y').'/'.(date('Y')+1) }}
    @endif
    &nbsp;|&nbsp;
    <span class="info-label">Período:</span> {{ $turma->periodo ?? ($turma->turno->nome ?? '-') }}
</div>

<table class="lista-table">
<colgroup>
    <col class="col-ordem">
    <col class="col-processo">
    <col class="col-nome">
</colgroup>
<thead>
<tr>
    <th class="col-ordem">Nº</th>
    <th class="col-processo">Processo</th>
    <th class="col-nome">Nome Completo do Aluno</th>
</tr>
</thead>
<tbody>
@foreach($alunos as $aluno)
<tr>
    <td class="col-ordem">{{ str_pad($aluno->numero_ordem, 2, '0', STR_PAD_LEFT) }}</td>
    <td class="col-processo">{{ $aluno->numero_processo }}</td>
    <td class="col-nome">{{ mb_convert_case(mb_strtolower($aluno->nome, 'UTF-8'), MB_CASE_TITLE, 'UTF-8') }}</td>
</tr>
@endforeach
</tbody>
</table>

<div class="rodape-documento">
    <div class="resumo-box">Total de Alunos: {{ $total_alunos }}</div>

    <table class="assinaturas">
    <tr>
        <td>
            <div class="linha-assinatura"></div>
            Director de Turma
        </td>
        <td>
            <div class="linha-assinatura"></div>
            Secretaria Académica
        </td>
    </tr>
    </table>

    <div class="footer-info">
        Documento gerado em {{ $gerado_em->format('d/m/Y') }} às {{ $gerado_em->format('H:i') }} — Gerado pelo sistema Facilita
    </div>
</div>

</body>
</html>
