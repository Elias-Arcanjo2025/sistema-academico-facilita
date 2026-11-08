@extends('layouts.portal-encarregado')

@section('title', 'Assiduidade — '.$aluno->nome)

@section('content')
<a href="{{ route('portal.encarregado.filho', $aluno) }}" class="small text-decoration-none">&larr; Voltar</a>
<h1 class="h5 mt-2">Assiduidade — {{ $aluno->nome }}</h1>
<p class="text-muted small">{{ $aluno->turma?->nome }}</p>

<div class="enc-card mb-3">
    <div class="row g-2 small">
        <div class="col-6">Presente: <strong>{{ $totais['presente'] ?? 0 }}</strong></div>
        <div class="col-6">Faltas (I): <strong>{{ $totais['falta_injustificada'] ?? 0 }}</strong></div>
        <div class="col-6">Faltas (J): <strong>{{ $totais['falta_justificada'] ?? 0 }}</strong></div>
        <div class="col-6">Atrasos: <strong>{{ $totais['atraso'] ?? 0 }}</strong></div>
    </div>
</div>

<div class="enc-card">
    <strong class="d-block mb-2">Registos recentes</strong>
    @forelse($recentes as $reg)
        <div class="small border-bottom py-2">
            <strong>{{ $reg->aula?->disciplina?->nome }}</strong>
            · {{ $reg->aula?->data?->format('d/m/Y') }}
            · {{ $estados[$reg->estado] ?? $reg->estado }}
        </div>
    @empty
        <p class="small text-muted mb-0">Sem faltas ou atrasos.</p>
    @endforelse
</div>
@endsection
