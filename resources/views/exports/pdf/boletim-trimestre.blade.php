<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Boletim - {{ mb_strtoupper($aluno->nome, 'UTF-8') }}</title>

@include('exports.pdf.partials.dynamic-styles')
@include('exports.pdf.partials.boletim-modelo-styles')

<style>
* { box-sizing: border-box; }
body { margin: 0; color: #222; }

.documento {
    width: 100%;
}

/* HEADER */
.top-bar {
    border-bottom: 2px solid var(--pdf-primary);
    padding-bottom: 6px;
    margin-bottom: 10px;
}

.top-bar-table {
    width: 100%;
    border-collapse: collapse;
}

.top-bar-table td {
    vertical-align: top;
    border: none;
    padding: 0;
}

.top-bar-center {
    text-align: center;
}

.top-bar-qr {
    width: 78px;
    text-align: center;
}

.top-bar-qr img {
    width: 68px;
    height: 68px;
    display: block;
    margin: 0 auto 2px;
}

.top-bar-qr-label {
    font-size: 6px;
    color: #555;
    line-height: 1.15;
}

.logo-instituicao {
    max-height: 45px;
    margin-bottom: 4px;
}

.instituicao {
    font-size: 13px;
    font-weight: bold;
    color: var(--pdf-primary);
}

.info-instituicao {
    font-size: 8px;
    color: #555;
    line-height: 1.2;
}

.subtitulo {
    font-size: 10px;
    font-weight: bold;
    margin-top: 4px;
}

/* INFO ALUNO */
.info-card {
    background: #f4f6f9;
    padding: 6px;
    margin-bottom: 8px;
    border-radius: 4px;
}

.info-row {
    margin-bottom: 2px;
}

.info-label {
    font-weight: bold;
    color: var(--pdf-primary);
}

/* TABELA */
.data-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    margin-bottom: 8px;
}

.data-table th {
    background: #1e3a8a;
    color: #fff;
    padding: 4px;
    font-size: 9px;
    font-weight: normal;
}

.data-table td {
    padding: 3px;
    font-size: 9px;
    border-bottom: 1px solid #ddd;
    text-align: center;
}

.disciplina {
    text-align: left !important;
    padding-left: 4px;
    font-size: 9px;
    line-height: 1.1;
}

.media-destaque {
    font-weight: bold;
}

.aprovado {
    color: #0066cc;
    font-weight: bold;
}

.reprovado {
    color: #cc0000;
    font-weight: bold;
}

/* RESUMO */
.resumo-box {
    background: #eef2ff;
    padding: 5px;
    margin-bottom: 6px;
    font-weight: bold;
    font-size: 9px;
    border-left: 3px solid #1e3a8a;
}

/* OBS */
.observacoes {
    border: 1px solid #ddd;
    padding: 5px;
    min-height: 40px;
    font-size: 9px;
    margin-bottom: 8px;
}

/* ASSINATURAS */
.assinaturas {
    width: 100%;
    margin-top: 12px;
}

.assinaturas td {
    width: 50%;
    text-align: center;
    padding-top: 15px;
    font-size: 9px;
}

.linha-assinatura {
    border-top: 1px solid #444;
    width: 60%;
    margin: auto;
    margin-bottom: 3px;
}

/* FOOTER FIXO */
.footer {
    position: fixed;
    bottom: -5mm;
    left: 0;
    right: 0;
    font-size: 8px;
    color: #666;
    border-top: 1px solid #ccc;
    padding-top: 4px;
}

.footer-left {
    float: left;
}

.footer-center {
    text-align: center;
}

.footer-right {
    float: right;
}

.clear {
    clear: both;
}
</style>
</head>

@php use App\Support\DisciplinaAbreviador; @endphp

<body>
<div class="documento">
@if(($boletim_modelo ?? 'classico') === 'formal')
<div class="documento-inner-frame">
@endif

