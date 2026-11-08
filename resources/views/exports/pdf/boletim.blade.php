<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Boletim Anual - {{ mb_strtoupper($aluno->nome, 'UTF-8') }}</title>

@include('exports.pdf.partials.dynamic-styles')
@include('exports.pdf.partials.boletim-modelo-styles')

<style>

* { box-sizing: border-box; }

body { margin: 0; color: #222; }

/* CONTAINER PRINCIPAL */
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

.nota-final {
    font-weight: bold;
    background: #f9fafb;
}

.aprovado {
    color: #0066cc;
    font-weight: bold;
}

.reprovado {
    color: #cc0000;
    font-weight: bold;
}

.pendente {
    color: #9ca3af;
}

/* RESULTADO FINAL */
.resultado-final {
    background: #eef2ff;
    padding: 8px;
    border-left: 3px solid #1e3a8a;
    margin-bottom: 8px;
    text-align: center;
    font-size: 11px;
    font-weight: bold;
}

/* OBS */
.observacoes {
    border: 1px solid #ddd;
    padding: 5px;
    min-height: 40px;
    font-size: 9px;
    margin-bottom: 8px;
}

/* LEGENDA */
.legenda {
    font-size: 8px;
    color: #666;
    margin-bottom: 8px;
    padding-top: 5px;
    border-top: 1px dashed #ccc;
}

/* ASSINATURAS */
.assinaturas {
    width: 100%;
    margin-top: 12px;
}

.assinaturas td {
    width: 33.33%;
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
                    Boletim Escolar Anual | {{ $ano_lectivo }}
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
    <div class="info-row"><span class="info-label">Curso:</span> {{ $turma->curso->nome ?? '-' }}</div>
</div>

<table class="data-table">
<thead>
<tr>
    <th style="width:30px;">Nº</th>
    <th>Disciplina</th>
    <th style="width:45px;">1ºT</th>
    <th style="width:45px;">2ºT</th>
    <th style="width:45px;">3ºT</th>
    <th style="width:50px;">NF</th>
    <th style="width:70px;">Resultado</th>
</tr>
</thead>
<tbody>
@foreach($disciplinas as $index => $disciplina)
<tr>
    <td>{{ $index+1 }}</td>
    <td class="disciplina">{{ DisciplinaAbreviador::paraBoletim($disciplina['disciplina']->nome, $disciplina['disciplina']->codigo ?? null) }}</td>
    <td class="{{ isset($disciplina['mt1']) && round($disciplina['mt1']) >= 10 ? 'aprovado' : 'reprovado' }}">{{ isset($disciplina['mt1']) ? round($disciplina['mt1']) : '-' }}</td>
    <td class="{{ isset($disciplina['mt2']) && round($disciplina['mt2']) >= 10 ? 'aprovado' : 'reprovado' }}">{{ isset($disciplina['mt2']) ? round($disciplina['mt2']) : '-' }}</td>
    <td class="{{ isset($disciplina['mt3']) && round($disciplina['mt3']) >= 10 ? 'aprovado' : 'reprovado' }}">{{ isset($disciplina['mt3']) ? round($disciplina['mt3']) : '-' }}</td>
    <td class="nota-final {{ isset($disciplina['nota_final']) && round($disciplina['nota_final']) >= 10 ? 'aprovado' : 'reprovado' }}">
        {{ isset($disciplina['nota_final']) ? round($disciplina['nota_final']) : '-' }}
    </td>
    <td class="{{ $disciplina['situacao'] === 'aprovado' ? 'aprovado' : ($disciplina['situacao'] === 'reprovado' ? 'reprovado' : 'pendente') }}">
        {{ $disciplina['situacao'] === 'aprovado' ? 'Aprovado' : ($disciplina['situacao'] === 'reprovado' ? 'Reprovado' : 'Pendente') }}
    </td>
</tr>
@endforeach
</tbody>
</table>

@if(isset($situacao_final))
<div class="resultado-final">
    RESULTADO FINAL:
    @if($situacao_final === 'aprovado')
        <span class="aprovado">&#10003; APROVADO</span>
    @elseif($situacao_final === 'reprovado')
        <span class="reprovado">&#10007; REPROVADO</span>
    @else
        <span class="pendente">PENDENTE</span>
    @endif
    @if(isset($media_geral) && $media_geral !== null)
        | Média Geral: {{ number_format($media_geral, 0) }}
    @endif
    @if(isset($disciplinas_reprovadas) && $disciplinas_reprovadas > 0)
        | Reprovadas: {{ $disciplinas_reprovadas }}
    @endif
</div>
@endif

<div class="observacoes">
<strong>Observações:</strong> {{ $observacoes ?? '' }}
</div>

<div class="legenda">
<strong>Legenda:</strong> 1ºT/2ºT/3ºT = Médias Trimestrais | NF = Nota Final
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
<td>
<div class="linha-assinatura"></div>
Directora Geral
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
