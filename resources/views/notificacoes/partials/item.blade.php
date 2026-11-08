@php
    $dados = $notificacao->data ?? [];
    $titulo = $dados['titulo'] ?? null;
    $mensagem = $dados['mensagem'] ?? $dados['message'] ?? 'Nova notificação';
    $lida = $notificacao->read_at !== null;
    $url = route('notificacoes.abrir', $notificacao->id);
@endphp
{{-- Sem data-bs-dismiss: o Bootstrap faz preventDefault em <a> e impede ir a /abrir (marcar lida + redirect). --}}
<a href="{{ $url }}"
   class="list-group-item list-group-item-action d-flex gap-2 align-items-start {{ $lida ? '' : 'list-group-item-light' }}">
    <i class="bi bi-bell {{ $lida ? 'text-muted' : 'text-primary' }} mt-1"></i>
    <span class="flex-grow-1 min-w-0">
        @if($titulo)
            <span class="d-block small fw-semibold">{{ $titulo }}</span>
        @endif
        <span class="d-block small {{ $titulo ? 'text-muted' : ($lida ? '' : 'fw-semibold') }}">{{ $mensagem }}</span>
        <span class="d-block text-muted" style="font-size:.7rem">{{ $notificacao->created_at?->diffForHumans() }}</span>
    </span>
    @unless($lida)
        <span class="badge bg-primary rounded-pill align-self-center">Nova</span>
    @endunless
</a>
