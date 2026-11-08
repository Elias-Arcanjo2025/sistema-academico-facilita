@extends('layouts.portal-encarregado')

@section('title', 'Linha do tempo — '.$aluno->nome)

@section('content')
@php $cor = $card['cor'] ?? '#0d9488'; @endphp

<div class="enc-dash" style="--enc-accent: {{ $cor }}">
    <a href="{{ route('portal.encarregado.filho', $aluno) }}" class="enc-back"><i class="bi bi-arrow-left"></i> {{ $aluno->nome }}</a>

    <div class="enc-hero mb-3">
        <div class="enc-hero__eyebrow">História do ano</div>
        <h1 class="enc-hero__title">Linha do tempo</h1>
        <p class="enc-hero__sub mb-0">Notas, faltas, mensagens e ocorrências de {{ $card['nome_curto'] ?? $aluno->nome }} numa só sequência.</p>
    </div>

    @if(!empty($acoesAgora))
        <div class="enc-panel mb-3">
            <div class="enc-panel__head">
                <strong><i class="bi bi-lightning-charge me-1"></i> O que fazer agora</strong>
            </div>
            <div class="enc-acoes">
                @foreach($acoesAgora as $acao)
                    <a href="{{ $acao['url'] }}" class="enc-acao">
                        <i class="bi {{ $acao['icone'] }}"></i>
                        <span>
                            <strong>{{ $acao['titulo'] }}</strong>
                            <small>{{ $acao['texto'] }}</small>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="enc-panel">
        <div class="enc-panel__head">
            <strong>Eventos</strong>
            <span class="small text-muted">{{ $eventos->count() }} registo(s)</span>
        </div>

        @if($eventos->isEmpty())
            <p class="text-muted mb-0 text-center py-3">Ainda sem eventos neste ano. Quando houver notas, faltas ou mensagens, aparecem aqui.</p>
        @else
            <ol class="enc-timeline">
                @foreach($eventos as $evento)
                    <li class="enc-timeline__item enc-timeline__item--{{ $evento['tom'] }}">
                        <div class="enc-timeline__dot"><i class="bi {{ $evento['icone'] }}"></i></div>
                        <div class="enc-timeline__body">
                            <div class="enc-timeline__meta">
                                {{ $evento['data']?->format('d/m/Y H:i') ?? '—' }}
                            </div>
                            <div class="enc-timeline__title">{{ $evento['titulo'] }}</div>
                            <div class="enc-timeline__text">{{ $evento['texto'] }}</div>
                            @if(!empty($evento['url']))
                                <a href="{{ $evento['url'] }}" class="enc-timeline__link">Abrir</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</div>
@endsection
