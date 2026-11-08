@php
    $assinaturaEnc = $assinatura_encarregado ?? null;
@endphp
@if($assinaturaEnc)
    <div style="min-height:28px; margin-bottom:3px;">
        <div style="font-weight:700; font-size:9px;">{{ $assinaturaEnc['nome'] }}</div>
        <div style="font-size:8px; color:#0f766e;">Assinado digitalmente no portal</div>
        <div style="font-size:7px; color:#555;">
            {{ $assinaturaEnc['assinado_em']?->format('d/m/Y H:i') }}
            · Ref. {{ $assinaturaEnc['hash_curto'] }}
        </div>
    </div>
@else
    <div class="linha-assinatura"></div>
@endif
Encarregado de Educação
