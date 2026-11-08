@extends('layouts.portal-encarregado')

@section('title', 'Notificações')

@section('content')
<div class="d-flex align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h5 mb-0">Notificações</h1>
    @if($notificacoesNaoLidas > 0)
        <form method="POST" action="{{ route('portal.encarregado.notificacoes.marcar-todas-lidas') }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-secondary">Marcar todas como lidas</button>
        </form>
    @endif
</div>

<div class="enc-card p-0 overflow-hidden">
    @forelse($notificacoes as $notificacao)
        @php
            $dados = $notificacao->data ?? [];
            $lida = $notificacao->read_at !== null;
            $mensagem = $dados['titulo'] ?? $dados['message'] ?? 'Nova notificação';
            if (! empty($dados['titulo']) && ! empty($dados['mensagem'])) {
                $mensagem = $dados['titulo'].': '.$dados['mensagem'];
            }
        @endphp
        <a href="{{ route('portal.encarregado.notificacoes.abrir', $notificacao->id) }}"
           class="d-flex gap-2 text-decoration-none border-bottom p-3 {{ $lida ? 'text-muted' : 'text-dark fw-semibold' }}">
            <span class="flex-shrink-0"><i class="bi bi-bell"></i></span>
            <span class="flex-grow-1">
                <span class="d-block small">{{ $mensagem }}</span>
                <span class="d-block" style="font-size:.7rem">{{ $notificacao->created_at?->diffForHumans() }}</span>
            </span>
            @unless($lida)
                <span class="badge bg-primary align-self-center">Nova</span>
            @endunless
        </a>
    @empty
        <div class="text-center text-muted py-5">
            <i class="bi bi-bell-slash fs-1 d-block mb-2 opacity-50"></i>
            <p class="mb-0 small">Ainda não recebeu notificações.</p>
        </div>
    @endforelse
</div>

@if($notificacoes->hasPages())
    <div class="mt-3 d-flex justify-content-center">{{ $notificacoes->links() }}</div>
@endif
@endsection
