<x-app-layout>
    @php
        $tipoPeriodo = $planificacao->tipo_periodo ?? 'quinzenal';
        $labelPeriodos = $tipoPeriodo === 'mensal' ? 'Meses' : 'Quinzenas';
        $labelPeriodo = $tipoPeriodo === 'mensal' ? 'Mês' : 'Quinzena';
    @endphp
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">{{ $planificacao->disciplina->nome ?? '' }} — {{ $planificacao->turma->nome ?? '' }}</h2>
            <span class="badge bg-primary">{{ $planificacao->estado }}</span>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-calendar-week" variant="info" title="Planificação" id="tip-planificacao-show" compact>
                Fluxo: rascunho → submeter → aprovação do coordenador da disciplina (classe) → activar calendário.
                Pode submeter mesmo se for coordenador desta disciplina; nesse caso <strong>não pode auto-aprovar</strong> — outro coordenador ou a direcção deve aprovar.
            </x-tip-card>

            @if(!empty($conteudoMinimo) && auth()->user()?->can('conteudos.ver'))
                <x-tip-card icon="bi-list-check" variant="success" title="Conteúdos mínimos oficiais" id="tip-planificacao-conteudos" compact>
                    Existe um programa oficial publicado para {{ $planificacao->disciplina?->nome }} · {{ $planificacao->turma?->classe?->nome ?? $conteudoMinimo->classe?->nome }} (v{{ $conteudoMinimo->versao }}).
                    <a href="{{ route('pedagogico.conteudos.show', $conteudoMinimo) }}" class="alert-link">Ver conteúdos mínimos</a>
                </x-tip-card>
            @endif

            @foreach (['success' => 'success', 'error' => 'danger'] as $key => $variant)
                @if(session($key))<div class="alert alert-{{ $variant }}">{{ session($key) }}</div>@endif
            @endforeach

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <a href="{{ route('pedagogico.aulas.index', $planificacao) }}" class="btn btn-outline-primary w-100">Aulas</a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('pedagogico.planos.index', $planificacao) }}" class="btn btn-outline-primary w-100">Planos de Aula</a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('pedagogico.execucao.show', $planificacao) }}" class="btn btn-outline-primary w-100">Execução</a>
                </div>
                @can('pedagogico.planificacao.editar')
                <div class="col-md-3">
                    <a href="{{ route('pedagogico.planificacoes.edit', $planificacao) }}" class="btn btn-outline-secondary w-100">Editar Wizard</a>
                </div>
                @endcan
            </div>

            <div class="card mb-4">
                <div class="card-header">Resumo</div>
                <div class="card-body row">
                    <div class="col-md-3"><strong>Trimestre:</strong> {{ $planificacao->trimestre }}º</div>
                    <div class="col-md-3"><strong>Tipo:</strong> {{ $tipoPeriodo === 'mensal' ? 'Mensal' : 'Quinzenal' }}</div>
                    <div class="col-md-3"><strong>Professor:</strong> {{ $planificacao->professor->name ?? '-' }}</div>
                    <div class="col-md-3"><strong>Período:</strong> {{ $planificacao->data_inicial?->format('d/m/Y') }} – {{ $planificacao->data_final?->format('d/m/Y') }}</div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">{{ $labelPeriodos }} ({{ $planificacao->quinzenas->count() }})</div>
                <div class="list-group list-group-flush">
                    @forelse($planificacao->quinzenas as $q)
                        <div class="list-group-item">
                            <strong>{{ $labelPeriodo }} {{ $q->numero }}</strong>
                            <span class="text-muted">({{ $q->data_inicial?->format('d/m') }} – {{ $q->data_final?->format('d/m') }})</span>

                            @if($q->temas->isNotEmpty())
                                <ul class="mb-2 mt-2 ps-3">
                                    @foreach($q->temas as $tema)
                                        <li>{{ $tema->rotulo() }}</li>
                                    @endforeach
                                </ul>
                            @elseif($q->tema)
                                <p class="mb-1 mt-1">{{ $q->tema }}</p>
                            @endif

                            @if($q->objetivos_geral)
                                <p class="small mb-1"><strong>Objectivo geral:</strong> {{ $q->objetivos_geral }}</p>
                            @endif
                            @if($q->objetivos_especificos)
                                <p class="small mb-1"><strong>Objectivos específicos:</strong><br>{!! nl2br(e($q->objetivos_especificos)) !!}</p>
                            @endif
                            @if($q->dias->isNotEmpty())
                                <table class="table table-sm table-bordered mt-2 mb-0">
                                    <thead class="table-light">
                                        <tr><th>Data</th><th>Tema</th><th>Obj. geral</th><th>Obj. específicos</th><th>Tempos</th><th>Hora</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach($q->dias->sortBy('ordem') as $dia)
                                        <tr>
                                            <td>{{ $dia->data?->format('d/m/Y') }}</td>
                                            <td>{{ $dia->tema }}</td>
                                            <td>{{ Str::limit($dia->objetivos_geral ?? '—', 40) }}</td>
                                            <td>{{ Str::limit($dia->objetivos_especificos ?? '—', 40) }}</td>
                                            <td>{{ $dia->tempos }}T</td>
                                            <td>{{ $dia->hora_inicio ? substr($dia->hora_inicio, 0, 5) : '—' }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <p class="text-muted small mb-0 mt-1">Sem dias definidos — use o wizard para planificar cada aula.</p>
                            @endif
                        </div>
                    @empty
                        <div class="list-group-item text-muted">Nenhum período definido.</div>
                    @endforelse
                </div>
            </div>

            @can('pedagogico.planificacao.editar')
                @if($planificacao->estado === 'rascunho')
                <form method="POST" action="{{ route('pedagogico.planificacoes.submeter', $planificacao) }}" class="d-inline"
                      data-confirm-title="Submeter planificação"
                      data-confirm="Submeter para aprovação do coordenador de disciplina? Se for o único coordenador desta disciplina/classe, a direcção ou outro coordenador terá de aprovar."
                      data-confirm-ok="Sim, submeter"
                      data-confirm-color="#0d6efd">
                    @csrf
                    <button type="submit" class="btn btn-primary">Submeter para aprovação</button>
                </form>
                @endif
                @if($planificacao->estado === 'aprovada')
                <form method="POST" action="{{ route('pedagogico.planificacoes.activar', $planificacao) }}" class="d-inline"
                      data-confirm-title="Activar planificação"
                      data-confirm="Activar planificação e gerar o calendário de aulas?"
                      data-confirm-ok="Sim, activar"
                      data-confirm-color="#198754">
                    @csrf
                    <button type="submit" class="btn btn-success">Activar Planificação</button>
                </form>
                @endif
                @if($planificacao->estado === 'activo')
                <form method="POST" action="{{ route('pedagogico.planificacoes.regenerar-calendario', $planificacao) }}" class="d-inline"
                      data-confirm-title="Regenerar calendário"
                      data-confirm="Regenerar o calendário de aulas desta planificação?"
                      data-confirm-ok="Sim, regenerar"
                      data-confirm-icon="warning">
                    @csrf
                    <button type="submit" class="btn btn-warning">Regenerar Calendário</button>
                </form>
                @endif
            @endcan
            @can('pedagogico.aprovar')
                @if($planificacao->estado === 'pendente_aprovacao' && (int) $planificacao->user_id !== (int) auth()->id())
                <form method="POST" action="{{ route('pedagogico.planificacoes.aprovar', $planificacao) }}" class="d-inline"
                      data-confirm-title="Aprovar planificação"
                      data-confirm="Confirmar aprovação desta planificação?"
                      data-confirm-ok="Sim, aprovar"
                      data-confirm-color="#198754">
                    @csrf
                    <button type="submit" class="btn btn-success">Aprovar</button>
                </form>
                <form method="POST" action="{{ route('pedagogico.planificacoes.rejeitar', $planificacao) }}" class="d-inline"
                      data-confirm-prompt="Motivo da rejeição"
                      data-confirm-prompt-field="motivo_rejeicao"
                      data-confirm-title="Rejeitar planificação"
                      data-confirm-ok="Rejeitar"
                      data-confirm-danger="1">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm">Rejeitar</button>
                </form>
                @elseif($planificacao->estado === 'pendente_aprovacao' && (int) $planificacao->user_id === (int) auth()->id())
                    <span class="text-muted small align-middle ms-1">Aguarda aprovação de outro coordenador ou da direcção (não pode auto-aprovar).</span>
                @endif
            @endcan
            @if($planificacao->motivo_rejeicao)
                <div class="alert alert-warning mt-3 mb-0 small">Última rejeição: {{ $planificacao->motivo_rejeicao }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
