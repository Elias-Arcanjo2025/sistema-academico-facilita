@php
    use App\Support\ProvaQuestaoHelper;

    $numero = is_array($q) ? ($q['numero'] ?? null) : null;
    $tituloGrupo = is_array($q) ? ($q['titulo_grupo'] ?? $q['titulo'] ?? null) : null;
    $texto = is_array($q) ? ($q['texto'] ?? $q['enunciado'] ?? '') : (string) $q;
    $intro = is_array($q) ? ($q['introducao'] ?? $q['contexto'] ?? null) : null;
    $tipo = is_array($q) ? ($q['tipo'] ?? '') : '';
    $opcoes = is_array($q) ? ProvaQuestaoHelper::opcoesNormalizadas($q['opcoes'] ?? []) : [];
    $subitens = is_array($q) ? ($q['subitens'] ?? []) : [];
    $cotacao = is_array($q) ? ($q['cotacao'] ?? $q['cotacao_total'] ?? null) : null;
    $temOpcoes = $opcoes !== [];
@endphp
<li class="mb-3">
    <div class="fw-semibold">
        {{ $numero ?? '?' }}.
        @if($tituloGrupo){{ $tituloGrupo }}@elseif(!$subitens && !$temOpcoes){{ $texto }}@endif
        @if($cotacao)
            <span class="text-muted">({{ number_format((float) $cotacao, 1, ',', '.') }} {{ (float) $cotacao == 1 ? 'valor' : 'valores' }})</span>
        @endif
    </div>
    @if($intro)<p class="mb-1 mt-1">{{ $intro }}</p>@elseif($texto && ($tituloGrupo || $subitens || $temOpcoes))<p class="mb-1 mt-1">{{ $texto }}</p>@endif
    @if(!empty($subitens))
        <ul class="list-unstyled ms-3 mb-0">
            @foreach($subitens as $sub)
                @php
                    $letra = ProvaQuestaoHelper::letraSubitem(is_array($sub) ? ($sub['letra'] ?? null) : null, $loop->index);
                    $subTexto = is_array($sub) ? ($sub['texto'] ?? '') : (string) $sub;
                @endphp
                <li class="mb-1"><strong>{{ $letra }})</strong> {{ $subTexto }}</li>
            @endforeach
        </ul>
    @elseif($temOpcoes)
        <ul class="list-unstyled ms-3 mt-1 mb-0">
            @foreach($opcoes as $opcao)
                <li><strong>{{ $opcao['letra'] }})</strong> {{ $opcao['texto'] }}</li>
            @endforeach
        </ul>
    @endif
</li>
