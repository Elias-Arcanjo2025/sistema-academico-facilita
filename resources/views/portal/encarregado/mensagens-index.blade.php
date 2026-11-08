@extends('layouts.portal-encarregado')

@section('title', 'Mensagens')

@section('content')
<div class="enc-dash">
    <div class="enc-hero mb-3">
        <div class="enc-hero__eyebrow">Escola ↔ Família</div>
        <h1 class="enc-hero__title">Mensagens</h1>
        <p class="enc-hero__sub mb-0">Escolha o educando para falar com a escola (director de turma e equipa).</p>
    </div>

    @if($filhos->isEmpty())
        <div class="enc-panel">
            <p class="text-muted mb-0">Não há educandos associados. Contacte a secretaria.</p>
        </div>
    @else
        <div class="row g-3">
            @foreach($filhos as $filho)
                <div class="col-12 col-md-6">
                    <a href="{{ route('portal.encarregado.mensagens.show', $filho) }}" class="enc-filho-card">
                        <div class="enc-filho-card__top">
                            <div class="enc-filho-card__avatar">{{ mb_strtoupper(mb_substr($filho->nome, 0, 1)) }}</div>
                            <div class="min-w-0">
                                <div class="enc-filho-card__nome">{{ $filho->nome }}</div>
                                <div class="enc-filho-card__meta">
                                    {{ $filho->turma?->codigo ?? $filho->turma?->nome ?? 'Sem turma' }}
                                </div>
                            </div>
                            <i class="bi bi-chat-dots ms-auto text-muted"></i>
                        </div>
                        <div class="small text-muted">Abrir conversa com a escola</div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
