<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Lista de Transição — {{ $turma->nome }}@if(!empty($filtro)) — {{ $filtro }}@endif</title>

@php
    $totalLinhas = collect($gruposExibir)->sum(fn ($g) => count($g));
    $compacto = $totalLinhas > 28;
    $bodyFont = $compacto ? '7.5px' : '8.5px';
    $cellFont = $compacto ? '7px' : '8px';
    $cellPad = $compacto ? '2px 3px' : '3px 4px';

    $coresGrupo = [
        'TRANSITA' => ['bg' => '#d9e8f7', 'border' => '#0d6efd', 'text' => '#0d47a1'],
        'TRANSITA/C DEF' => ['bg' => '#fff3cd', 'border' => '#ffc107', 'text' => '#856404'],
        'RECUPERAÇÃO' => ['bg' => '#fff3cd', 'border' => '#fd7e14', 'text' => '#984c0c'],
        'RECURSO' => ['bg' => '#fff3cd', 'border' => '#fd7e14', 'text' => '#984c0c'],
        'APTO' => ['bg' => '#d9e8f7', 'border' => '#0d6efd', 'text' => '#0d47a1'],
        'N/TRANSITA' => ['bg' => '#f8d7da', 'border' => '#dc3545', 'text' => '#842029'],
        'N/APTO' => ['bg' => '#f8d7da', 'border' => '#dc3545', 'text' => '#842029'],
        'Pendente' => ['bg' => '#e9ecef', 'border' => '#6c757d', 'text' => '#495057'],
        'DESISTENTE' => ['bg' => '#e2e3e5', 'border' => '#6c757d', 'text' => '#495057'],
    ];
@endphp

@php $pdfPageOrientation = 'landscape'; @endphp
@include('exports.pdf.partials.dynamic-styles')

<style>

* { box-sizing: border-box; }

body {
    font-size: {{ $bodyFont }};
    color: #222;
    margin: 0;
}

.top-bar {
    border-bottom: 2px solid var(--pdf-primary);
    padding-bottom: 5px;
    margin-bottom: 8px;
    text-align: center;
}

.instituicao {
    font-size: {{ $compacto ? '10px' : '11px' }};
    font-weight: bold;
    color: #1e3a8a;
}

.subtitulo {
    font-size: {{ $compacto ? '9px' : '10px' }};
    margin-top: 2px;
    color: #444;
}

.info-card {
    background: #f4f6f9;
    padding: 5px 8px;
    border-radius: 3px;
    margin-bottom: 8px;
    font-size: {{ $bodyFont }};
}

.resumo-inline span {
    display: inline-block;
    margin-right: 10px;
    font-weight: bold;
}

.grupo-section {
    margin-bottom: 14px;
    page-break-inside: avoid;
}

.grupo-section + .grupo-section {
    page-break-before: auto;
}

.grupo-titulo {
    font-size: {{ $compacto ? '9px' : '10px' }};
    font-weight: bold;
    padding: 4px 8px;
    margin-bottom: 4px;
    border-left: 4px solid #1e3a8a;
}

.lista-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    margin-bottom: 0;
}

.lista-table thead {
    display: table-header-group;
}

.lista-table th,
.lista-table td {
    border: 1px solid #ccc;
    padding: {{ $cellPad }};
    font-size: {{ $cellFont }};
    vertical-align: top;
    word-wrap: break-word;
}

.lista-table th {
    background: #1e3a8a;
    color: #fff;
    font-weight: bold;
    text-align: center;
}

.lista-table td.centro { text-align: center; }
.lista-table td.nome { text-align: left; }
.lista-table td.disc { text-align: left; font-size: {{ $compacto ? '6.5px' : '7.5px' }}; }

.sem-alunos {
    font-style: italic;
    color: #666;
    padding: 6px 8px;
    border: 1px dashed #ccc;
    margin-bottom: 8px;
}

.rodape {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    font-size: 6.5px;
    color: #666;
    text-align: center;
    border-top: 1px dashed #ccc;
    padding-top: 3px;
}

