<x-app-layout>
    <x-slot name="header">Relatório Académico</x-slot>

    @push('styles')
    <style>
        .stat-card { border-radius: 12px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .stat-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .table-turma th { background: #f1f5f9; font-size: 12px; }
        .filter-card { background: var(--card-bg, #fff); border: 1px solid var(--border-color, #e2e8f0); border-radius: 10px; }
        .progress-bar-custom { height: 8px; border-radius: 4px; }
        .periodo-tab { border: 2px solid #dee2e6; border-radius: 8px; padding: 6px 16px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all .15s; text-decoration: none; color: #495057; }
        .periodo-tab.active { border-color: #0d6efd; background: #0d6efd; color: #fff; }

        /* ── Relatório académico: secções modernas ── */
        .relatorio-page { width: 100%; max-width: 100%; overflow-x: hidden; }

        .ra-section {
            background: var(--bg-card, #fff);
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 14px;
            box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,.08));
            padding: 1.25rem 1.25rem 1rem;
        }
        @media (min-width: 768px) { .ra-section { padding: 1.5rem; } }

        .ra-section-head {
            display: flex; flex-wrap: wrap; align-items: flex-start;
            justify-content: space-between; gap: .75rem;
            margin-bottom: 1.25rem;
        }
        .ra-section-title {
            font-size: 1rem; font-weight: 700; margin: 0 0 .2rem;
            display: flex; align-items: center; gap: .5rem;
        }
        .ra-section-sub { font-size: .8rem; color: var(--text-muted, #64748b); margin: 0; }

        .ra-pill {
            display: inline-flex; align-items: center;
            font-size: .72rem; font-weight: 600;
            padding: .25rem .65rem; border-radius: 999px;
            background: var(--bg-light, #f1f5f9);
            color: var(--text-muted, #64748b);
            border: 1px solid var(--border-color, #e2e8f0);
            white-space: nowrap;
        }
        .ra-pill-sm { font-size: .65rem; padding: .15rem .5rem; }
        .ra-pill-primary { background: rgba(79,70,229,.1); color: #4f46e5; border-color: transparent; }
        .ra-pill-danger { background: rgba(239,68,68,.1); color: #dc2626; border-color: transparent; }

        .ra-empty {
            text-align: center; padding: 2rem 1rem;
            color: var(--text-muted, #94a3b8); font-size: .875rem;
            background: var(--bg-light, #f8fafc); border-radius: 10px;
        }

        /* Lista de alunos */
        .ra-list { list-style: none; margin: 0; padding: 0; }
        .ra-list-item {
            display: flex; align-items: center; gap: .75rem;
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--border-color, #e2e8f0);
        }
        .ra-list-item:last-child { border-bottom: none; }
        .ra-list-item.is-medal-1 { background: rgba(255,215,0,.06); }
        .ra-list-item.is-medal-2 { background: rgba(192,192,192,.08); }
        .ra-list-item.is-medal-3 { background: rgba(205,127,50,.06); }
        .ra-list-pos {
            flex-shrink: 0; width: 2rem; text-align: center;
            font-size: 1rem; font-weight: 700; color: var(--text-muted, #64748b);
        }
        .ra-list-body { flex: 1; min-width: 0; }
        .ra-list-name {
            display: block; font-size: .875rem; font-weight: 600;
            line-height: 1.35; color: var(--text-dark, #1e293b);
            word-break: break-word;
        }
        .ra-list-meta { display: block; font-size: .75rem; color: var(--text-muted, #64748b); margin-top: .1rem; }
        .ra-list-score {
            flex-shrink: 0; min-width: 2.5rem; text-align: center;
            font-size: 1rem; font-weight: 800;
            padding: .35rem .6rem; border-radius: 8px;
            background: #ecfdf5; color: #047857;
        }

        /* Accordion turmas */
        .ra-accordion { --bs-accordion-border-radius: 10px; }
        .ra-acc-item {
            border: 1px solid var(--border-color, #e2e8f0) !important;
            border-radius: 10px !important; overflow: hidden;
            margin-bottom: .5rem;
        }
        .ra-acc-item:last-child { margin-bottom: 0; }
        .ra-acc-btn {
            font-size: .875rem; padding: .85rem 1rem;
            background: var(--bg-light, #f8fafc) !important;
            box-shadow: none !important;
        }
        .ra-acc-btn:not(.collapsed) { background: #fff !important; }
        .ra-acc-turma { font-weight: 700; margin-right: .35rem; }
        .ra-acc-curso { font-size: .75rem; color: var(--text-muted, #64748b); margin-left: .5rem; }
        .ra-acc-count { font-size: .72rem; color: var(--text-muted, #64748b); font-weight: 500; }
        .ra-acc-footer {
            padding: .5rem 1rem; text-align: right;
            background: var(--bg-light, #f8fafc);
            border-top: 1px solid var(--border-color, #e2e8f0);
        }
        .ra-acc-footer a { font-size: .8rem; text-decoration: none; font-weight: 600; }
        .ra-curso-label {
            font-size: .68rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .05em; color: var(--text-muted, #64748b);
            margin: 1rem 0 .5rem; padding-bottom: .35rem;
            border-bottom: 2px solid var(--border-color, #e2e8f0);
        }
        .ra-curso-label:first-child { margin-top: 0; }

        /* Pódio (turma única) */
        .ra-podium-wrap { text-align: center; }
        .ra-podium-meta { margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; gap: .5rem; justify-content: center; align-items: center; }
        .ra-turma-link { font-weight: 800; font-size: 1.1rem; color: var(--text-dark, #1e293b); text-decoration: none; }
        .ra-turma-link:hover { color: var(--primary-color, #4f46e5); }
        .ra-podium {
            display: flex; align-items: flex-end; justify-content: center;
            gap: .75rem; margin-bottom: 1.5rem; flex-wrap: wrap;
        }
        .ra-podium-slot {
            flex: 1; min-width: 100px; max-width: 160px;
            padding: 1rem .75rem; border-radius: 12px;
            background: var(--bg-light, #f8fafc);
            border: 1px solid var(--border-color, #e2e8f0);
        }
        .ra-podium-slot.pos-1 {
            order: 2; transform: scale(1.05);
            background: linear-gradient(180deg, rgba(255,215,0,.15) 0%, #fff 100%);
            border-color: #ffd700; padding-top: 1.25rem;
        }
        .ra-podium-slot.pos-2 { order: 1; }
        .ra-podium-slot.pos-3 { order: 3; }
        .ra-podium-medal { font-size: 1.75rem; line-height: 1; margin-bottom: .35rem; }
        .ra-podium-score { font-size: 1.75rem; font-weight: 800; color: #047857; line-height: 1.1; }
        .ra-podium-name { font-size: .8rem; font-weight: 600; margin-top: .5rem; line-height: 1.3; word-break: break-word; }
        .ra-podium-num { font-size: .7rem; color: var(--text-muted, #64748b); }
        .ra-list-compact { text-align: left; max-width: 480px; margin: 0 auto; border: 1px solid var(--border-color, #e2e8f0); border-radius: 10px; overflow: hidden; }

        /* Leaderboard professores */
        .ra-leaderboard { display: flex; flex-direction: column; gap: .65rem; }
        .ra-leader-row {
            display: flex; align-items: flex-start; gap: .75rem;
            padding: .85rem 1rem; border-radius: 12px;
            border: 1px solid var(--border-color, #e2e8f0);
            background: var(--bg-card, #fff);
            transition: box-shadow .15s;
        }
        .ra-leader-row:hover { box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .ra-leader-row.is-top {
            border-color: rgba(239,68,68,.35);
            background: linear-gradient(90deg, rgba(239,68,68,.04) 0%, #fff 40%);
        }
        .ra-leader-rank {
            flex-shrink: 0; width: 1.75rem; font-size: .875rem;
            font-weight: 800; color: var(--text-muted, #94a3b8); padding-top: .35rem;
        }
        .ra-leader-avatar {
            flex-shrink: 0; width: 2.5rem; height: 2.5rem; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: .75rem; font-weight: 700;
            background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff;
        }
        .ra-leader-body { flex: 1; min-width: 0; }
        .ra-leader-top {
            display: flex; flex-wrap: wrap; align-items: baseline;
            justify-content: space-between; gap: .25rem .75rem; margin-bottom: .4rem;
        }
        .ra-leader-name { font-size: .875rem; font-weight: 700; line-height: 1.3; word-break: break-word; }
        .ra-leader-stat { font-size: .875rem; font-weight: 800; color: #dc2626; white-space: nowrap; }
        .ra-leader-bar {
            height: 6px; border-radius: 3px;
            background: #fee2e2; overflow: hidden; margin-bottom: .35rem;
        }
        .ra-leader-bar-fill {
            height: 100%; border-radius: 3px;
            background: linear-gradient(90deg, #f87171, #dc2626);
            transition: width .3s ease;
        }
        .ra-leader-meta { font-size: .72rem; color: var(--text-muted, #64748b); line-height: 1.4; }
        .ra-footnote { font-size: .75rem; color: var(--text-muted, #64748b); margin: 1rem 0 0; }
    </style>
    @endpush

    @php
        $periodoLabel = $periodo === 'anual' ? 'Anual (pauta T3)' : ($trimestresOpts[$trimestre] ?? "T{$trimestre}");
        $queryBase = array_filter([
            'periodo' => $periodo,
            'trimestre' => $trimestre,
            'curso_id' => $cursoId,
            'turma_id' => $turmaId,
            'ano_lectivo' => $anoLectivo,
        ]);
        $t3Oficial = ($modo_aproveitamento ?? '') === 't3_oficial';
    @endphp

    <div class="relatorio-page container-fluid px-3 px-md-4 pb-4">

    {{-- Filtros --}}
    <div class="filter-card p-3 mb-4">
        <form method="GET" action="{{ route('relatorios.academico') }}" class="row g-2 align-items-end">

            {{-- Selector Trimestral / Anual --}}
            <div class="col-12 col-sm-auto">
                <label class="form-label small fw-semibold mb-1 d-block">Período</label>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('relatorios.academico', array_merge(request()->only(['trimestre','curso_id','turma_id','ano_lectivo']), ['periodo'=>'trimestral'])) }}"
                       class="periodo-tab {{ $periodo !== 'anual' ? 'active' : '' }}">
                        Trimestral
                    </a>
                    <a href="{{ route('relatorios.academico', array_merge(request()->only(['curso_id','turma_id','ano_lectivo']), ['periodo'=>'anual'])) }}"
                       class="periodo-tab {{ $periodo === 'anual' ? 'active' : '' }}">
                        Anual
                    </a>
                </div>
                <input type="hidden" name="periodo" value="{{ $periodo }}">
            </div>

            {{-- Trimestre (só visível no modo trimestral) --}}
            @if($periodo !== 'anual')
            <div class="col-6 col-sm-4 col-md-2">
                <label class="form-label small fw-semibold mb-1">Trimestre</label>
                <select name="trimestre" class="form-select form-select-sm">
                    @foreach($trimestresOpts as $val => $label)
                        <option value="{{ $val }}" @selected($val == $trimestre)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            {{-- Ano lectivo --}}
            <div class="col-6 col-sm-4 col-md-2">
                <label class="form-label small fw-semibold mb-1">Ano lectivo</label>
                <select name="ano_lectivo" class="form-select form-select-sm">
                    @foreach($anosLectivos as $id => $nome)
                        <option value="{{ $nome }}" @selected($nome == $anoLectivo)>{{ $nome }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Curso --}}
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-semibold mb-1">Curso</label>
                <select name="curso_id" class="form-select form-select-sm" id="curso_id">
                    <option value="">Todos os cursos</option>
                    @foreach($cursos as $c)
                        <option value="{{ $c->id }}" @selected($c->id == $cursoId)>{{ $c->nome }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Turma --}}
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-semibold mb-1">Turma</label>
                <select name="turma_id" class="form-select form-select-sm">
                    <option value="">Todas as turmas</option>
                    @foreach($turmasFiltro as $t)
                        <option value="{{ $t->id }}" @selected($t->id == $turmaId)>{{ $t->nome }} — {{ $t->curso?->nome }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-sm-auto d-flex gap-2 ms-sm-auto">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1 flex-sm-grow-0">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <a href="{{ route('relatorios.academico-pdf', $queryBase) }}"
                   class="btn btn-danger btn-sm" target="_blank" title="Exportar PDF">
                    <i class="bi bi-file-earmark-pdf"></i>
                </a>
            </div>
        </form>
    </div>

    {{-- Período activo --}}
    <div class="mb-3 d-flex flex-wrap gap-2">
        <span class="badge bg-primary fs-6 px-3 py-2">
            <i class="bi bi-calendar3 me-1"></i>
            {{ $anoLectivo }} — {{ $periodoLabel }}
        </span>
        @if($cursoId && $cursos->find($cursoId))
        <span class="badge bg-secondary fs-6 px-3 py-2">
            {{ $cursos->find($cursoId)->nome }}
        </span>
        @endif
        @if($turmaId && $turmas->firstWhere('id', $turmaId))
        <span class="badge bg-info fs-6 px-3 py-2">
            {{ $turmas->firstWhere('id', $turmaId)->nome }}
        </span>
        @endif
    </div>

    {{-- ── CARDS DE RESUMO ── --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card card h-100 p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:#eff6ff;">
                        <i class="bi bi-people text-primary"></i>
                    </div>
                    <div>
                        <div class="fs-2 fw-bold lh-1">{{ $total_turmas }}</div>
                        <div class="text-muted small">Turmas</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card card h-100 p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:#f0fdf4;">
                        <i class="bi bi-person-badge text-success"></i>
                    </div>
                    <div>
                        <div class="fs-2 fw-bold lh-1">{{ $aproveitamento['total'] }}</div>
                        <div class="text-muted small">Total Alunos</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card card h-100 p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:#f0fdf4;">
                        <i class="bi bi-check-circle text-success"></i>
                    </div>
                    <div>
                        <div class="fs-2 fw-bold lh-1 text-success">{{ $aproveitamento['activos'] }}</div>
                        <div class="text-muted small">Activos</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card card h-100 p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:#fff7ed;">
                        <i class="bi bi-person-dash text-warning"></i>
                    </div>
                    <div>
                        <div class="fs-2 fw-bold lh-1 text-warning">{{ $aproveitamento['desistentes'] }}</div>
                        <div class="text-muted small">Desistentes</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── APROVEITAMENTO ── --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-6">
            <div class="stat-card card h-100 p-3">
                <h6 class="fw-semibold mb-3">
                    <i class="bi bi-bar-chart-fill text-primary me-2"></i>
                    Aproveitamento — {{ $periodoLabel }}
                </h6>
                @php
                    $baseCalc = max($aproveitamento['activos'], 1);
                    $pctCom   = round($aproveitamento['comAproveitamento'] / $baseCalc * 100);
                    $pctSem   = round($aproveitamento['semAproveitamento'] / $baseCalc * 100);
                @endphp

                @if($t3Oficial)
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <div class="text-center flex-fill bg-success bg-opacity-10 rounded p-2">
                            <div class="fs-4 fw-bold text-success">{{ $aproveitamento['transita'] + $aproveitamento['aprovado'] }}</div>
                            <div class="small text-muted">Transitam / Aprovados</div>
                        </div>
                        <div class="text-center flex-fill bg-warning bg-opacity-10 rounded p-2">
                            <div class="fs-4 fw-bold text-warning">{{ $aproveitamento['recuperacao'] }}</div>
                            <div class="small text-muted">Transita c/ Def.</div>
                        </div>
                        <div class="text-center flex-fill bg-danger bg-opacity-10 rounded p-2">
                            <div class="fs-4 fw-bold text-danger">{{ $aproveitamento['reprova'] }}</div>
                            <div class="small text-muted">N/TRANSITA / Reprovados</div>
                        </div>
                        <div class="text-center flex-fill bg-secondary bg-opacity-10 rounded p-2">
                            <div class="fs-4 fw-bold text-secondary">{{ $aproveitamento['pendente'] ?? 0 }}</div>
                            <div class="small text-muted">Pendentes</div>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Dados da <strong>pauta T3 oficial</strong> (CA/CFD com substituições).
                        10.ª/11.ª: TRANSITA · TRANS. C/DEF. (2 contínuas) · RECUP. (terminais) · N/TRANSITA (&gt;4 neg.).
                        12.ª: APROVADO / REPROVADO (CFD).
                    </p>
                @else
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small text-success fw-semibold">C/ Aproveitamento</span>
                        <span class="small fw-bold">{{ $aproveitamento['comAproveitamento'] }} <span class="text-muted">({{ $pctCom }}%)</span></span>
                    </div>
                    <div class="progress progress-bar-custom">
                        <div class="progress-bar bg-success" style="width:{{ $pctCom }}%"></div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small text-danger fw-semibold">S/ Aproveitamento</span>
                        <span class="small fw-bold">{{ $aproveitamento['semAproveitamento'] }} <span class="text-muted">({{ $pctSem }}%)</span></span>
                    </div>
                    <div class="progress progress-bar-custom">
                        <div class="progress-bar bg-danger" style="width:{{ $pctSem }}%"></div>
                    </div>
                </div>

                <div class="d-flex gap-3 mt-3">
                    <div class="text-center flex-fill bg-success bg-opacity-10 rounded p-2">
                        <div class="fs-4 fw-bold text-success">{{ $aproveitamento['comAproveitamento'] }}</div>
                        <div class="small text-muted">Aprovados</div>
                    </div>
                    <div class="text-center flex-fill bg-danger bg-opacity-10 rounded p-2">
                        <div class="fs-4 fw-bold text-danger">{{ $aproveitamento['semAproveitamento'] }}</div>
                        <div class="small text-muted">Reprovados</div>
                    </div>
                    <div class="text-center flex-fill bg-warning bg-opacity-10 rounded p-2">
                        <div class="fs-4 fw-bold text-warning">{{ $aproveitamento['desistentes'] }}</div>
                        <div class="small text-muted">Desistentes</div>
                    </div>
                </div>

                @if($periodo === 'anual')
                <p class="text-muted small mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Modo anual: usa a pauta T3 com notas finais efectivas (recuperação, recurso, melhoria).
                </p>
                @else
                <p class="text-muted small mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Reprovação trimestral: mais de {{ \App\Models\Configuracao::get('max_disciplinas_reprovacao', 2) }} disciplinas com MT &lt; {{ \App\Models\Configuracao::get('nota_minima_aprovacao', 10) }}.
                </p>
                @endif
                @endif
            </div>
        </div>

        {{-- Turmas resumo --}}
        <div class="col-12 col-lg-6">
            <div class="stat-card card h-100 p-3">
                <h6 class="fw-semibold mb-3"><i class="bi bi-table text-primary me-2"></i>Turmas ({{ $total_turmas }})</h6>
                @if($turmas->isEmpty())
                    <p class="text-muted small">Sem turmas para os filtros seleccionados.</p>
                @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="small">Turma</th>
                                <th class="small">Curso</th>
                                <th class="small text-center">Alunos</th>
                                <th class="small text-center">Classe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($turmas as $t)
                            <tr>
                                <td class="small fw-semibold">
                                    <a href="{{ route('pautas.trimestral-completa', ['turma_id' => $t->id, 'trimestre' => $periodo === 'anual' ? 3 : ($trimestre ?? 3), 'ano_lectivo' => $anoLectivo]) }}"
                                       class="text-decoration-none">
                                        {{ $t->nome }}
                                    </a>
                                </td>
                                <td class="small text-muted">{{ $t->curso?->nome ?? '-' }}</td>
                                <td class="small text-center">{{ $t->alunos->count() }}</td>
                                <td class="small text-center">{{ $t->classe?->nome ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

    @include('relatorios.partials.ranking-alunos')

    @include('relatorios.partials.professores-negativas')

    {{-- Botão de exportação --}}
    <div class="d-flex justify-content-end mb-4">
        <a href="{{ route('relatorios.academico-pdf', $queryBase) }}"
           class="btn btn-danger btn-sm" target="_blank">
            <i class="bi bi-file-earmark-pdf me-1"></i>Exportar PDF
        </a>
    </div>

    </div>{{-- /.relatorio-page --}}
</x-app-layout>
