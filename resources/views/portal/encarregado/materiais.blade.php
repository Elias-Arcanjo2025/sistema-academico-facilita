@extends('layouts.portal-encarregado')

@section('title', 'Materiais — '.$aluno->nome)

@section('content')
<a href="{{ route('portal.encarregado.filho', $aluno) }}" class="small text-decoration-none">&larr; Voltar</a>
<h1 class="h5 mt-2">Materiais académicos</h1>
<p class="text-muted small">{{ $aluno->nome }} · {{ $aluno->turma?->nome }}</p>

<div class="enc-card">
    @forelse($materiais as $m)
        <div class="d-flex justify-content-between align-items-start gap-2 border-bottom py-2">
            <div>
                <strong>{{ $m->titulo }}</strong>
                <div class="small text-muted">{{ $m->disciplina?->nome ?? 'Geral' }}</div>
            </div>
            <a href="{{ route('portal.encarregado.filho.materiais.download', [$aluno, $m]) }}" class="btn btn-sm btn-outline-primary">Descarregar</a>
        </div>
    @empty
        <p class="small text-muted mb-0">Sem materiais publicados.</p>
    @endforelse
    @if($materiais->hasPages())
        <div class="pt-2">{{ $materiais->links() }}</div>
    @endif
</div>
@endsection
