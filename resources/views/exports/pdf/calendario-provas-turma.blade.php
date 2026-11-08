<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Calendário de Provas — {{ $turma->nome }}</title>

@php $pdfPageOrientation = 'landscape'; @endphp
@include('exports.pdf.partials.dynamic-styles')

<style>
@page { margin: 6mm 8mm 6mm 8mm; }

body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #111; margin: 0; }

.cabecalho { text-align: center; margin-bottom: 4px; }
.logo img { max-height: 40px; max-width: 100px; }
.titulo-doc {
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    margin: 3px 0 5px;
    letter-spacing: 0.5px;
}

.subtitulo-classe {
    font-size: 9px;
    font-weight: bold;
    text-transform: uppercase;
    margin: 4px 0 3px;
    text-align: center;
}

.bloco-calendario {
    page-break-inside: avoid;
    margin-bottom: 10px;
}

.faixa-info {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 0;
}

.faixa-info td {
    background: #f5b7b1;
    border: 1px solid #222;
    padding: 3px 5px;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 7px;
    text-align: center;
}

.grelha-provas {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.grelha-provas th,
.grelha-provas td {
    border: 1px solid #222;
    padding: 2px 3px;
    text-align: center;
    vertical-align: middle;
    font-size: 6.5px;
    word-wrap: break-word;
}

.grelha-provas .col-rotulo {
    width: 11%;
    font-weight: bold;
    text-align: left;
    padding-left: 4px;
    background: #f8fafc;
    font-size: 6px;
}

.grelha-provas .linha-supervisor td {
    background: #fef9c3;
    font-size: 6px;
    line-height: 1.15;
}

.grelha-provas .celula-disciplina {
    font-weight: bold;
    font-size: 5.5px;
    line-height: 1.2;
    text-align: center;
}

.legenda-horarios {
    margin-top: 3px;
    font-size: 7px;
    font-weight: bold;
    text-transform: uppercase;
}

.legenda-horarios span {
    margin-right: 14px;
}

.rodape-doc {
    margin-top: 6px;
    font-size: 6px;
    text-align: center;
    color: #555;
}

.page-break {
    page-break-after: always;
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
    <div class="titulo-doc">Calendário de Provas {{ $ano_lectivo_label }}</div>
</div>

@foreach($blocos as $idx => $bloco)
    <div class="bloco-calendario {{ $idx < count($blocos) - 1 ? 'page-break' : '' }}">
        <div class="subtitulo-classe">{{ $classe_titulo }}</div>

        <table class="faixa-info">
            <tr>
                <td style="width:18%">{{ $classe_label }}</td>
                <td style="width:22%">{{ $turno_label }}</td>
                <td style="width:45%">{{ $curso_label }}</td>
                <td style="width:15%">{{ $sala_label }}</td>
            </tr>
        </table>

        <table class="grelha-provas">
            <tr>
                <td class="col-rotulo">Dia da Semana</td>
                @foreach($bloco['colunas'] as $col)
                    <td>{{ $col['dia_semana'] }}</td>
                @endforeach
            </tr>
            <tr>
                <td class="col-rotulo">Dia</td>
                @foreach($bloco['colunas'] as $col)
                    <td>{{ $col['dia'] }}</td>
                @endforeach
            </tr>
            <tr>
                <td class="col-rotulo">1ª Prova</td>
                @foreach($bloco['colunas'] as $col)
                    <td class="celula-disciplina">{{ $col['prova_1'] }}</td>
                @endforeach
            </tr>
            <tr class="linha-supervisor">
                <td class="col-rotulo">Prof. Supervisor(a)</td>
                @foreach($bloco['colunas'] as $col)
                    <td>{{ $col['supervisor_1'] }}</td>
                @endforeach
            </tr>
            <tr>
                <td class="col-rotulo">2ª Prova</td>
                @foreach($bloco['colunas'] as $col)
                    <td class="celula-disciplina">{{ $col['prova_2'] }}</td>
                @endforeach
            </tr>
            <tr class="linha-supervisor">
                <td class="col-rotulo">Prof. Supervisor(a)</td>
                @foreach($bloco['colunas'] as $col)
                    <td>{{ $col['supervisor_2'] }}</td>
                @endforeach
            </tr>
        </table>

        <div class="legenda-horarios">
            <span>1ª PROVA: {{ $calendario_config->legenda1aProva() }}</span>
            <span>2ª PROVA: {{ $calendario_config->legenda2aProva() }}</span>
        </div>
    </div>
@endforeach

<div class="rodape-doc">
    {{ $instituicao['nome'] ?? 'Instituição' }} — Gerado em {{ $gerado_em->format('d/m/Y H:i') }} — Sistema Facilita
</div>

</body>
</html>