.assinaturas {
    width: 100%;
    margin-top: 12px;
    border-collapse: collapse;
}

.assinaturas td {
    width: 33%;
    text-align: center;
    font-size: 7.5px;
    vertical-align: top;
}

.linha-assinatura {
    border-top: 1px solid #444;
    width: 75%;
    margin: 18px auto 4px auto;
}
</style>
</head>
<body>

<div class="rodape">
    Documento gerado em {{ $gerado_em->format('d/m/Y H:i') }} — Condições de Transição de Classe (DE 424/25) — Sistema Facilita
</div>

<div class="top-bar">
    @if(!empty($instituicao['logo']))
        <div style="margin-bottom: 2px;">
            <img src="{{ storage_path('app/public/' . $instituicao['logo']) }}" alt="Logo" style="max-height: 38px; max-width: 110px;">
        </div>
    @endif
    <div class="instituicao">{{ $instituicao['nome'] ?? 'Instituição de Ensino' }}</div>
    <div class="subtitulo">
        Lista por Resultado de Transição — {{ $turma->nome }}
        · {{ $classe_numero }}ª classe · {{ $turma->curso->nome ?? '' }}
        · T{{ $trimestre }} · {{ $ano_lectivo }}
    </div>
</div>

<div class="info-card">
    <strong>Pauta T3 — DE 424/25</strong>
    @if(empty($filtro))
        <div class="resumo-inline" style="margin-top: 3px;">
            @foreach(\App\Services\ListaTransicaoTurmaService::GRUPOS_ORDEM as $g)
                <span>{{ $g }}: {{ $resumo[$g] ?? 0 }}</span>
            @endforeach
        </div>
    @else
        <div style="margin-top: 3px;">Resultado: <strong>{{ $filtro }}</strong> — {{ count($gruposExibir[$filtro] ?? []) }} aluno(s)</div>
    @endif
</div>

@foreach($gruposExibir as $grupo => $linhas)
    @php $cor = $coresGrupo[$grupo] ?? ['bg' => '#f4f6f9', 'border' => '#1e3a8a', 'text' => '#222']; @endphp
    <div class="grupo-section">
        <div class="grupo-titulo" style="background: {{ $cor['bg'] }}; border-color: {{ $cor['border'] }}; color: {{ $cor['text'] }};">
            {{ $grupo }} ({{ count($linhas) }})
        </div>

        @if(count($linhas) === 0)
            <div class="sem-alunos">Sem alunos nesta categoria.</div>
        @else
            <table class="lista-table">
                <thead>
                    <tr>
                        <th style="width: 4%;">N.º</th>
                        <th style="width: 8%;">Processo</th>
                        <th style="width: 18%;">Nome</th>
                        <th style="width: 10%;">Resultado</th>
                        <th style="width: 14%;">Observação</th>
                        <th style="width: 15%;">Deficiências (contínuas)</th>
                        <th style="width: 15%;">Recuperação (terminais)</th>
                        <th style="width: 4%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($linhas as $l)
                        <tr>
                            <td class="centro">{{ $l['numero_ordem'] }}</td>
                            <td class="centro">{{ $l['numero_processo'] }}</td>
                            <td class="nome">{{ $l['nome'] }}</td>
                            <td class="centro">{{ $l['resultado'] }}</td>
                            <td class="disc">{{ $l['observacao'] ?: '—' }}</td>
                            <td class="disc">{{ $l['deficiencias'] ?: '—' }}</td>
                            <td class="disc">{{ $l['recuperacao'] ?: '—' }}</td>
                            <td class="centro">{{ $l['total_negativas'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endforeach

@if(empty($filtro))
    <table class="assinaturas">
        <tr>
            <td><div class="linha-assinatura"></div>Director de Turma</td>
            <td><div class="linha-assinatura"></div>Coordenador de Curso</td>
            <td><div class="linha-assinatura"></div>Subdirector Pedagógico</td>
        </tr>
    </table>
@endif

</body>
</html>
