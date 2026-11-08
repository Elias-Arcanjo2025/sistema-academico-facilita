<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Estatísticas de Alunos - {{ $anoLectivo }}</title>

@php $pdfPageOrientation = 'landscape'; @endphp
@include('exports.pdf.partials.dynamic-styles')

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    color: #1a1a2e;
}

/* HEADER */
.top-bar {
    border-bottom: 3px solid var(--pdf-primary);
    padding-bottom: 8px;
    margin-bottom: 12px;
    text-align: center;
}

.logo-container {
    text-align: center;
    margin-bottom: 6px;
}

.logo {
    max-height: 55px;
    max-width: 160px;
}

.instituicao {
    font-size: 13px;
    font-weight: bold;
    color: #1e3a8a;
    text-align: center;
    text-transform: uppercase;
    margin-bottom: 2px;
}

.subtitulo {
    font-size: 9px;
    text-align: center;
    color: #555;
}

.documento-titulo {
    font-size: 12px;
    font-weight: bold;
    color: #1e3a8a;
    text-align: center;
    margin: 10px 0 4px;
    text-transform: uppercase;
}

.ano-lectivo {
    font-size: 10px;
    text-align: center;
    color: #666;
    margin-bottom: 12px;
}

/* CARDS DE RESUMO */
.resumo-grid {
    display: table;
    width: 100%;
    margin-bottom: 15px;
    border-collapse: separate;
    border-spacing: 6px 0;
}

.resumo-row {
    display: table-row;
}

.resumo-card {
    display: table-cell;
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 8px 10px;
    text-align: center;
    vertical-align: middle;
    width: 20%;
}

.resumo-card-label {
    font-size: 8px;
    color: #64748b;
    text-transform: uppercase;
    font-weight: bold;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.resumo-card-valor {
    font-size: 22px;
    font-weight: bold;
    color: #1e3a8a;
    line-height: 1;
}

.resumo-card-detalhes {
    font-size: 8px;
    color: #888;
    margin-top: 3px;
}

/* SECTION TITLE */
.section-title {
    font-size: 11px;
    font-weight: bold;
    color: #1e3a8a;
    border-bottom: 2px solid #1e3a8a;
    padding-bottom: 3px;
    margin-bottom: 8px;
    margin-top: 12px;
    text-transform: uppercase;
}

/* TABLE */
.stats-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9px;
}

.stats-table th {
    background-color: #1e3a8a;
    color: #ffffff;
    padding: 5px 6px;
    text-align: center;
    border: 1px solid #1e3a8a;
    font-weight: bold;
}

.stats-table td {
    padding: 5px 6px;
    border: 1px solid #dee2e6;
    text-align: center;
    vertical-align: middle;
}

.stats-table tr:nth-child(even) td {
    background-color: #f8fafc;
}

.col-classe {
    text-align: left;
    font-weight: bold;
    padding-left: 8px;
}

.total-row td {
    background-color: #1e3a8a !important;
    color: #ffffff;
    font-weight: bold;
}

.genero-breakdown {
    font-size: 7.5px;
    color: #999;
    margin-top: 1px;
}

.total-row .genero-breakdown {
    color: #ccc;
}

/* BADGE */
.badge {
    display: inline-block;
    padding: 1px 5px;
    border-radius: 3px;
    font-size: 9px;
    font-weight: bold;
}

