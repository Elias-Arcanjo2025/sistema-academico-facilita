@extends('layouts.portal-aluno')

@section('title', 'Regras de Avaliação')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h5 mb-0">Regras de avaliação</h1>
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('portal.aluno.dashboard') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<div class="card border-0 shadow-sm portal-card">
    <div class="card-body p-3 p-md-4 small">
        @include('regras-avaliacao._conteudo')
    </div>
</div>
@endsection
