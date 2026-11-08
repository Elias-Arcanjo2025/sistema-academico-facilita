@php
    use App\Support\ProvaQuestaoHelper;

    $numero = is_array($q) ? ($q['numero'] ?? null) : null;
    $tituloGrupo = is_array($q) ? ($q['titulo_grupo'] ?? $q['titulo'] ?? null) : null;
    $texto = is_array($q) ? ($q['texto'] ?? $q['enunciado'] ?? '') : (string) $q;
    $intro = is_array($q) ? ($q['introducao'] ?? $q['contexto'] ?? null) : null;
    $opcoes = is_array($q) ? ProvaQuestaoHelper::opcoesNormalizadas($q['opcoes'] ?? []) : [];
    $subitens = is_array($q) ? ($q['subitens'] ?? []) : [];
    $cotacao = is_array($q) ? ($q['cotacao'] ?? $q['cotacao_total'] ?? null) : null;
    $cotacaoFmt = $cotacao !== null ? number_format((float) $cotacao, 1, ',', '.') : null;
    $temOpcoes = $opcoes !== [];
@endphp
<div class="questao-grupo">
    <div class="questao-titulo">
        {{ $numero ?? '?' }}.
        @if($tituloGrupo)
            {{ $tituloGrupo }}
        @elseif($texto && empty($subitens) && ! $temOpcoes)
            {{ $texto }}
        @endif
        @if($cotacaoFmt)
            <span class="questao-cotacao">({{ $cotacaoFmt }} {{ (float) $cotacao == 1 ? 'Valor' : 'Valores' }})</span>
        @endif
    </div>

    @if($intro)
        <p class="questao-intro">{{ $intro }}</p>
    @elseif($texto && ($tituloGrupo || !empty($subitens) || $temOpcoes))
        <p class="questao-intro">{{ $texto }}</p>
    @endif

    @if(!empty($subitens))
        <ul class="questao-subitens">
            @foreach($subitens as $sub)
                @php
                    $letra = ProvaQuestaoHelper::letraSubitem(is_array($sub) ? ($sub['letra'] ?? null) : null, $loop->index);
                    $subTexto = is_array($sub) ? ($sub['texto'] ?? '') : (string) $sub;
                    $subCot = is_array($sub) && isset($sub['cotacao']) ? number_format((float) $sub['cotacao'], 1, ',', '.') : null;
                @endphp
                <li>
                    <span class="questao-sub-letra">{{ $letra }})</span> {{ $subTexto }}
                    @if($subCot) ({{ $subCot }}v) @endif
                </li>
            @endforeach
        </ul>
    @elseif($temOpcoes)
        <ul class="questao-opcoes">
            @foreach($opcoes as $opcao)
                <li><strong>{{ $opcao['letra'] }})</strong> {{ $opcao['texto'] }}</li>
            @endforeach
        </ul>
    @endif
</div>
