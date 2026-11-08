@extends('layouts.portal-aluno')

@section('title', 'Materiais')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3 py-md-4 portal-aluno-page">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h2 class="h4 mb-1">Materiais académicos</h2>
            <p class="text-muted mb-0 small">{{ $aluno->turma?->nome }} · {{ $aluno->turma?->curso?->nome }}</p>
        </div>
        <a href="{{ route('portal.aluno.dashboard') }}" class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>

    <div class="card border-0 shadow-sm portal-card">
        <div class="list-group list-group-flush">
            @forelse($materiais as $m)
                <div class="list-group-item d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="fw-semibold">{{ $m->titulo }}</div>
                        <div class="small text-muted">
                            {{ $m->disciplina?->nome ?? 'Geral' }}
                            @if($m->turma) · {{ $m->turma->nome }} @endif
                        </div>
                        @if($m->descricao)
                            <div class="small mt-1">{{ $m->descricao }}</div>
                        @endif
                    </div>
                    <a href="{{ route('portal.aluno.materiais.download', $m) }}" class="btn btn-sm btn-outline-primary">Descarregar</a>
                </div>
            @empty
                <div class="p-4 text-muted text-center">Sem materiais publicados.</div>
            @endforelse
        </div>
        @if($materiais->hasPages())
            <div class="p-3">{{ $materiais->links() }}</div>
        @endif
    </div>
</div>
@endsection
