@extends('layouts.portal-encarregado')

@section('title', 'Ocorrências — '.$aluno->nome)

@section('content')
<a href="{{ route('portal.encarregado.filho', $aluno) }}" class="small text-decoration-none">&larr; Voltar</a>
<h1 class="h5 mt-2">Ocorrências — {{ $aluno->nome }}</h1>
<p class="text-muted small">{{ $aluno->turma?->nome }}</p>

<div class="enc-card">
    @forelse($ocorrencias as $oc)
        <div class="border-bottom py-2">
            <div class="d-flex justify-content-between">
                <strong class="small">{{ $tipos[$oc->tipo] ?? $oc->tipo }}</strong>
                <span class="badge {{ $oc->estado === 'aberta' ? 'bg-warning text-dark' : 'bg-success' }}">
                    {{ $oc->estado === 'aberta' ? 'Aberta' : 'Resolvida' }}
                </span>
            </div>
            <div class="small text-muted">{{ $oc->data->format('d/m/Y') }} · {{ $severidades[$oc->severidade] ?? $oc->severidade }}</div>
            <p class="small mb-0 mt-1">{{ $oc->descricao }}</p>
        </div>
    @empty
        <p class="small text-muted mb-0">Sem ocorrências.</p>
    @endforelse
</div>

@if($ocorrencias->hasPages())
    <div class="mt-3">{{ $ocorrencias->links() }}</div>
@endif
@endsection
