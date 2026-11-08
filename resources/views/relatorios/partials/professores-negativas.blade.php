@php
    $notaMin = \App\Models\Configuracao::get('nota_minima_aprovacao', 10);
    $maxNeg = $top_professores_negativos->max('total_negativas') ?: 1;
@endphp

<section class="ra-section mb-4">
    <header class="ra-section-head">
        <div>
            <h2 class="ra-section-title">
                <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                Professores com Mais Notas Negativas
            </h2>
            <p class="ra-section-sub">
                {{ $periodoLabel }} · MAC, PP, PT &lt; {{ $notaMin }}
                · deduplicado pré/pós-conselho
            </p>
        </div>
        @if($top_professores_negativos->isNotEmpty())
        <span class="ra-pill ra-pill-danger">Top {{ $top_professores_negativos->count() }}</span>
        @endif
    </header>

    @if($top_professores_negativos->isEmpty())
        <div class="ra-empty">Sem registos de notas negativas para os filtros seleccionados.</div>
    @else
        <div class="ra-leaderboard">
            @foreach($top_professores_negativos as $i => $p)
            @php
                $pos = $i + 1;
                $pctBar = round($p->total_negativas / $maxNeg * 100);
                $iniciais = collect(explode(' ', $p->professor_nome))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
            @endphp
            <article class="ra-leader-row {{ $pos === 1 ? 'is-top' : '' }}">
                <span class="ra-leader-rank">{{ $pos }}</span>
                <span class="ra-leader-avatar" aria-hidden="true">{{ $iniciais }}</span>
                <div class="ra-leader-body">
                    <div class="ra-leader-top">
                        <span class="ra-leader-name">{{ $p->professor_nome }}</span>
                        <span class="ra-leader-stat">{{ number_format($p->total_negativas, 0, ',', '.') }} neg.</span>
                    </div>
                    <div class="ra-leader-bar" role="presentation">
                        <div class="ra-leader-bar-fill" style="width: {{ $pctBar }}%"></div>
                    </div>
                    <div class="ra-leader-meta">
                        Média das negativas: <strong>{{ number_format($p->media_valor ?? 0, 1, ',', '.') }}</strong>
                        · {{ $p->pct_negativas ?? 0 }}% do total lançado
                        ({{ number_format($p->total_lancamentos ?? 0, 0, ',', '.') }} provas)
                    </div>
                </div>
            </article>
            @endforeach
        </div>
        <p class="ra-footnote">
            <i class="bi bi-info-circle"></i>
            Conta cada prova parcelar (MAC/PP/PT) abaixo de {{ $notaMin }}, uma por aluno/disciplina/trimestre.
            @if($periodo === 'anual') Inclui todos os trimestres do ano. @endif
        </p>
    @endif
</section>
