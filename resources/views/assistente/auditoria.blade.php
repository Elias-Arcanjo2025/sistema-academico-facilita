<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Auditoria do Assistente IA</h2>
            <form method="GET" action="{{ route('assistente.auditoria') }}" class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 small text-muted">Período</label>
                <select name="periodo" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="7" @selected($periodo === 7)>7 dias</option>
                    <option value="30" @selected($periodo === 30)>30 dias</option>
                    <option value="90" @selected($periodo === 90)>90 dias</option>
                    <option value="365" @selected($periodo === 365)>1 ano</option>
                </select>
            </form>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container-fluid">
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card h-100"><div class="card-body">
                        <div class="text-muted small">Perguntas</div>
                        <div class="fs-3 fw-semibold">{{ number_format($resumo['perguntas']) }}</div>
                    </div></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card h-100"><div class="card-body">
                        <div class="text-muted small">Conversas</div>
                        <div class="fs-3 fw-semibold">{{ number_format($resumo['conversas']) }}</div>
                    </div></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card h-100"><div class="card-body">
                        <div class="text-muted small">Tokens</div>
                        <div class="fs-3 fw-semibold">{{ number_format($resumo['tokens_total']) }}</div>
                        <div class="small text-muted">in {{ number_format($resumo['tokens_in']) }} · out {{ number_format($resumo['tokens_out']) }}</div>
                    </div></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card h-100"><div class="card-body">
                        <div class="text-muted small">Feedback</div>
                        <div class="fs-5 fw-semibold text-success">👍 {{ $resumo['feedback_util'] }}</div>
                        <div class="fs-5 fw-semibold text-danger">👎 {{ $resumo['feedback_inutil'] }}</div>
                        <div class="small text-muted mt-1">Recusas perfil: {{ $resumo['recusas_perfil'] }}</div>
                    </div></div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-7">
                    <div class="card h-100">
                        <div class="card-header">Perguntas por dia</div>
                        <div class="card-body">
                            @if($porDia->isEmpty())
                                <p class="text-muted mb-0">Sem actividade neste período.</p>
                            @else
                                @php $max = max(1, $porDia->max('total')); @endphp
                                <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                                    <table class="table table-sm mb-0">
                                        <thead><tr><th>Data</th><th class="text-end">Total</th><th></th></tr></thead>
                                        <tbody>
                                        @foreach($porDia as $dia)
                                            <tr>
                                                <td>{{ \Carbon\Carbon::parse($dia->data)->format('d/m/Y') }}</td>
                                                <td class="text-end">{{ $dia->total }}</td>
                                                <td style="width:45%">
                                                    <div class="progress" style="height:8px">
                                                        <div class="progress-bar" style="width: {{ ($dia->total / $max) * 100 }}%"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card h-100 mb-3">
                        <div class="card-header">Por perfil</div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Perfil</th><th class="text-end">Perguntas</th><th class="text-end">Utilizadores</th></tr></thead>
                                <tbody>
                                @forelse($porPerfil as $linha)
                                    <tr>
                                        <td class="text-capitalize">{{ $linha->perfil }}</td>
                                        <td class="text-end">{{ $linha->perguntas }}</td>
                                        <td class="text-end">{{ $linha->utilizadores }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted px-3 py-3">Sem dados.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">Top utilizadores</div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Utilizador</th><th class="text-end">Perguntas</th><th class="text-end">Tokens</th></tr></thead>
                                <tbody>
                                @forelse($topUtilizadores as $u)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $u->nome }}</div>
                                            <div class="small text-muted">{{ $u->cargos }}</div>
                                        </td>
                                        <td class="text-end">{{ $u->perguntas }}</td>
                                        <td class="text-end">{{ number_format($u->tokens) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted px-3 py-3">Sem dados.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Conversas recentes</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Utilizador</th>
                                    <th class="text-end">Msgs</th>
                                    <th class="text-end">Tokens</th>
                                    <th>Actualizado</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($conversas as $c)
                                <tr>
                                    <td>{{ $c->titulo ?: 'Sem título' }}</td>
                                    <td>{{ $c->user?->name ?? '—' }}</td>
                                    <td class="text-end">{{ $c->mensagens_count }}</td>
                                    <td class="text-end">{{ number_format(($c->tokens_in_sum ?? 0) + ($c->tokens_out_sum ?? 0)) }}</td>
                                    <td>{{ $c->updated_at?->format('d/m/Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted px-3 py-3">Sem conversas neste período.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($conversas->hasPages())
                    <div class="card-footer">{{ $conversas->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
