<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Radar Pedagógico</h2>
            <a href="{{ route('estatisticas.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-bar-chart me-1"></i> Estatísticas
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Análise', 'url' => route('radar.index')],
                ['label' => 'Radar Pedagógico', 'url' => route('radar.index')],
            ]" />

            <x-tip-card icon="bi-radar" variant="info" title="Radar Pedagógico" id="tip-radar-index" compact>
                Visão rápida da saúde da escola, turmas em risco e MAC/PP em atraso.
                Clique nos cartões ou nas linhas para abrir a turma, a mini-pauta ou o perfil do professor.
                @if(!empty($dados['ano_lectivo']))
                    · {{ is_object($dados['ano_lectivo']) ? ($dados['ano_lectivo']->nome ?? $dados['ano_lectivo']) : $dados['ano_lectivo'] }} · T{{ $dados['trimestre'] }}
                @endif
            </x-tip-card>

            @if(!empty($dados['sem_ano_lectivo']))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>Ano lectivo ainda não configurado.</strong>
                    Crie e active um ano em Configurações para ver turmas em risco e atrasos de MAC/PP.
                    <a href="{{ route('configuracoes.index') }}#anos-lectivos-section" class="alert-link ms-1">Criar ano lectivo</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @php
                $score = $dados['saude_escola']['score'] ?? $dados['saude_escola']['pontuacao'] ?? null;
                $scoreLabel = $dados['saude_escola']['score_label'] ?? $dados['saude_escola']['label'] ?? $dados['saude_escola']['estado'] ?? '';
                $nTurmasRisco = count($dados['turmas_risco'] ?? []);
                $nAtrasos = count($dados['professores_atraso'] ?? []);
                $nAlunosRisco = count($dados['alunos_risco'] ?? []);
                $nAbandono = (int) ($dados['abandono']['total'] ?? 0);
                $trimestre = $dados['trimestre'] ?? null;
            @endphp

            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <a href="{{ route('estatisticas.index') }}" class="card h-100 text-decoration-none radar-kpi-card">
                        <div class="card-body">
                            <div class="text-muted small mb-1"><i class="bi bi-heart-pulse me-1"></i>Saúde da escola</div>
                            <div class="fs-3 fw-semibold text-dark">{{ $score ?? '—' }}</div>
                            <div class="small text-muted">{{ $scoreLabel }}</div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="#radar-alunos-risco" class="card h-100 text-decoration-none radar-kpi-card">
                        <div class="card-body">
                            <div class="text-muted small mb-1"><i class="bi bi-person-exclamation me-1"></i>Alunos em risco</div>
                            <div class="fs-3 fw-semibold text-danger">{{ $nAlunosRisco }}</div>
                            <div class="small text-muted">≥3 MTs negativas</div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="#radar-turmas-risco" class="card h-100 text-decoration-none radar-kpi-card">
                        <div class="card-body">
                            <div class="text-muted small mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Turmas em risco</div>
                            <div class="fs-3 fw-semibold text-danger">{{ $nTurmasRisco }}</div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="#radar-abandono" class="card h-100 text-decoration-none radar-kpi-card">
                        <div class="card-body">
                            <div class="text-muted small mb-1"><i class="bi bi-door-open me-1"></i>Abandono / desistência</div>
                            <div class="fs-3 fw-semibold text-warning">{{ $nAbandono }}</div>
                            <div class="small text-muted">{{ $dados['abandono']['desistentes'] ?? 0 }} oficiais · {{ $dados['abandono']['auto_desistentes'] ?? 0 }} auto</div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-6" id="radar-alunos-risco">
                    <div class="card h-100">
                        <div class="card-header"><strong>Alunos em risco</strong></div>
                        <div class="card-body p-0">
                            @if($nAlunosRisco === 0)
                                <p class="text-muted small p-3 mb-0">Nenhum aluno com 3+ MTs negativas neste trimestre.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead><tr><th>Aluno</th><th>Turma</th><th>MTs&lt;10</th></tr></thead>
                                        <tbody>
                                            @foreach($dados['alunos_risco'] as $a)
                                                <tr>
                                                    <td>
                                                        <a href="{{ route('alunos.show', $a['aluno_id']) }}">{{ $a['nome'] }}</a>
                                                        <span class="badge bg-{{ $a['nivel'] === 'critico' ? 'danger' : 'warning' }}">{{ $a['nivel'] }}</span>
                                                    </td>
                                                    <td class="small">{{ $a['turma'] }}</td>
                                                    <td>{{ $a['mts_negativas'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-lg-6" id="radar-abandono">
                    <div class="card h-100">
                        <div class="card-header"><strong>Abandono escolar</strong></div>
                        <div class="card-body p-0">
                            @if(empty($dados['abandono']['alunos']))
                                <p class="text-muted small p-3 mb-0">Sem desistentes registados neste ano.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead><tr><th>Aluno</th><th>Turma</th><th>Motivo</th></tr></thead>
                                        <tbody>
                                            @foreach($dados['abandono']['alunos'] as $a)
                                                <tr>
                                                    <td><a href="{{ route('alunos.show', $a['aluno_id']) }}">{{ $a['nome'] }}</a></td>
                                                    <td class="small">{{ $a['turma'] }}</td>
                                                    <td class="small">{{ $a['motivo'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-6" id="radar-turmas-risco">
                    @if($nTurmasRisco === 0)
                        <div class="card h-100">
                            <div class="card-body">
                                <x-empty-state
                                    icon="bi-check2-circle"
                                    message="Nenhuma turma em risco relevante."
                                    :can-create="false"
                                />
                            </div>
                        </div>
                    @else
                        <x-data-table
                            title="Turmas em risco (antes do conselho)"
                            icon="bi-exclamation-triangle"
                            :badge="$nTurmasRisco"
                            :searchable="true">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Turma</th>
                                        <th>Nível</th>
                                        <th class="text-end">% neg.</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($dados['turmas_risco'] as $t)
                                        <tr>
                                            <td>
                                                <a href="{{ route('turmas.show', $t['turma_id']) }}" class="fw-semibold text-decoration-none">
                                                    {{ $t['turma'] }}
                                                </a>
                                                <div class="small text-muted">{{ $t['curso'] }} · {{ $t['alunos'] }} alunos</div>
                                            </td>
                                            <td>
                                                @php
                                                    $nivelBadge = match ($t['nivel']) {
                                                        'critico' => 'bg-danger',
                                                        'alto' => 'bg-warning text-dark',
                                                        default => 'bg-secondary',
                                                    };
                                                @endphp
                                                <span class="badge {{ $nivelBadge }}">{{ $t['nivel'] }}</span>
                                            </td>
                                            <td class="text-end fw-semibold">{{ $t['taxa_negativas'] }}%</td>
                                            <td class="text-end">
                                                <div class="d-inline-flex gap-1">
                                                    <a href="{{ route('turmas.show', $t['turma_id']) }}" class="btn btn-sm btn-outline-primary" title="Ver turma">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    @can('mini-pautas.ver')
                                                        <a href="{{ route('mini-pautas.index', ['turma_id' => $t['turma_id'], 'trimestre' => $trimestre]) }}" class="btn btn-sm btn-outline-secondary" title="Mini-pautas">
                                                            <i class="bi bi-table"></i>
                                                        </a>
                                                    @endcan
                                                    @can('pautas.ver')
                                                        <a href="{{ route('pautas.index', ['turma_id' => $t['turma_id'], 'trimestre' => $trimestre]) }}" class="btn btn-sm btn-outline-secondary" title="Pauta">
                                                            <i class="bi bi-grid-3x3"></i>
                                                        </a>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-data-table>
                    @endif
                </div>

                <div class="col-lg-6" id="radar-mac-pp">
                    @if($nAtrasos === 0)
                        <div class="card h-100">
                            <div class="card-body">
                                <x-empty-state
                                    icon="bi-check2-circle"
                                    message="Sem atrasos relevantes de MAC/PP."
                                    :can-create="false"
                                />
                            </div>
                        </div>
                    @else
                        <x-data-table
                            title="MAC / PP em atraso"
                            icon="bi-hourglass-split"
                            :badge="$nAtrasos"
                            :searchable="true">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Professor</th>
                                        <th>Turma / Disc.</th>
                                        <th>MAC</th>
                                        <th>PP</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($dados['professores_atraso'] as $p)
                                        <tr>
                                            <td>
                                                @if(!empty($p['user_id']))
                                                    <a href="{{ route('users.show', $p['user_id']) }}" class="fw-semibold text-decoration-none">
                                                        {{ $p['professor'] }}
                                                    </a>
                                                @else
                                                    <span class="fw-semibold">{{ $p['professor'] }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if(!empty($p['turma_id']))
                                                    <a href="{{ route('turmas.show', $p['turma_id']) }}" class="text-decoration-none">{{ $p['turma'] }}</a>
                                                @else
                                                    {{ $p['turma'] }}
                                                @endif
                                                <div class="small text-muted">{{ $p['disciplina'] }}</div>
                                            </td>
                                            <td class="{{ !empty($p['falta_mac']) ? 'text-danger fw-semibold' : '' }}">
                                                {{ $p['mac_lancados'] }}/{{ $p['alunos'] }}
                                            </td>
                                            <td class="{{ !empty($p['falta_pp']) ? 'text-danger fw-semibold' : '' }}">
                                                {{ $p['pp_lancados'] }}/{{ $p['alunos'] }}
                                            </td>
                                            <td class="text-end">
                                                <div class="d-inline-flex gap-1">
                                                    @can('mini-pautas.ver')
                                                        @if(!empty($p['turma_id']) && !empty($p['disciplina_id']))
                                                            <a href="{{ route('mini-pautas.index', [
                                                                'turma_id' => $p['turma_id'],
                                                                'disciplina_id' => $p['disciplina_id'],
                                                                'trimestre' => $trimestre,
                                                            ]) }}" class="btn btn-sm btn-primary" title="Abrir mini-pauta">
                                                                <i class="bi bi-pencil-square"></i>
                                                            </a>
                                                        @endif
                                                    @endcan
                                                    @can('notas.lancar')
                                                        @if(!empty($p['turma_id']) && !empty($p['disciplina_id']))
                                                            <a href="{{ route('notas.lancar', [
                                                                'turma_id' => $p['turma_id'],
                                                                'disciplina_id' => $p['disciplina_id'],
                                                                'trimestre' => $trimestre,
                                                            ]) }}" class="btn btn-sm btn-outline-primary" title="Lançar notas">
                                                                <i class="bi bi-plus-lg"></i>
                                                            </a>
                                                        @endif
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-data-table>
                    @endif
                </div>
            </div>

            <div class="card" id="radar-benchmark">
                <div class="card-header fw-semibold">
                    <i class="bi bi-globe2 me-1"></i> Benchmark Facilita (rede)
                </div>
                <div class="card-body">
                    @if(!empty($dados['benchmark']['disponivel']))
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="text-muted small">Escolas na rede</div>
                                <div class="fs-5 fw-semibold">{{ $dados['benchmark']['escolas_hub'] }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Média de alunos</div>
                                <div class="fs-5 fw-semibold">{{ $dados['benchmark']['alunos_media_rede'] }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Mediana de alunos</div>
                                <div class="fs-5 fw-semibold">{{ $dados['benchmark']['alunos_mediana_rede'] }}</div>
                            </div>
                        </div>
                        <p class="small text-muted mb-0 mt-3">{{ $dados['benchmark']['nota'] }}</p>
                    @else
                        <p class="text-muted mb-0">{{ $dados['benchmark']['motivo'] ?? 'Indisponível.' }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('styles')
    <style>
        .radar-kpi-card {
            transition: transform .15s ease, box-shadow .15s ease;
            border: 1px solid var(--border-color, #e2e8f0);
        }
        .radar-kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, .08);
            border-color: rgba(79, 70, 229, .35);
        }
        [data-theme="dark"] .radar-kpi-card:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, .35);
        }
        [data-theme="dark"] .radar-kpi-card .text-dark {
            color: var(--text-dark) !important;
        }
    </style>
    @endpush
</x-app-layout>
