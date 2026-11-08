<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dashboard Pedagógico</title>
    @include('pedagogico.pdf.partials.head-styles')
    <style>h1{font-size:calc(var(--pdf-font-header) + 3px);}.kpi{display:inline-block;width:23%;text-align:center;margin:8px 0;}</style>
</head>
<body>
    <h1>Dashboard Pedagógico — {{ $kpis['trimestre'] }}º Trimestre</h1>
    <div>
        <div class="kpi"><strong>{{ $kpis['total_planificacoes'] }}</strong><br>Planificações</div>
        <div class="kpi"><strong>{{ $kpis['planificacoes_activas'] }}</strong><br>Activas</div>
        <div class="kpi"><strong>{{ number_format($kpis['execucao_media_percentagem'],1) }}%</strong><br>Execução</div>
        <div class="kpi"><strong>{{ $kpis['provas_geradas'] }}</strong><br>Provas</div>
    </div>
    <h3>Médias de notas</h3>
    <p>MAC: {{ $kpis['medias_notas']['MAC'] ?? '—' }} | PP: {{ $kpis['medias_notas']['PP'] ?? '—' }} | PT: {{ $kpis['medias_notas']['PT'] ?? '—' }} | MT: {{ $kpis['medias_notas']['MT'] ?? '—' }}</p>
</body>
</html>
