@php
    $dados = $notificacao->data ?? [];
    $tipo = $dados['tipo_evento'] ?? 'geral';
    $icone = match ($tipo) {
        'nota_publicada' => 'bi-journal-check',
        'prova_agendada' => 'bi-calendar-event',
        'alerta_academico' => 'bi-exclamation-triangle',
        default => 'bi-bell',
    };
    $corIcone = match ($tipo) {
        'nota_publicada' => 'text-primary',
        'prova_agendada' => 'text-warning',
        'alerta_academico' => 'text-danger',
        default => 'text-secondary',
    };
    $lida = $notificacao->read_at !== null;
    $mensagem = $dados['titulo'] ?? $dados['message'] ?? 'Nova notificação';
    if (! empty($dados['titulo']) && ! empty($dados['mensagem'])) {
        $mensagem = $dados['titulo'].': '.$dados['mensagem'];
    }
    $url = route('portal.aluno.notificacoes.abrir', $notificacao->id);
@endphp
{{-- Sem data-bs-dismiss: o Bootstrap faz preventDefault em <a> e impede ir a /abrir (marcar lida + redirect). --}}
<a href="{{ $url }}"
   class="portal-notificacao-item d-flex gap-2 text-decoration-none {{ $lida ? 'portal-notificacao-item--lida' : 'portal-notificacao-item--nova' }}">
    <span class="portal-notificacao-item__icon flex-shrink-0 {{ $corIcone }}">
        <i class="bi {{ $icone }}"></i>
    </span>
    <span class="flex-grow-1 min-w-0">
        <span class="d-block {{ $lida ? '' : 'fw-semibold' }} small portal-notificacao-item__text">{{ $mensagem }}</span>
        <span class="d-block text-muted" style="font-size:.7rem">{{ $notificacao->created_at?->diffForHumans() }}</span>
    </span>
    @unless($lida)
        <span class="portal-notificacao-badge flex-shrink-0 align-self-center" aria-hidden="true"></span>
    @endunless
</a>
