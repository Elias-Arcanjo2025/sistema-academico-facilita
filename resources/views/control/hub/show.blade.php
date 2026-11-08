@extends('layouts.control-hub')

@section('title', $escola->nome.' — Controlo')
@section('page-title', $escola->nome)

@section('content')
<div class="container-fluid py-3">
    <div class="mb-3">
        <a href="{{ route('control.hub.index') }}" class="text-decoration-none small">&larr; Todas as escolas</a>
    </div>

    @if(session('control_secret_once'))
        <div class="alert alert-warning">
            <strong>Secret (mostrar uma vez):</strong>
            <code class="user-select-all">{{ session('control_secret_once') }}</code>
            <div class="small mt-2 mb-0">
                Configure na escola:<br>
                <code>CONTROL_MODE=school</code><br>
                <code>CONTROL_HUB_URL={{ config('app.url') }}</code><br>
                <code>CONTROL_INSTALLATION_ID={{ $escola->installation_id }}</code><br>
                <code>CONTROL_SECRET={{ session('control_secret_once') }}</code>
            </div>
        </div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $escola->nome }}</h1>
            <div class="text-muted small">ID: <code>{{ $escola->installation_id }}</code></div>
            @php
                $badge = match($escola->status) {
                    'activo' => 'success',
                    'em_cobranca' => 'info',
                    'em_atraso' => 'warning',
                    'bloqueado' => 'danger',
                    default => 'secondary',
                };
            @endphp
            <span class="badge text-bg-{{ $badge }} mt-2">{{ \App\Models\Control\ControlEscola::statuses()[$escola->status] ?? $escola->status }}</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('control.hub.escolas.sync', $escola) }}">@csrf
                <button class="btn btn-outline-info btn-sm" @disabled(! $escola->app_url) title="{{ $escola->app_url ? 'Pedir sync à escola' : 'Define a URL da escola primeiro' }}">
                    <i class="bi bi-arrow-repeat"></i> Sincronizar agora
                </button>
            </form>
            @if(! $cobrancaAberta && $escola->status !== 'bloqueado')
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCobranca">
                    Criar cobrança
                </button>
            @endif
            @if($escola->status !== 'bloqueado')
                <form method="POST" action="{{ route('control.hub.escolas.atraso', $escola) }}">@csrf
                    <button class="btn btn-outline-warning btn-sm">Marcar atraso</button>
                </form>
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalBloquear">
                    Bloquear escola
                </button>
            @else
                <form method="POST" action="{{ route('control.hub.escolas.desbloquear', $escola) }}">@csrf
                    <button class="btn btn-success btn-sm">Desbloquear</button>
                </form>
            @endif
        </div>
    </div>

    @if($cobrancaAberta)
        <div class="alert alert-{{ $escola->status === 'em_atraso' ? 'danger' : 'warning' }} d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong>Cobrança {{ $cobrancaAberta->referencia }}</strong>
                — {{ number_format((float) $cobrancaAberta->valor_total, 2, ',', '.') }}
                ({{ $cobrancaAberta->total_alunos }} alunos)
                @if($cobrancaAberta->vencimento_em)
                    · vence {{ $cobrancaAberta->vencimento_em->format('d/m/Y') }}
                @endif
                <div class="small mt-1 opacity-75">
                    @if($cobrancaAberta->aviso_em)
                        Aviso: {{ $cobrancaAberta->aviso_em->format('d/m/Y H:i') }}
                    @endif
                    @if($cobrancaAberta->atraso_em)
                        · Atraso: {{ $cobrancaAberta->atraso_em->format('d/m/Y H:i') }}
                    @endif
                    @if($cobrancaAberta->bloqueio_em)
                        · Bloqueio: {{ $cobrancaAberta->bloqueio_em->format('d/m/Y H:i') }}
                    @endif
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalPagar">Marcar como pago</button>
                <form method="POST" action="{{ route('control.hub.cobrancas.cancelar', $cobrancaAberta) }}" onsubmit="return confirm('Cancelar esta cobrança?')">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary">Cancelar</button>
                </form>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="border rounded-3 p-3">
                <div class="text-muted small">Alunos activos</div>
                <div class="fs-4 fw-semibold">{{ $escola->total_alunos }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="border rounded-3 p-3">
                <div class="text-muted small">Professores</div>
                <div class="fs-4 fw-semibold">{{ $escola->total_professores }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="border rounded-3 p-3">
                <div class="text-muted small">Cursos / Turmas / Salas</div>
                <div class="fs-5 fw-semibold">{{ $escola->total_cursos }} / {{ $escola->total_turmas }} / {{ $escola->total_salas }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="border rounded-3 p-3">
                <div class="text-muted small">Valor estimado</div>
                <div class="fs-4 fw-semibold">{{ number_format($escola->valorEstimado(), 2, ',', '.') }}</div>
                <div class="small text-muted">{{ number_format($escola->preco_anual_aluno, 2, ',', '.') }} / aluno</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="border rounded-3 p-3 mb-3">
                <h2 class="h6">Dados da instalação</h2>
                <form method="POST" action="{{ route('control.hub.escolas.update', $escola) }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-2">
                        <label class="form-label small">Nome</label>
                        <input type="text" name="nome" value="{{ old('nome', $escola->nome) }}" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">URL</label>
                        <input type="url" name="app_url" value="{{ old('app_url', $escola->app_url) }}" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Email</label>
                        <input type="email" name="contacto_email" value="{{ old('contacto_email', $escola->contacto_email) }}" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Telefone</label>
                        <input type="text" name="contacto_telefone" value="{{ old('contacto_telefone', $escola->contacto_telefone) }}" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Preço anual / aluno</label>
                        <input type="number" step="0.01" min="0" name="preco_anual_aluno" value="{{ old('preco_anual_aluno', $escola->preco_anual_aluno) }}" class="form-control form-control-sm">
                    </div>
                    <div class="border rounded-2 p-2 mb-2">
                        <div class="small fw-semibold mb-1">Prazos por defeito (minutos)</div>
                        <div class="small text-muted mb-2">Usados ao criar cobranças se não fores preenchidos no modal. Contam a partir da criação.</div>
                        <div class="row g-2">
                            <div class="col-4">
                                <label class="form-label small mb-0">Aviso</label>
                                <input type="number" min="0" name="minutos_ate_aviso" value="{{ old('minutos_ate_aviso', $escola->minutos_ate_aviso ?? 0) }}" class="form-control form-control-sm" placeholder="0">
                            </div>
                            <div class="col-4">
                                <label class="form-label small mb-0">Atraso</label>
                                <input type="number" min="0" name="minutos_ate_atraso" value="{{ old('minutos_ate_atraso', $escola->minutos_ate_atraso) }}" class="form-control form-control-sm" placeholder="—">
                            </div>
                            <div class="col-4">
                                <label class="form-label small mb-0">Bloqueio</label>
                                <input type="number" min="0" name="minutos_ate_bloqueio" value="{{ old('minutos_ate_bloqueio', $escola->minutos_ate_bloqueio) }}" class="form-control form-control-sm" placeholder="—">
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Notas internas</label>
                        <textarea name="notas_internas" rows="3" class="form-control form-control-sm">{{ old('notas_internas', $escola->notas_internas) }}</textarea>
                    </div>
                    <button class="btn btn-sm btn-primary">Guardar</button>
                </form>
                <hr>
                <form method="POST" action="{{ route('control.hub.escolas.secret', $escola) }}" onsubmit="return confirm('Gerar novo secret? O anterior deixa de funcionar.')">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary">Regenerar secret</button>
                </form>
                <div class="small text-muted mt-3">
                    <div>Versão: {{ $escola->app_version ?: '—' }}</div>
                    <div>Ano lectivo: {{ $escola->ano_lectivo ?: '—' }}</div>
                    <div>Heartbeat: {{ $escola->last_heartbeat_at?->format('d/m/Y H:i') ?? 'Nunca' }}</div>
                    <div>Sync alunos: {{ $escola->last_sync_at?->format('d/m/Y H:i') ?? 'Nunca' }}</div>
                    @if($escola->sync_requested_at)
                        <div class="text-info">Sync pedido: {{ $escola->sync_requested_at->format('d/m/Y H:i') }}</div>
                    @endif
                    @if($escola->bloqueio_motivo)
                        <div class="text-danger mt-2">Motivo bloqueio: {{ $escola->bloqueio_motivo }}</div>
                    @endif
                </div>
            </div>

            <div class="border rounded-3 p-3">
                <h2 class="h6">Histórico</h2>
                <ul class="list-unstyled mb-0 small">
                    @forelse($eventos as $evento)
                        <li class="mb-2 pb-2 border-bottom">
                            <strong>{{ $evento->accao }}</strong>
                            @if($evento->status_anterior || $evento->status_novo)
                                <span class="text-muted">({{ $evento->status_anterior ?? '—' }} → {{ $evento->status_novo ?? '—' }})</span>
                            @endif
                            <div class="text-muted">{{ $evento->created_at->format('d/m/Y H:i') }} @if($evento->user)— {{ $evento->user->name }}@endif</div>
                            @if($evento->motivo)<div>{{ $evento->motivo }}</div>@endif
                        </li>
                    @empty
                        <li class="text-muted">Sem eventos.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="border rounded-3 p-3 mb-3">
                <h2 class="h6 mb-3">Cobranças</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Referência</th>
                                <th>Ano</th>
                                <th class="text-end">Alunos</th>
                                <th class="text-end">Valor</th>
                                <th>Estado</th>
                                <th>Vencimento</th>
                                <th>Prazos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cobrancas as $c)
                                <tr>
                                    <td><code>{{ $c->referencia }}</code></td>
                                    <td>{{ $c->ano_lectivo ?: '—' }}</td>
                                    <td class="text-end">{{ $c->total_alunos }}</td>
                                    <td class="text-end">{{ number_format((float) $c->valor_total, 2, ',', '.') }}</td>
                                    <td>
                                        @php
                                            $cb = match($c->status) {
                                                'paga' => 'success',
                                                'aberta' => 'warning',
                                                'vencida' => 'danger',
                                                default => 'secondary',
                                            };
                                        @endphp
                                        <span class="badge text-bg-{{ $cb }}">{{ \App\Models\Control\ControlCobranca::statuses()[$c->status] ?? $c->status }}</span>
                                    </td>
                                    <td>{{ $c->vencimento_em?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="small">
                                        @if($c->aviso_em || $c->atraso_em || $c->bloqueio_em)
                                            @if($c->aviso_em)<div>Aviso {{ $c->aviso_em->format('d/m H:i') }}</div>@endif
                                            @if($c->atraso_em)<div>Atraso {{ $c->atraso_em->format('d/m H:i') }}</div>@endif
                                            @if($c->bloqueio_em)<div>Bloq. {{ $c->bloqueio_em->format('d/m H:i') }}</div>@endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-muted text-center py-3">Sem cobranças.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="border rounded-3 p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 mb-0">Alunos sincronizados (facturação)</h2>
                    <span class="text-muted small">{{ $alunos->total() }} registos</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Processo</th>
                                <th>Nome</th>
                                <th>Turma</th>
                                <th>Curso</th>
                                <th>Situação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($alunos as $aluno)
                                <tr>
                                    <td>{{ $aluno->numero_processo ?: '—' }}</td>
                                    <td>{{ $aluno->nome }}</td>
                                    <td>{{ $aluno->turma_codigo ?: $aluno->turma_nome ?: '—' }}</td>
                                    <td>{{ $aluno->curso_nome ?: '—' }}</td>
                                    <td>{{ $aluno->situacao_academica ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Ainda sem sync de alunos.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $alunos->links() }}</div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCobranca" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('control.hub.escolas.cobrancas.store', $escola) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Criar cobrança anual</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Calcula o valor com base nos alunos activos sincronizados. A escola passa a <strong>em cobrança</strong> e vê um aviso no sistema.</p>
                <div class="mb-2">
                    <label class="form-label">Ano lectivo</label>
                    <input type="text" name="ano_lectivo" class="form-control" value="{{ $escola->ano_lectivo }}">
                </div>
                <div class="row">
                    <div class="col-6 mb-2">
                        <label class="form-label">Total alunos</label>
                        <input type="number" min="0" name="total_alunos" class="form-control" value="{{ $escola->total_alunos }}" placeholder="Automático">
                    </div>
                    <div class="col-6 mb-2">
                        <label class="form-label">Preço / aluno</label>
                        <input type="number" step="0.01" min="0" name="preco_unitario" class="form-control" value="{{ $escola->preco_anual_aluno }}">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Valor total (opcional)</label>
                    <input type="number" step="0.01" min="0" name="valor_total" class="form-control" placeholder="Deixe vazio = alunos × preço">
                </div>
                <div class="mb-2">
                    <label class="form-label">Vencimento (data de referência)</label>
                    <input type="date" name="vencimento_em" class="form-control" value="{{ now()->addDays(30)->toDateString() }}">
                </div>
                <div class="border rounded-2 p-2 mb-3">
                    <div class="fw-semibold small mb-1">Prazos automáticos (minutos desde agora)</div>
                    <p class="small text-muted mb-2 mb-md-2">
                        Define quando a escola vê o aviso, passa a atraso e é bloqueada.
                        Deixe atraso/bloqueio vazios para não automatizar. <code>0</code> = imediato.
                    </p>
                    <div class="row g-2">
                        <div class="col-4">
                            <label class="form-label small">Até aviso</label>
                            <input type="number" min="0" name="minutos_ate_aviso" class="form-control"
                                   value="{{ old('minutos_ate_aviso', $escola->minutos_ate_aviso ?? 0) }}">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Até atraso</label>
                            <input type="number" min="0" name="minutos_ate_atraso" class="form-control"
                                   value="{{ old('minutos_ate_atraso', $escola->minutos_ate_atraso) }}" placeholder="opcional">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Até bloqueio</label>
                            <input type="number" min="0" name="minutos_ate_bloqueio" class="form-control"
                                   value="{{ old('minutos_ate_bloqueio', $escola->minutos_ate_bloqueio) }}" placeholder="opcional">
                        </div>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Aviso na escola</label>
                    <textarea name="aviso_escola" class="form-control" rows="2" placeholder="Mensagem mostrada no banner da escola"></textarea>
                </div>
                <div class="mb-0">
                    <label class="form-label">Notas internas</label>
                    <textarea name="notas" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Criar cobrança</button>
            </div>
        </form>
    </div>
</div>

@if($cobrancaAberta)
<div class="modal fade" id="modalPagar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('control.hub.cobrancas.pagar', $cobrancaAberta) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Registar pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Confirmar pagamento de <strong>{{ number_format((float) $cobrancaAberta->valor_total, 2, ',', '.') }}</strong> ({{ $cobrancaAberta->referencia }}).</p>
                <div class="mb-2">
                    <label class="form-label">Método</label>
                    <input type="text" name="metodo_pagamento" class="form-control" placeholder="Transferência, numerário, etc.">
                </div>
                <div class="mb-2">
                    <label class="form-label">Referência do pagamento</label>
                    <input type="text" name="referencia_pagamento" class="form-control">
                </div>
                <div class="mb-0">
                    <label class="form-label">Notas</label>
                    <textarea name="notas" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success">Confirmar pago</button>
            </div>
        </form>
    </div>
</div>
@endif

<div class="modal fade" id="modalBloquear" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('control.hub.escolas.bloquear', $escola) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Bloquear escola</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Isto suspende o acesso de <strong>toda</strong> a instalação em até cerca de 1 minuto (verificação de licença na escola) ou no próximo sync.</p>
                <label class="form-label">Motivo *</label>
                <textarea name="motivo" class="form-control" rows="3" required placeholder="Ex.: Pagamento anual em falta após contacto de cobrança"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger">Confirmar bloqueio</button>
            </div>
        </form>
    </div>
</div>
@endsection
