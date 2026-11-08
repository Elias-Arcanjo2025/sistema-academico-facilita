@extends('layouts.portal-aluno')

@section('title', 'Acesso restrito')

@section('content')
<div class="text-center py-4">
    <div class="display-4 text-warning mb-3"><i class="bi bi-lock-fill"></i></div>
    <h1 class="h5 mb-3">Consulta de notas indisponível</h1>
    <p class="text-muted mb-4">{{ $mensagem }}</p>
    @auth
        <a href="{{ route('portal.aluno.dashboard') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-house me-1"></i> Voltar ao início
        </a>
    @endauth
</div>
@endsection
