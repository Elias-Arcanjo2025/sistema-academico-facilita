@extends('layouts.portal-encarregado')

@section('title', 'Mensagens — '.$aluno->nome)

@push('styles')
<style>
    /* Assistente não tapa o botão Enviar nesta página */
    body:has(.enc-chat) .assistente-root {
        --assistente-offset-bottom: calc(5.75rem + env(safe-area-inset-bottom, 0px));
        --assistente-offset-right: max(0.65rem, env(safe-area-inset-right, 0px));
    }
    body:has(.enc-chat) .assistente-fab {
        transform: scale(0.92);
        opacity: 0.92;
    }

    .enc-chat {
        display: flex;
        flex-direction: column;
        gap: .75rem;
        max-width: 40rem;
        margin: 0 auto;
    }

    .enc-chat__nav {
        display: flex;
        align-items: center;
        gap: .65rem;
    }
    .enc-chat__back {
        width: 2.15rem; height: 2.15rem; border-radius: 999px;
        display: grid; place-items: center;
        text-decoration: none; color: #0f766e;
        background: #fff; border: 1px solid #dbe4ee;
        flex-shrink: 0;
    }
    .enc-chat__back:hover { background: #ecfdf5; color: #0f766e; }
    .enc-chat__who { min-width: 0; }
    .enc-chat__who strong {
        display: block; font-size: 1rem; line-height: 1.2;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .enc-chat__who span {
        display: block; font-size: .78rem; color: #64748b;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    .enc-chat__shell {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1.25rem;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
        display: flex;
        flex-direction: column;
        min-height: 22rem;
        max-height: calc(100dvh - 11.5rem);
    }

    .enc-chat__dtbar {
        display: flex; align-items: center; gap: .55rem;
        padding: .65rem .9rem;
        border-bottom: 1px solid #eef2f7;
        background: linear-gradient(180deg, #f8fafc, #fff);
        font-size: .8rem; color: #475569;
    }
    .enc-chat__dtbar i {
        width: 1.7rem; height: 1.7rem; border-radius: 999px;
        display: grid; place-items: center;
        background: #ccfbf1; color: #0f766e; font-size: .85rem;
    }
    .enc-chat__dtbar strong { color: #0f172a; font-weight: 600; }

    .enc-chat__thread {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: .85rem .75rem 1rem;
        min-height: 12rem;
        scroll-behavior: smooth;
        background: #f8fafc;
    }

    .enc-chat__empty {
        min-height: 14rem;
        display: grid; place-items: center; text-align: center;
        color: #64748b; padding: 1.5rem .75rem;
    }
    .enc-chat__empty-card {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem;
        padding: 1.15rem 1.25rem; max-width: 16rem;
        box-shadow: 0 6px 16px rgba(15, 23, 42, .04);
    }
    .enc-chat__empty-icon {
        width: 2.6rem; height: 2.6rem; border-radius: 999px;
        display: grid; place-items: center; margin: 0 auto .6rem;
        background: #ccfbf1; color: #0f766e;
    }

    .enc-day {
        text-align: center; margin: .35rem 0 .85rem;
    }
    .enc-day span {
        display: inline-block; font-size: .68rem; font-weight: 600;
        color: #64748b; background: rgba(255,255,255,.85);
        border: 1px solid #e2e8f0; border-radius: 999px;
        padding: .15rem .55rem;
    }

    .enc-msg {
        display: flex;
        flex-direction: column;
        width: 100%;
        margin-bottom: .2rem;
        animation: encPop .18s ease-out;
    }
    .enc-msg--me { align-items: flex-end; }
    .enc-msg--them { align-items: flex-start; }
    .enc-msg--tight { margin-bottom: .08rem; }
    .enc-msg--gap { margin-top: .35rem; }

    @keyframes encPop {
        from { opacity: 0; transform: translateY(3px); }
        to { opacity: 1; transform: none; }
    }

    .enc-msg__label {
        font-size: .68rem;
        font-weight: 600;
        color: #0f766e;
        margin: 0 .2rem .12rem;
        line-height: 1;
    }

    .enc-bubble {
        display: inline-flex;
        flex-direction: row;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: flex-end;
        gap: .15rem .45rem;
        width: max-content !important;
        max-width: min(72%, 14.5rem) !important;
        min-width: 0;
        padding: .32rem .55rem;
        font-size: .875rem;
        line-height: 1.25;
        word-break: break-word;
        overflow-wrap: anywhere;
        white-space: pre-wrap;
        box-shadow: 0 1px 1px rgba(15, 23, 42, .05);
    }
    .enc-bubble--me {
        background: #0f766e;
        color: #fff;
        border-radius: .85rem .85rem .2rem .85rem;
    }
    .enc-bubble--them {
        background: #fff;
        color: #0f172a;
        border: 1px solid #e2e8f0;
        border-radius: .85rem .85rem .85rem .2rem;
    }
    .enc-bubble__text {
        margin: 0;
        padding: 0;
        flex: 0 1 auto;
        max-width: 100%;
    }
    .enc-bubble__time {
        flex: 0 0 auto;
        font-size: .6rem;
        line-height: 1;
        opacity: .7;
        margin-bottom: .05rem;
        white-space: nowrap;
    }
    .enc-bubble--them .enc-bubble__time { color: #64748b; }
    .enc-bubble--me .enc-bubble__time { color: rgba(255,255,255,.82); }

    .enc-chat__composer {
        display: flex; align-items: flex-end; gap: .5rem;
        padding: .65rem .7rem .7rem;
        border-top: 1px solid #eef2f7;
        background: #fff;
        padding-right: 3.6rem; /* espaço para o FAB do assistente */
    }
    .enc-chat__composer-field {
        flex: 1; min-width: 0;
        background: #f1f5f9;
        border: 1px solid transparent;
        border-radius: 1.25rem;
        padding: .55rem .9rem;
        transition: border-color .15s, background .15s, box-shadow .15s;
    }
    .enc-chat__composer-field:focus-within {
        background: #fff;
        border-color: #99f6e4;
        box-shadow: 0 0 0 3px rgba(13, 148, 136, .12);
    }
    .enc-chat__composer textarea {
        width: 100%; border: 0; outline: 0; resize: none;
        background: transparent; padding: 0; margin: 0;
        min-height: 1.4rem; max-height: 6.5rem;
        font-size: .92rem; line-height: 1.35;
        field-sizing: content;
    }
    .enc-chat__composer textarea:disabled { opacity: .6; }
    .enc-chat__send {
        width: 2.55rem; height: 2.55rem; border: 0; border-radius: 999px;
        background: #0f766e; color: #fff;
        display: grid; place-items: center; flex-shrink: 0;
        box-shadow: 0 6px 14px rgba(15, 118, 110, .28);
        transition: transform .12s, background .12s;
    }
    .enc-chat__send:not(:disabled):hover { background: #0d9488; transform: scale(1.04); }
    .enc-chat__send:disabled { opacity: .45; box-shadow: none; }
    .enc-chat__error { color: #dc2626; font-size: .78rem; padding: 0 .9rem .55rem; }

    @media (min-width: 768px) {
        .enc-chat__composer { padding-right: .7rem; }
        body:has(.enc-chat) .assistente-root {
            --assistente-offset-right: max(1.25rem, env(safe-area-inset-right, 0px));
        }
    }
</style>
@endpush

@section('content')
@php
    $primeiroNome = explode(' ', trim($aluno->nome))[0] ?? $aluno->nome;
    $dtNome = $directores->pluck('name')->join(', ');
    $equipaNomes = ($equipa ?? collect())->pluck('name')->unique()->values();
@endphp

<div class="enc-chat">
    <div class="enc-chat__nav">
        <a href="{{ route('portal.encarregado.filho', $aluno) }}" class="enc-chat__back" title="Voltar" aria-label="Voltar ao dashboard">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div class="enc-chat__who">
            <strong>{{ $aluno->nome }}</strong>
            <span>{{ $aluno->turma?->codigo ?? $aluno->turma?->nome ?? 'Sem turma' }} · Mensagens com o director</span>
        </div>
    </div>

    @if(($equipa ?? collect())->isEmpty())
        <div class="alert alert-warning py-2 mb-0">Ainda não há destinatários na escola para esta turma. Contacte a secretaria.</div>
    @endif

    <div class="enc-chat__shell">
        @if($equipaNomes->isNotEmpty() || $directores->isNotEmpty())
            <div class="enc-chat__dtbar">
                <i class="bi bi-people"></i>
                <div>
                    @if($dtNome !== '')
                        Director: <strong>{{ $dtNome }}</strong>
                        @if($equipaNomes->count() > $directores->count())
                            <span class="text-muted"> · equipa da turma também recebe</span>
                        @endif
                    @else
                        Equipa da turma: <strong>{{ $equipaNomes->take(3)->join(', ') }}</strong>
                    @endif
                </div>
            </div>
        @endif

        <div class="enc-chat__thread" id="encChatThread" role="log" aria-live="polite" aria-relevant="additions">
            @forelse($mensagens as $msg)
                @php
                    $meu = (int) $msg->user_id === (int) auth()->id();
                    $prev = $loop->index > 0 ? $mensagens[$loop->index - 1] : null;
                    $mesmoAutor = $prev && (int) $prev->user_id === (int) $msg->user_id;
                    $mesmoDia = $prev && $prev->created_at->isSameDay($msg->created_at);
                    $mostrarDia = ! $prev || ! $mesmoDia;
                    $mostrarNome = ! $meu && (! $mesmoAutor || ! $mesmoDia);
                @endphp

                @if($mostrarDia)
                    <div class="enc-day"><span>{{ $msg->created_at->format('d/m/Y') }}</span></div>
                @endif

                <div class="enc-msg {{ $meu ? 'enc-msg--me' : 'enc-msg--them' }} {{ $mesmoAutor && $mesmoDia ? 'enc-msg--tight' : 'enc-msg--gap' }}">
                    @if($mostrarNome)
                        @php
                            $nomeAutor = $msg->autor?->name ?? 'Director';
                            $nomeCurto = explode(' ', trim($nomeAutor))[0] ?? $nomeAutor;
                        @endphp
                        <div class="enc-msg__label">{{ $nomeCurto }}</div>
                    @endif
                    <div class="enc-bubble {{ $meu ? 'enc-bubble--me' : 'enc-bubble--them' }}">
                        <span class="enc-bubble__text">{{ $msg->corpo }}</span>
                        <span class="enc-bubble__time">{{ $msg->created_at->format('H:i') }}</span>
                    </div>
                </div>
            @empty
                <div class="enc-chat__empty">
                    <div class="enc-chat__empty-card">
                        <div class="enc-chat__empty-icon"><i class="bi bi-chat-heart"></i></div>
                        <strong class="d-block text-dark mb-1" style="font-size:.95rem">Comece a conversa</strong>
                        <span class="small">Envie uma mensagem sobre {{ $primeiroNome }} ao director de turma.</span>
                    </div>
                </div>
            @endforelse
        </div>

        <form method="POST"
              action="{{ route('portal.encarregado.mensagens.store', $aluno) }}"
              class="enc-chat__composer"
              id="encChatForm">
            @csrf
            <div class="enc-chat__composer-field">
                <label class="visually-hidden" for="encChatCorpo">Mensagem</label>
                <textarea name="corpo"
                          id="encChatCorpo"
                          rows="1"
                          maxlength="2000"
                          required
                          @disabled(($equipa ?? collect())->isEmpty())
                          placeholder="Mensagem…">{{ old('corpo') }}</textarea>
            </div>
            <button type="submit"
                    class="enc-chat__send"
                    id="encChatSend"
                    title="Enviar"
                    aria-label="Enviar mensagem"
                    @disabled(($equipa ?? collect())->isEmpty())>
                <i class="bi bi-send-fill"></i>
            </button>
        </form>
        @error('corpo')
            <div class="enc-chat__error">{{ $message }}</div>
        @enderror
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var thread = document.getElementById('encChatThread');
    if (thread) thread.scrollTop = thread.scrollHeight;

    var form = document.getElementById('encChatForm');
    var btn = document.getElementById('encChatSend');
    var area = document.getElementById('encChatCorpo');
    if (!form || !btn || !area) return;

    function autoGrow() {
        area.style.height = 'auto';
        area.style.height = Math.min(area.scrollHeight, 104) + 'px';
    }
    area.addEventListener('input', autoGrow);
    autoGrow();

    form.addEventListener('submit', function () {
        if (btn.disabled) return;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
    });

    area.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (!btn.disabled && area.value.trim()) form.requestSubmit();
        }
    });
})();
</script>
@endpush
