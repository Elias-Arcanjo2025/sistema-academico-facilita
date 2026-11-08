@extends('layouts.portal-aluno')

@section('title', 'Assiduidade')

@section('content')
<div class="portal-aluno-page">
    <h1 class="h5 mb-3">Assiduidade</h1>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm portal-card p-3 text-center">
                <div class="small text-muted">Presente</div>
                <div class="fs-4 fw-semibold text-success">{{ $totais['presente'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm portal-card p-3 text-center">
                <div class="small text-muted">Faltas (I)</div>
                <div class="fs-4 fw-semibold text-danger">{{ $totais['falta_injustificada'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm portal-card p-3 text-center">
                <div class="small text-muted">Faltas (J)</div>
                <div class="fs-4 fw-semibold text-info">{{ $totais['falta_justificada'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm portal-card p-3 text-center">
                <div class="small text-muted">Atrasos</div>
                <div class="fs-4 fw-semibold text-warning">{{ $totais['atraso'] ?? 0 }}</div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm portal-card overflow-hidden">
        <div class="card-header bg-transparent border-0 fw-semibold small">Faltas e atrasos recentes</div>
        <div class="list-group list-group-flush">
            @forelse($recentes as $reg)
                <div class="list-group-item">
                    <div class="fw-semibold small">{{ $reg->aula?->disciplina?->nome ?? 'Disciplina' }}</div>
                    <div class="text-muted" style="font-size:.75rem">
                        {{ $reg->aula?->data?->format('d/m/Y') }}
                        · {{ $estados[$reg->estado] ?? $reg->estado }}
                        @if($reg->justificacao)
                            · {{ $reg->justificacao }}
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-4 text-center text-muted small">Sem faltas ou atrasos registados.</div>
            @endforelse
        </div>
    </div>

    <div class="mt-3 text-center">
        <a href="{{ route('portal.aluno.ocorrencias') }}" class="btn btn-sm btn-outline-secondary">Ver ocorrências</a>
    </div>
</div>
@endsection