.badge-info    { background: #dbeafe; color: #1e40af; }
.badge-primary { background: #e0e7ff; color: #3730a3; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-danger  { background: #fee2e2; color: #991b1b; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-dark    { background: #334155; color: #ffffff; }

/* ALERT */
.alert-sem-notas {
    background: #fffbeb;
    border: 1px solid #f59e0b;
    border-radius: 4px;
    padding: 5px 8px;
    font-size: 8px;
    color: #92400e;
    margin-bottom: 10px;
}

/* FOOTER */
.footer {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    font-size: 7.5px;
    color: #aaa;
    border-top: 1px solid #e2e8f0;
    padding: 4px 12mm;
    display: table;
    width: 100%;
}

.footer div:first-child { display: table-cell; text-align: left; }
.footer div:last-child  { display: table-cell; text-align: right; }
</style>
</head>
<body>

<div>
    <!-- HEADER -->
    <div class="top-bar">
        @if(!empty($logoBase64))
            <div class="logo-container">
                <img src="{{ $logoBase64 }}" alt="Logo" class="logo">
            </div>
        @endif

        <div class="instituicao">{{ $instituicao['nome'] ?? 'Sistema de Gestão Escolar' }}</div>
        @if(!empty($instituicao['endereco']))
            <div class="subtitulo">{{ $instituicao['endereco'] }}</div>
        @endif
        @if(!empty($instituicao['telefone']) || !empty($instituicao['email']))
            <div class="subtitulo">
                @if(!empty($instituicao['telefone'])) Tel: {{ $instituicao['telefone'] }} @endif
                @if(!empty($instituicao['telefone']) && !empty($instituicao['email'])) | @endif
                @if(!empty($instituicao['email'])) Email: {{ $instituicao['email'] }} @endif
            </div>
        @endif
    </div>

    <div class="documento-titulo">Estatísticas de Alunos — {{ $trimestresOpts[$trimestre] ?? $trimestre }}º Trimestre</div>
    <div class="ano-lectivo">Ano Lectivo: {{ $anoLectivo }}</div>

    <!-- CARDS DE RESUMO -->
    <div class="resumo-grid">
        <div class="resumo-row">
            <div class="resumo-card">
                <div class="resumo-card-label">Total de Alunos</div>
                <div class="resumo-card-valor">{{ $statsGerais['total_mf'] }}</div>
                <div class="resumo-card-detalhes">
                    M: {{ $statsGerais['total_mf'] - $statsGerais['total_f'] }} | F: {{ $statsGerais['total_f'] }}
                </div>
            </div>
            <div class="resumo-card">
                <div class="resumo-card-label">Avaliados</div>
                <div class="resumo-card-valor" style="color:#3730a3;">{{ $statsGerais['avaliados_mf'] }}</div>
                <div class="resumo-card-detalhes">
                    M: {{ $statsGerais['avaliados_mf'] - $statsGerais['avaliados_f'] }} | F: {{ $statsGerais['avaliados_f'] }}
                </div>
            </div>
            <div class="resumo-card">
                <div class="resumo-card-label">C/Aproveitamento</div>
                <div class="resumo-card-valor" style="color:#065f46;">{{ $statsGerais['com_mf'] }}</div>
                <div class="resumo-card-detalhes">
                    {{ number_format($statsGerais['com_pct'], 1, ',') }}% &nbsp;|&nbsp; F: {{ $statsGerais['com_f'] }}
                </div>
            </div>
            <div class="resumo-card">
                <div class="resumo-card-label">S/Aproveitamento</div>
                <div class="resumo-card-valor" style="color:#991b1b;">{{ $statsGerais['sem_mf'] }}</div>
                <div class="resumo-card-detalhes">
                    {{ number_format($statsGerais['sem_pct'], 1, ',') }}% &nbsp;|&nbsp; F: {{ $statsGerais['sem_f'] }}
                </div>
            </div>
            <div class="resumo-card">
                <div class="resumo-card-label">Desistentes / Transferidos</div>
                <div class="resumo-card-valor" style="color:#92400e;">
                    {{ $statsGerais['desistentes_mf'] + $statsGerais['transferidos_mf'] }}
                </div>
                <div class="resumo-card-detalhes">
                    Desist: {{ $statsGerais['desistentes_mf'] }} | Transf: {{ $statsGerais['transferidos_mf'] }}
                </div>
            </div>
        </div>
    </div>

    @if($statsGerais['sem_notas_mf'] > 0)
        <div class="alert-sem-notas">
            ⚠ {{ $statsGerais['sem_notas_mf'] }} aluno(s) ainda sem {{ $statsGerais['metrica'] ?? 'notas' }} para o {{ $trimestresOpts[$trimestre] ?? $trimestre }}º Trimestre — contabilizados em S/Aproveitamento.
        </div>
    @endif

    <!-- TABELA DETALHADA POR CLASSE -->
    <div class="section-title">Aproveitamento Detalhado por Classe — {{ $trimestresOpts[$trimestre] ?? $trimestre }}º Trimestre</div>

    <table class="stats-table">
        <thead>
            <tr>
                <th style="width: 16%; text-align: left; padding-left: 8px;">Classe</th>
                <th style="width: 12%;">Total<br><span style="font-size: 7.5px; font-weight: normal;">(M / F)</span></th>
                <th style="width: 12%;">Desistentes</th>
                <th style="width: 12%;">Avaliados<br><span style="font-size: 7.5px; font-weight: normal;">(M / F)</span></th>
                <th style="width: 14%;">C/Aproveitamento<br><span style="font-size: 7.5px; font-weight: normal;">(MF / F)</span></th>
                <th style="width: 8%;">%</th>
                <th style="width: 14%;">S/Aproveitamento<br><span style="font-size: 7.5px; font-weight: normal;">(MF / F)</span></th>
                <th style="width: 8%;">%</th>
            </tr>
        </thead>
        <tbody>
            @foreach($statsPorClasse['classes'] as $classeData)
                <tr>
                    <td class="col-classe">{{ $classeData['nome'] }}</td>
                    <td>
                        <span class="badge badge-info">{{ $classeData['total'] }}</span>
                        <div class="genero-breakdown">
                            {{ $classeData['total'] - ($classeData['com_f'] + $classeData['sem_f'] + $classeData['desistentes']) }} /
                            {{ $classeData['com_f'] + $classeData['sem_f'] }}
                        </div>
                    </td>
                    <td><span class="badge badge-warning">{{ $classeData['desistentes'] }}</span></td>
                    <td>
                        <span class="badge badge-primary">{{ $classeData['avaliados'] }}</span>
                    </td>
                    <td>
                        <span class="badge badge-success">{{ $classeData['com_mf'] }}</span>
                        <div class="genero-breakdown">F: {{ $classeData['com_f'] }}</div>
                    </td>
                    <td style="font-weight: bold; color: {{ $classeData['com_pct'] >= 50 ? '#065f46' : '#991b1b' }};">
                        {{ number_format($classeData['com_pct'], 1, ',') }}%
                    </td>
                    <td>
                        <span class="badge badge-danger">{{ $classeData['sem_mf'] }}</span>
                        <div class="genero-breakdown">F: {{ $classeData['sem_f'] }}</div>
                    </td>
                    <td style="font-weight: bold; color: {{ $classeData['sem_pct'] < 50 ? '#065f46' : '#991b1b' }};">
                        {{ number_format($classeData['sem_pct'], 1, ',') }}%
                    </td>
                </tr>
            @endforeach

            <!-- TOTAL GERAL -->
            @if(!empty($statsPorClasse['total_geral']))
            <tr class="total-row">
                <td class="col-classe">TOTAL GERAL</td>
                <td>
                    <span>{{ $statsPorClasse['total_geral']['total'] }}</span>
                </td>
                <td>{{ $statsPorClasse['total_geral']['desistentes'] }}</td>
                <td>{{ $statsPorClasse['total_geral']['avaliados'] }}</td>
                <td>{{ $statsPorClasse['total_geral']['com'] }}</td>
                <td>{{ number_format($statsPorClasse['total_geral']['com_pct'], 1, ',') }}%</td>
                <td>{{ $statsPorClasse['total_geral']['sem'] }}</td>
                <td>{{ number_format($statsPorClasse['total_geral']['sem_pct'], 1, ',') }}%</td>
            </tr>
            @endif
        </tbody>
    </table>

    <!-- LEGENDA -->
    <div style="margin-top: 12px; font-size: 7.5px; color: #888;">
        <strong>Legenda:</strong>
        MF = Masculino + Feminino (total) &nbsp;|&nbsp; F = Feminino &nbsp;|&nbsp;
        C/Aproveitamento = alunos com ≤ {{ \App\Models\Configuracao::get('max_disciplinas_reprovacao', 2) }} disciplinas abaixo da nota mínima ({{ $statsGerais['metrica'] ?? 'MT' }}) &nbsp;|&nbsp;
        S/Aproveitamento = alunos com média trimestral negativa ou sem notas
    </div>
</div>

<!-- FOOTER -->
<div class="footer">
    <div>Gerado em {{ $gerado_em->format('d/m/Y \à\s H:i') }}</div>
    <div>{{ $instituicao['nome'] ?? 'Sistema de Gestão Escolar' }}</div>
</div>

</body>
</html>
