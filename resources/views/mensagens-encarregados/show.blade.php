<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="h4 mb-0">Conversa — {{ $conversa->aluno?->nome }}</h2>
                <small class="text-muted">
                    {{ $conversa->aluno?->turma?->nome }} · Encarregado: {{ $conversa->encarregadoUser?->name }}
                </small>
            </div>
            <a href="{{ route('mensagens-encarregados.index') }}" class="btn btn-outline-secondary btn-sm">Voltar</a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container" style="max-width: 28rem;">
            <style>
                .dt-chat-shell {
                    background: var(--bg-card, #fff);
                    border: 1px solid var(--border-color, #e2e8f0);
                    border-radius: 1.15rem;
                    overflow: hidden;
                    box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
                }
                .dt-chat-thread {
                    max-height: min(52vh, 28rem);
                    min-height: 12rem;
                    overflow-y: auto;
                    padding: .75rem .7rem;
                    background: #f8fafc;
                }
                [data-theme="dark"] .dt-chat-thread { background: rgba(15, 23, 42, .35); }
                .dt-msg { display: flex; flex-direction: column; width: 100%; margin-bottom: .2rem; }
                .dt-msg--me { align-items: flex-end; }
                .dt-msg--them { align-items: flex-start; }
                .dt-msg__label { font-size: .68rem; font-weight: 600; color: #0f766e; margin: 0 .2rem .12rem; }
                .dt-bubble {
                    display: inline-flex; flex-wrap: wrap; align-items: flex-end; gap: .15rem .45rem;
                    width: max-content !important; max-width: min(72%, 15rem) !important;
                    padding: .32rem .55rem; font-size: .875rem; line-height: 1.25;
                    word-break: break-word; overflow-wrap: anywhere; white-space: pre-wrap;
                }
                .dt-bubble--me {
                    background: #0f766e; color: #fff;
                    border-radius: .85rem .85rem .2rem .85rem;
                }
                .dt-bubble--them {
                    background: #fff; color: #0f172a;
                    border: 1px solid #e2e8f0;
                    border-radius: .85rem .85rem .85rem .2rem;
                }
                [data-theme="dark"] .dt-bubble--them {
                    background: #1e293b; color: #e2e8f0; border-color: #334155;
                }
                .dt-bubble__time { font-size: .6rem; opacity: .7; white-space: nowrap; line-height: 1; margin-bottom: .05rem; }
                .dt-chat-composer {
                    display: flex; align-items: flex-end; gap: .5rem;
                    padding: .7rem; border-top: 1px solid var(--border-color, #e2e8f0);
                    background: var(--bg-card, #fff);
                }
                .dt-chat-composer textarea {
                    flex: 1; border-radius: 1rem; resize: none; min-height: 2.5rem; max-height: 7rem;
                }
            </style>

            <div class="dt-chat-shell">
                <div class="dt-chat-thread" id="staffChatThread">
                    @forelse($mensagens as $msg)
                        @php
                            $meu = (int) $msg->user_id === (int) auth()->id();
                            $prev = $loop->index > 0 ? $mensagens[$loop->index - 1] : null;
                            $mostrarNome = ! $meu && (! $prev || (int) $prev->user_id !== (int) $msg->user_id);
                            $nomeAutor = $msg->autor?->name ?? 'Encarregado';
                            $nomeCurto = explode(' ', trim($nomeAutor))[0] ?? $nomeAutor;
                        @endphp
                        <div class="dt-msg {{ $meu ? 'dt-msg--me' : 'dt-msg--them' }}">
                            @if($mostrarNome)
                                <div class="dt-msg__label">{{ $nomeCurto }}</div>
                            @endif
                            <div class="dt-bubble {{ $meu ? 'dt-bubble--me' : 'dt-bubble--them' }}">
                                <span>{{ $msg->corpo }}</span>
                                <span class="dt-bubble__time">{{ $msg->created_at->format('H:i') }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-chat-dots fs-3 d-block mb-2"></i>
                            Sem mensagens nesta conversa.
                        </div>
                    @endforelse
                </div>

                <form method="POST"
                      action="{{ route('mensagens-encarregados.store', $conversa) }}"
                      class="dt-chat-composer"
                      id="staffChatForm">
                    @csrf
                    <textarea name="corpo"
                              id="staffChatCorpo"
                              class="form-control @error('corpo') is-invalid @enderror"
                              rows="2"
                              maxlength="2000"
                              required
                              placeholder="Escreva a resposta…">{{ old('corpo') }}</textarea>
                    <button type="submit" class="btn btn-primary rounded-circle" style="width:2.6rem;height:2.6rem" id="staffChatSend" title="Enviar" aria-label="Enviar">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </form>
                @error('corpo')
                    <div class="text-danger small px-3 pb-2">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    <script>
    (function () {
        var thread = document.getElementById('staffChatThread');
        if (thread) thread.scrollTop = thread.scrollHeight;
        var form = document.getElementById('staffChatForm');
        var btn = document.getElementById('staffChatSend');
        var area = document.getElementById('staffChatCorpo');
        if (!form || !btn || !area) return;
        form.addEventListener('submit', function () {
            btn.disabled = true;
        });
        area.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (!btn.disabled && area.value.trim()) form.requestSubmit();
            }
        });
    })();
    </script>
</x-app-layout>
