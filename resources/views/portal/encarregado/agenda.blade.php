@extends('layouts.portal-encarregado')

@section('title', 'Agenda — '.$aluno->nome)

@section('content')
<a href="{{ route('portal.encarregado.filho', $aluno) }}" class="small text-decoration-none">&larr; Voltar</a>
<h1 class="h5 mt-2">Agenda escolar</h1>
<p class="text-muted small">{{ $aluno->nome }} · {{ $aluno->turma?->nome }}</p>

<div class="enc-card">
    @forelse($eventos as $ev)
        <div class="border-bottom py-2">
            <strong>{{ $ev->titulo }}</strong>
            <div class="small text-muted">
                {{ $ev->inicia_em->format('d/m/Y H:i') }}
                · {{ $tipos[$ev->tipo] ?? $ev->tipo }}
            </div>
            @if($ev->descricao)
                <div class="small">{{ $ev->descricao }}</div>
            @endif
        </div>
    @empty
        <p class="small text-muted mb-0">Sem eventos na agenda.</p>
    @endforelse
</div>
@endsection
