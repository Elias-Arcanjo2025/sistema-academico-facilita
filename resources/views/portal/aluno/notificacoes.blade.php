@extends('layouts.portal-aluno')

@section('title', 'Notificações')

@section('content')
<div class="portal-aluno-page">
    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
        <h1 class="h5 mb-0">Notificações</h1>
        @if($notificacoesNaoLidas > 0)
            <form method="POST" action="{{ route('portal.aluno.notificacoes.marcar-todas-lidas') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    Marcar todas como lidas
                </button>
            </form>
        @endif
    </div>

    <div class="card border-0 shadow-sm portal-card overflow-hidden">
        @forelse($notificacoes as $notificacao)
            @include('portal.aluno.partials.notificacao-item', ['notificacao' => $notificacao])
        @empty
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-bell-slash fs-1 d-block mb-2 opacity-50"></i>
                <p class="mb-0 small">Ainda não recebeu notificações.</p>
            </div>
        @endforelse
    </div>

    @if($notificacoes->hasPages())
        <div class="mt-3 d-flex justify-content-center">
            {{ $notificacoes->links() }}
        </div>
    @endif
</div>
@endsection
