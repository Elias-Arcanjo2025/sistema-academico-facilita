@extends('layouts.portal-aluno')

@section('title', 'Ocorrências')

@section('content')
<div class="portal-aluno-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Ocorrências</h1>
        <a href="{{ route('portal.aluno.assiduidade') }}" class="btn btn-sm btn-outline-secondary">Assiduidade</a>
    </div>

    <div class="card border-0 shadow-sm portal-card overflow-hidden">
        @forelse($ocorrencias as $oc)
            <div class="border-bottom p-3">
                <div class="d-flex justify-content-between gap-2">
                    <div class="fw-semibold small">{{ $tipos[$oc->tipo] ?? $oc->tipo }}</div>
                    <span class="badge {{ $oc->estado === 'aberta' ? 'bg-warning text-dark' : 'bg-success' }}">
                        {{ $oc->estado === 'aberta' ? 'Aberta' : 'Resolvida' }}
                    </span>
                </div>
                <div class="text-muted" style="font-size:.75rem">
                    {{ $oc->data->format('d/m/Y') }} · {{ $severidades[$oc->severidade] ?? $oc->severidade }}
                </div>
                <p class="small mb-0 mt-1">{{ $oc->descricao }}</p>
                @if($oc->medida)
                    <p class="small text-muted mb-0 mt-1">Medida: {{ $oc->medida }}</p>
                @endif
            </div>
        @empty
            <div class="p-4 text-center text-muted small">Sem ocorrências registadas.</div>
        @endforelse
    </div>

    @if($ocorrencias->hasPages())
        <div class="mt-3 d-flex justify-content-center">{{ $ocorrencias->links() }}</div>
    @endif
</div>
@endsection
