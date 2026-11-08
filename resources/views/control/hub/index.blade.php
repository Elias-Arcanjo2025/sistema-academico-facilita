@extends('layouts.control-hub')

@section('title', 'Controlo Facilita')
@section('page-title', 'Painel')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Controlo Facilita</h1>
            <p class="text-muted mb-0">Visão agregada das instalações escolares</p>
        </div>
        <a href="{{ route('control.hub.escolas.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Registar escola
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="border rounded-3 p-3 h-100">
                <div class="text-muted small">Escolas</div>
                <div class="fs-4 fw-semibold">{{ $totais['escolas'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="border rounded-3 p-3 h-100">
                <div class="text-muted small">Activas</div>
                <div class="fs-4 fw-semibold text-success">{{ $totais['activas'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="border rounded-3 p-3 h-100">
                <div class="text-muted small">Bloqueadas</div>
                <div class="fs-4 fw-semibold text-danger">{{ $totais['bloqueadas'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="border rounded-3 p-3 h-100">
                <div class="text-muted small">Alunos</div>
                <div class="fs-4 fw-semibold">{{ number_format($totais['alunos'], 0, ',', '.') }}</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="border rounded-3 p-3 h-100">
                <div class="text-muted small">Em aberto</div>
                <div class="fs-5 fw-semibold text-warning">{{ number_format($totais['valor_em_aberto'], 2, ',', '.') }}</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="border rounded-3 p-3 h-100">
                <div class="text-muted small">Recebido</div>
                <div class="fs-5 fw-semibold text-success">{{ number_format($totais['valor_recebido'], 2, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <div class="table-responsive border rounded-3">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Escola</th>
                    <th>Estado</th>
                    <th class="text-end">Alunos</th>
                    <th class="text-end">Prof.</th>
                    <th class="text-end">Turmas</th>
                    <th>Ano lectivo</th>
                    <th>Último contacto</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($escolas as $escola)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $escola->nome }}</div>
                            <div class="small text-muted">{{ Str::limit($escola->installation_id, 13) }}</div>
                        </td>
                        <td>
                            @php
                                $badge = match($escola->status) {
                                    'activo' => 'success',
                                    'em_cobranca' => 'info',
                                    'em_atraso' => 'warning',
                                    'bloqueado' => 'danger',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge text-bg-{{ $badge }}">{{ \App\Models\Control\ControlEscola::statuses()[$escola->status] ?? $escola->status }}</span>
                            @if($escola->isOnline())
                                <span class="badge text-bg-success-subtle text-success border">online</span>
                            @else
                                <span class="badge text-bg-secondary-subtle text-secondary border">offline</span>
                            @endif
                        </td>
                        <td class="text-end">{{ $escola->total_alunos }}</td>
                        <td class="text-end">{{ $escola->total_professores }}</td>
                        <td class="text-end">{{ $escola->total_turmas }}</td>
                        <td>{{ $escola->ano_lectivo ?: '—' }}</td>
                        <td class="small">
                            {{ $escola->last_heartbeat_at?->diffForHumans() ?? 'Nunca' }}
                        </td>
                        <td class="text-end">
                            <a href="{{ route('control.hub.escolas.show', $escola) }}" class="btn btn-sm btn-outline-primary">Abrir</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Ainda não há escolas registadas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
