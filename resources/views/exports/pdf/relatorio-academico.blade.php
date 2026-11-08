<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Relatório Académico — {{ $anoLectivo ?? '' }}</title>
@include('exports.pdf.partials.dynamic-styles')
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    color: #1e293b;
    line-height: 1.35;
}

/* ── Cabeçalho ── */
.top-bar {
    border-bottom: 3px solid var(--pdf-primary);
    padding-bottom: 8px;
    margin-bottom: 10px;
    text-align: center;
}
.logo-wrap img { max-height: 46px; max-width: 120px; margin-bottom: 4px; }
.inst-nome {
    font-size: 10px; font-weight: bold;
    text-transform: uppercase; color: #1e3a8a;
    letter-spacing: 0.3px;
}
.inst-sub { font-size: 7.5px; color: #64748b; margin-top: 1px; }
.doc-titulo {
    font-size: 13px; font-weight: bold;
    color: #4f46e5; margin-top: 6px;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.doc-badges { margin-top: 5px; }
.badge {
    display: inline-block;
    font-size: 7px; font-weight: bold;
    padding: 2px 7px; border-radius: 10px;
    margin: 0 2px;
}
.badge-primary { background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; }
.badge-muted   { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.doc-meta { font-size: 7px; color: #94a3b8; margin-top: 4px; }

/* ── KPI cards (table layout — DomPDF-safe) ── */
.kpi-table { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin-bottom: 10px; }
.kpi-cell { width: 25%; vertical-align: top; }
.kpi-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-left: 3px solid #4f46e5;
    border-radius: 4px;
    padding: 7px 8px;
    text-align: center;
}
.kpi-card.green  { border-left-color: #059669; }
.kpi-card.orange { border-left-color: #d97706; }
.kpi-card.slate  { border-left-color: #64748b; }
.kpi-num  { font-size: 18px; font-weight: bold; line-height: 1; color: #1e293b; }
.kpi-num.blue   { color: #4f46e5; }
.kpi-num.green  { color: #059669; }
.kpi-num.orange { color: #d97706; }
.kpi-lbl  { font-size: 7px; color: #64748b; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.4px; font-weight: bold; }

/* ── Secções ── */
.section { margin-bottom: 11px; page-break-inside: avoid; }
.section-head {
    background: #4f46e5;
    color: #fff;
    font-size: 8.5px; font-weight: bold;
    text-transform: uppercase; letter-spacing: 0.4px;
    padding: 4px 8px;
    border-radius: 3px 3px 0 0;
}
.section-body {
    border: 1px solid #e2e8f0;
    border-top: none;
    border-radius: 0 0 3px 3px;
    padding: 6px 7px;
    background: #fff;
}
.section-empty { font-size: 8px; color: #94a3b8; padding: 8px 4px; text-align: center; }

/* ── Tabelas ── */
.data-table { width: 100%; border-collapse: collapse; font-size: 8px; }
.data-table th {
    background: #f1f5f9;
    color: #475569;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 7px;
    letter-spacing: 0.3px;
    padding: 4px 5px;
    border-bottom: 2px solid #e2e8f0;
    text-align: left;
}
.data-table th.center { text-align: center; }
.data-table td {
    padding: 3px 5px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.data-table tr:nth-child(even) td { background: #fafbfc; }
.data-table tr:last-child td { border-bottom: none; }
.text-center { text-align: center; }
.text-right  { text-align: right; }
.fw-bold { font-weight: bold; }

/* ── Barras de progresso (aproveitamento) ── */
.aprov-row td { padding: 5px; }
.aprov-label { font-weight: bold; width: 38%; }
.aprov-qty   { width: 12%; text-align: center; font-weight: bold; }
.aprov-pct   { width: 10%; text-align: center; color: #64748b; }
.aprov-bar-cell { width: 40%; }
.bar-track {
    background: #e2e8f0;
    height: 7px;
    border-radius: 4px;
    overflow: hidden;
}
.bar-fill { height: 7px; border-radius: 4px; }
.bar-green  { background: #10b981; }
.bar-red    { background: #ef4444; }
.bar-orange { background: #f59e0b; }
.bar-indigo { background: #6366f1; }
.bar-amber  { background: #f59e0b; }
.bar-gray   { background: #94a3b8; }

/* ── Turmas agrupadas ── */
.curso-label {
    font-size: 7px; font-weight: bold;
    text-transform: uppercase; letter-spacing: 0.5px;
    color: #64748b;
    padding: 4px 0 2px;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 2px;
}

/* ── Ranking por turma (blocos) ── */
.ranking-grid { width: 100%; border-collapse: separate; border-spacing: 6px 6px; }
.ranking-grid td { width: 50%; vertical-align: top; }
.turma-block {
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
    page-break-inside: avoid;
}
.turma-block-head {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 4px 6px;
    font-size: 8px; font-weight: bold;
    color: #1e293b;
}
.turma-block-sub { font-size: 7px; color: #64748b; font-weight: normal; }
.rank-row td { padding: 3px 5px; font-size: 8px; border-bottom: 1px solid #f8fafc; }
.rank-row:last-child td { border-bottom: none; }
.rank-pos { width: 16px; text-align: center; font-weight: bold; color: #64748b; }
.rank-pos.gold   { color: #b45309; }
.rank-pos.silver { color: #6b7280; }
.rank-pos.bronze { color: #92400e; }
.rank-name { font-weight: 600; }
.rank-score {
    text-align: right; font-weight: bold;
    color: #059669; width: 22px;
}
.rank-empty { font-size: 7.5px; color: #94a3b8; padding: 6px; text-align: center; }

/* ── Professores ── */
.prof-row td { padding: 5px; border-bottom: 1px solid #f1f5f9; }
.prof-row:last-child td { border-bottom: none; }
.prof-rank { width: 14px; font-weight: bold; color: #94a3b8; text-align: center; }
.prof-name { font-weight: bold; }
.prof-stat { text-align: right; color: #dc2626; font-weight: bold; white-space: nowrap; }
.prof-meta { font-size: 7px; color: #64748b; padding-top: 1px; }
.prof-bar { margin-top: 3px; }
.footnote { font-size: 7px; color: #94a3b8; margin-top: 5px; line-height: 1.4; }

/* ── Rodapé ── */
.footer {
    border-top: 1px solid #e2e8f0;
    padding-top: 5px;
    margin-top: 10px;
    text-align: center;
    font-size: 7px;
    color: #94a3b8;
}
.page-break { page-break-before: always; }
</style>
</head>
<body>

@php
    $t3Oficial = ($modo_aproveitamento ?? '') === 't3_oficial';
    $totalActivos = max($aproveitamento['activos'], 1);
    $totalGeral = max($aproveitamento['total'], 1);
    $pctDes = round($aproveitamento['desistentes'] / $totalGeral * 100);
    $metricaLabel = $metrica_ranking ?? 'MT';
    $turmasComRanking = $melhores_por_turma->filter(fn ($i) => ($i['ranking'] ?? collect())->isNotEmpty());
@endphp

{{-- ── CABEÇALHO ── --}}
<div class="top-bar">
    @if(!empty($logoBase64))
    <div class="logo-wrap"><img src="{{ $logoBase64 }}" alt="Logo"></div>
    @endif
    <div class="inst-nome">{{ $instituicao['nome'] ?? 'Instituição de Ensino' }}</div>
    @if(!empty($instituicao['endereco']))
    <div class="inst-sub">{{ $instituicao['endereco'] }}</div>
    @endif
    <div class="doc-titulo">Relatório Académico</div>
    <div class="doc-badges">
        <span class="badge badge-primary">{{ $anoLectivo ?? '' }}</span>
        <span class="badge badge-primary">{{ $trimestreNome }}</span>
        <span class="badge badge-muted">{{ $cursoNome }}</span>
        @if(!empty($turmaNome))
        <span class="badge badge-muted">{{ $turmaNome }}</span>
        @endif
    </div>
    <div class="doc-meta">Gerado em {{ $dataGeracao }} · Facilita</div>
</div>

{{-- ── KPIs ── --}}
<table class="kpi-table">
    <tr>
        <td class="kpi-cell">
            <div class="kpi-card">
                <div class="kpi-num blue">{{ $total_turmas }}</div>
                <div class="kpi-lbl">Turmas</div>
            </div>
        </td>
        <td class="kpi-cell">
            <div class="kpi-card green">
                <div class="kpi-num green">{{ $aproveitamento['activos'] }}</div>
                <div class="kpi-lbl">Alunos Activos</div>
            </div>
        </td>
        <td class="kpi-cell">
            <div class="kpi-card orange">
                <div class="kpi-num orange">{{ $aproveitamento['desistentes'] }}</div>
                <div class="kpi-lbl">Desistentes</div>
            </div>
        </td>
        <td class="kpi-cell">
            <div class="kpi-card slate">
                <div class="kpi-num">{{ $aproveitamento['total'] }}</div>
                <div class="kpi-lbl">Total Matrículas</div>
            </div>
        </td>
    </tr>
</table>

{{-- ── APROVEITAMENTO ── --}}
<div class="section">
    <div class="section-head">Aproveitamento — {{ $trimestreNome }}</div>
    <div class="section-body">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Indicador</th>
                    <th class="center" style="width:12%">Qtd.</th>
                    @if(!$t3Oficial)<th class="center" style="width:10%">%</th>@endif
                    <th style="width:40%">Distribuição</th>
                </tr>
            </thead>
            <tbody>
                @if($t3Oficial)
                    @php
                        $transita = $aproveitamento['transita'] + $aproveitamento['aprovado'];
                        $recup = $aproveitamento['recuperacao'];
                        $reprova = $aproveitamento['reprova'];
                        $pend = $aproveitamento['pendente'] ?? 0;
                        $pctT = round($transita / $totalActivos * 100);
                        $pctR = round($recup / $totalActivos * 100);
                        $pctRep = round($reprova / $totalActivos * 100);
                        $pctP = round($pend / $totalActivos * 100);
                    @endphp
                    <tr class="aprov-row">
                        <td class="aprov-label" style="color:#059669;">Transitam / Aprovados</td>
                        <td class="aprov-qty">{{ $transita }}</td>
                        <td class="aprov-bar-cell">
                            <div class="bar-track"><div class="bar-fill bar-green" style="width:{{ $pctT }}%;"></div></div>
                        </td>
                    </tr>
                    <tr class="aprov-row">
                        <td class="aprov-label" style="color:#d97706;">Transita c/ Def.</td>
                        <td class="aprov-qty">{{ $recup }}</td>
                        <td class="aprov-bar-cell">
                            <div class="bar-track"><div class="bar-fill bar-amber" style="width:{{ $pctR }}%;"></div></div>
                        </td>
                    </tr>
                    <tr class="aprov-row">
                        <td class="aprov-label" style="color:#dc2626;">N/TRANSITA / Reprovados</td>
                        <td class="aprov-qty">{{ $reprova }}</td>
                        <td class="aprov-bar-cell">
                            <div class="bar-track"><div class="bar-fill bar-red" style="width:{{ $pctRep }}%;"></div></div>
                        </td>
                    </tr>
                    @if($pend > 0)
                    <tr class="aprov-row">
                        <td class="aprov-label" style="color:#64748b;">Pendentes</td>
                        <td class="aprov-qty">{{ $pend }}</td>
                        <td class="aprov-bar-cell">
                            <div class="bar-track"><div class="bar-fill bar-gray" style="width:{{ $pctP }}%;"></div></div>
                        </td>
                    </tr>
                    @endif
                @else
                    @php
                        $pctCom = round($aproveitamento['comAproveitamento'] / $totalActivos * 100);
                        $pctSem = round($aproveitamento['semAproveitamento'] / $totalActivos * 100);
                    @endphp
                    <tr class="aprov-row">
                        <td class="aprov-label" style="color:#059669;">C/ Aproveitamento</td>
                        <td class="aprov-qty">{{ $aproveitamento['comAproveitamento'] }}</td>
                        <td class="aprov-pct">{{ $pctCom }}%</td>
                        <td class="aprov-bar-cell">
                            <div class="bar-track"><div class="bar-fill bar-green" style="width:{{ $pctCom }}%;"></div></div>
                        </td>
                    </tr>
                    <tr class="aprov-row">
                        <td class="aprov-label" style="color:#dc2626;">S/ Aproveitamento</td>
                        <td class="aprov-qty">{{ $aproveitamento['semAproveitamento'] }}</td>
                        <td class="aprov-pct">{{ $pctSem }}%</td>
                        <td class="aprov-bar-cell">
                            <div class="bar-track"><div class="bar-fill bar-red" style="width:{{ $pctSem }}%;"></div></div>
                        </td>
                    </tr>
                @endif
                <tr class="aprov-row">
                    <td class="aprov-label" style="color:#d97706;">Desistentes</td>
                    <td class="aprov-qty">{{ $aproveitamento['desistentes'] }}</td>
                    @if(!$t3Oficial)<td class="aprov-pct">{{ $pctDes }}%</td>@endif
                    <td class="aprov-bar-cell">
                        <div class="bar-track"><div class="bar-fill bar-orange" style="width:{{ $pctDes }}%;"></div></div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- ── TURMAS ── --}}
<div class="section">
    <div class="section-head">Turmas ({{ $total_turmas }})</div>
    <div class="section-body">
        @if($turmas->isEmpty())
            <div class="section-empty">Sem turmas para os filtros seleccionados.</div>
        @else
            @foreach($turmas->groupBy(fn ($t) => $t->curso_id) as $grupo)
            @php $cursoNomeGrupo = $grupo->first()->curso?->nome ?? 'Sem curso'; @endphp
            @if($turmas->pluck('curso_id')->unique()->count() > 1)
            <div class="curso-label">{{ $cursoNomeGrupo }}</div>
            @endif
            <table class="data-table" style="margin-bottom:6px;">
                <thead>
                    <tr>
                        <th style="width:18%">Turma</th>
                        <th>Curso</th>
                        <th class="center" style="width:14%">Classe</th>
                        <th class="center" style="width:12%">Alunos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($grupo->sortBy('nome') as $t)
                    <tr>
                        <td class="fw-bold">{{ $t->nome }}</td>
                        <td>{{ $t->curso?->nome ?? '—' }}</td>
                        <td class="text-center">{{ $t->classe?->nome ?? '—' }}</td>
                        <td class="text-center fw-bold">{{ $t->alunos->count() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endforeach
        @endif
    </div>
</div>

{{-- ── MELHORES ALUNOS ── --}}
<div class="section page-break">
    <div class="section-head">
        Melhores Alunos por Turma — {{ $trimestreNome }}
        <span style="font-weight:normal; opacity:.85;">· Top 5 · {{ $metricaLabel }} · sem negativas</span>
    </div>
    <div class="section-body">
        @if($melhores_por_turma->isEmpty())
            <div class="section-empty">Sem dados disponíveis.</div>
        @elseif($turmasComRanking->isEmpty())
            <div class="section-empty">Nenhum aluno elegível (sem negativas) para este período.</div>
        @else
            @php
                $blocos = $melhores_por_turma->sortBy(fn ($i) => $i['turma']->nome)->values();
                $pares = $blocos->chunk(2);
            @endphp
            <table class="ranking-grid">
                @foreach($pares as $par)
                <tr>
                    @foreach($par as $item)
                    @php $turmaItem = $item['turma']; $ranking = $item['ranking']; @endphp
                    <td>
                        <div class="turma-block">
                            <div class="turma-block-head">
                                {{ $turmaItem->nome }}
                                @if($turmaItem->numeroClasse())
                                <span style="color:#4f46e5;">· {{ $turmaItem->numeroClasse() }}ª</span>
                                @endif
                                <div class="turma-block-sub">{{ $turmaItem->curso?->nome }}</div>
                            </div>
                            @if($ranking->isEmpty())
                            <div class="rank-empty">Sem alunos elegíveis</div>
                            @else
                            <table style="width:100%; border-collapse:collapse;">
                                @foreach($ranking as $i => $entry)
                                @php
                                    $pos = $i + 1;
                                    $posClass = $pos === 1 ? 'gold' : ($pos === 2 ? 'silver' : ($pos === 3 ? 'bronze' : ''));
                                    $medal = $pos === 1 ? '1' : ($pos === 2 ? '2' : ($pos === 3 ? '3' : $pos));
                                @endphp
                                <tr class="rank-row">
                                    <td class="rank-pos {{ $posClass }}">{{ $medal }}</td>
                                    <td class="rank-name">{{ $entry['aluno']->nome }}</td>
                                    <td class="rank-score">{{ $entry['media'] }}</td>
                                </tr>
                                @endforeach
                            </table>
                            @endif
                        </div>
                    </td>
                    @endforeach
                    @if($par->count() === 1)
                    <td></td>
                    @endif
                </tr>
                @endforeach
            </table>
        @endif
    </div>
</div>

{{-- ── PROFESSORES ── --}}
<div class="section">
    <div class="section-head">Professores com Mais Notas Negativas — {{ $trimestreNome }}</div>
    <div class="section-body">
        @if($top_professores_negativos->isEmpty())
            <div class="section-empty">Sem registos de notas negativas para os filtros seleccionados.</div>
        @else
            @php $maxNeg = max(1, $top_professores_negativos->max('total_negativas')); @endphp
            <table style="width:100%; border-collapse:collapse;">
                @foreach($top_professores_negativos as $i => $p)
                @php
                    $pos = $i + 1;
                    $pctBar = round($p->total_negativas / $maxNeg * 100);
                @endphp
                <tr class="prof-row">
                    <td class="prof-rank">{{ $pos }}</td>
                    <td>
                        <div class="prof-name">{{ $p->professor_nome }}</div>
                        <div class="prof-meta">
                            Média neg.: {{ number_format($p->media_valor ?? 0, 1, ',', '.') }}
                            · {{ $p->pct_negativas ?? 0 }}% do total
                            ({{ number_format($p->total_lancamentos ?? 0, 0, ',', '.') }} provas)
                        </div>
                        <div class="prof-bar">
                            <div class="bar-track">
                                <div class="bar-fill bar-red" style="width:{{ $pctBar }}%;"></div>
                            </div>
                        </div>
                    </td>
                    <td class="prof-stat">{{ number_format($p->total_negativas, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </table>
            <p class="footnote">
                Conta MAC, PP e PT abaixo da nota mínima de aprovação, deduplicado pré/pós-conselho.
                @if(isset($periodo) && $periodo === 'anual') Inclui todos os trimestres do ano lectivo. @endif
            </p>
        @endif
    </div>
</div>

<div class="footer">
    {{ $instituicao['nome'] ?? 'Facilita' }} · Relatório Académico · {{ $trimestreNome }} · {{ $dataGeracao }}
</div>

</body>
</html>