<div class="top-bar">
    <table class="top-bar-table">
        <tr>
            <td class="top-bar-center">
                @if(!empty($instituicao['logo']))
                    <img src="{{ storage_path('app/public/' . $instituicao['logo']) }}" class="logo-instituicao">
                @endif
                <div class="instituicao">{{ $instituicao['nome'] ?? 'Instituição de Ensino' }}</div>
                <div class="info-instituicao">
                    {{ $instituicao['endereco'] ?? '' }}
                    @if(!empty($instituicao['telefone'])) | Tel: {{ $instituicao['telefone'] }} @endif
                    @if(!empty($instituicao['email'])) | {{ $instituicao['email'] }} @endif
                </div>
                <div class="subtitulo">
                    Boletim – {{ $trimestre }}º Trimestre | {{ $ano_lectivo }}
                </div>
            </td>
            @if(!empty($qr_verificacao_data_uri))
            <td class="top-bar-qr">
                <img src="{{ $qr_verificacao_data_uri }}" alt="QR verificação">
                <div class="top-bar-qr-label">Verifique a autenticidade</div>
            </td>
            @endif
        </tr>
    </table>
</div>

<div class="info-card">
    <div class="info-row"><span class="info-label">Aluno:</span> {{ mb_strtoupper($aluno->nome, 'UTF-8') }}</div>
    <div class="info-row"><span class="info-label">Nº Processo:</span> {{ $aluno->numero_processo }}</div>
    <div class="info-row"><span class="info-label">Turma:</span> {{ $turma->nome }} | <span class="info-label">Classe:</span> {{ $turma->classe->nome ?? '-' }}</div>
</div>

<table class="data-table">
<thead>
<tr>
    <th style="width:25px;">Nº</th>
    <th>Disciplina</th>
    @foreach($colunas ?? [] as $coluna)
        <th style="width:35px;">{{ $coluna['header'] }}</th>
    @endforeach
    <th style="width:55px;">Resultado</th>
</tr>
</thead>
<tbody>
@foreach($notas as $index => $nota)
@php
    $valorResultado = $nota['valor_resultado'] ?? ($nota[$chave_resultado ?? 'mt'] ?? null);
@endphp
<tr>
    <td>{{ $index+1 }}</td>
    <td class="disciplina">{{ DisciplinaAbreviador::paraBoletim($nota['disciplina'], $nota['disciplina_codigo'] ?? null) }}</td>
    @foreach($colunas ?? [] as $coluna)
        @php $valor = $nota[$coluna['key']] ?? null; @endphp
        <td class="{{ $valor !== null && round($valor) >= 10 ? 'aprovado' : ($valor !== null ? 'reprovado' : '') }}">
            {{ $valor !== null ? round($valor) : '-' }}
        </td>
    @endforeach
    <td class="{{ $valorResultado !== null && round($valorResultado) >= 10 ? 'aprovado' : ($valorResultado !== null ? 'reprovado' : '') }}">
        {{ $valorResultado !== null ? (round($valorResultado) >= 10 ? 'Aprov.' : 'Reprov.') : '-' }}
    </td>
</tr>
@endforeach
</tbody>
</table>

@if(isset($resumo))
<div class="resumo-box">
    Aprovadas: {{ $resumo['aprovadas'] ?? 0 }} |
    Reprovadas: {{ $resumo['reprovadas'] ?? 0 }} |
    Média Geral: {{ $resumo['media_geral'] ?? '-' }}
</div>
@endif

<div class="observacoes">
<strong>Observações:</strong> {{ $observacoes ?? '' }}
</div>

<table class="assinaturas">
<tr>
<td>
<div class="linha-assinatura"></div>
Director de Turma
</td>
<td>
@include('exports.pdf.partials.assinatura-encarregado')
</td>
</tr>
</table>

@if(($boletim_modelo ?? 'classico') === 'formal')
</div>
@endif
</div>

<!-- FOOTER FIXO -->
<div class="footer">
    <div class="footer-left">Gerado em {{ now()->format('d/m/Y H:i') }}</div>
    <div class="footer-center">Sistema Facilita – Gestão Escolar</div>
    <div class="footer-right">
        <script type="text/php">
            if (isset($pdf)) {
                $font = $fontMetrics->get_font("DejaVu Sans", "normal");
                $pdf->page_text(480, 820, "Página {PAGE_NUM} / {PAGE_COUNT}", $font, 8, array(0.4,0.4,0.4));
            }
        </script>
    </div>
    <div class="clear"></div>
</div>

</body>
</html>
