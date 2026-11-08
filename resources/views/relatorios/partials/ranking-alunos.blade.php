@php
    $tipoMediaLabel = $metrica_ranking ?? ($t3Oficial ? 'CA' : 'MT');
    $turmasComRanking = $melhores_por_turma->filter(fn ($it) => ($it['ranking'] ?? collect())->isNotEmpty());
    $pautaTriDefault = $periodo === 'anual' ? 3 : ($trimestre ?? 3);
    $medalIcons = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
@endphp

<section class="ra-section mb-4">
    <header class="ra-section-head">
        <div>
            <h2 class="ra-section-title">
                <i class="bi bi-trophy-fill text-warning"></i>
                Melhores Alunos por Turma
            </h2>
            <p class="ra-section-sub">{{ $periodoLabel }} · Top 5 · {{ $tipoMediaLabel }} · sem negativas</p>
        </div>
        @if($turmasComRanking->isNotEmpty())
        <span class="ra-pill">{{ $turmasComRanking->count() }}/{{ $melhores_por_turma->count() }} turmas</span>
        @endif
    </header>

    @if($melhores_por_turma->isEmpty())
        <div class="ra-empty">Sem turmas para os filtros seleccionados.</div>
    @elseif($turmasComRanking->isEmpty())
        <div class="ra-empty">Nenhum aluno elegível (sem negativas) encontrado nas turmas filtradas.</div>
    @elseif($turmaId && $melhores_por_turma->count() === 1)
        @php
            $item = $melhores_por_turma->first();
            $turmaItem = $item['turma'];
            $ranking = $item['ranking'];
            $top3 = $ranking->take(3);
            $resto = $ranking->slice(3);
        @endphp
        <div class="ra-podium-wrap">
            <div class="ra-podium-meta">
                <a href="{{ route('pautas.trimestral-completa', ['turma_id' => $turmaItem->id, 'trimestre' => $pautaTriDefault, 'ano_lectivo' => $anoLectivo]) }}"
                   class="ra-turma-link">{{ $turmaItem->nome }}</a>
                <span class="ra-pill">{{ $turmaItem->curso?->nome }}</span>
                @if($turmaItem->numeroClasse())
                <span class="ra-pill ra-pill-primary">{{ $turmaItem->numeroClasse() }}ª</span>
                @endif
            </div>
            @if($top3->isNotEmpty())
            <div class="ra-podium">
                @foreach([1 => 1, 0 => 0, 2 => 2] as $slot => $idx)
                    @if($top3->has($idx))
                    @php $e = $top3[$idx]; $pos = $idx + 1; @endphp
                    <div class="ra-podium-slot pos-{{ $pos }}">
                        <div class="ra-podium-medal">{{ $medalIcons[$pos] ?? $pos }}</div>
                        <div class="ra-podium-score">{{ $e['media'] }}</div>
                        <div class="ra-podium-name">{{ $e['aluno']->nome }}</div>
                        <div class="ra-podium-num">N.º {{ $e['aluno']->numero_ordem ?? '—' }}</div>
                    </div>
                    @endif
                @endforeach
            </div>
            @endif
            @if($resto->isNotEmpty())
            <ul class="ra-list ra-list-compact">
                @foreach($resto as $i => $entry)
                @php $pos = $i + 4; @endphp
                <li class="ra-list-item">
                    <span class="ra-list-pos">{{ $pos }}</span>
                    <div class="ra-list-body">
                        <span class="ra-list-name">{{ $entry['aluno']->nome }}</span>
                        <span class="ra-list-meta">N.º {{ $entry['aluno']->numero_ordem ?? '—' }}</span>
                    </div>
                    <span class="ra-list-score">{{ $entry['media'] }}</span>
                </li>
                @endforeach
            </ul>
            @endif
        </div>
    @else
        @php
            $multiplosCursos = $melhores_por_turma->pluck('turma.curso_id')->unique()->count() > 1;
            $accIdx = 0;
        @endphp
        <div class="accordion ra-accordion" id="rankingTurmas">
            @foreach($melhores_por_turma->groupBy(fn ($it) => $it['turma']->curso_id) as $cursoIdGrp => $itemsCurso)
            @if(!$turmaId && $multiplosCursos)
            <p class="ra-curso-label">{{ $itemsCurso->first()['turma']->curso?->nome ?? 'Sem curso' }}</p>
            @endif
            @foreach($itemsCurso->sortBy(fn ($it) => $it['turma']->nome) as $item)
            @php
                $accIdx++;
                $turmaItem = $item['turma'];
                $rankingTurma = $item['ranking'] ?? collect();
                $collapseId = 'rankTurma'.$turmaItem->id;
                $expanded = $accIdx === 1 && $melhores_por_turma->count() <= 3;
            @endphp
            <div class="accordion-item ra-acc-item">
                <h3 class="accordion-header">
                    <button class="accordion-button {{ $expanded ? '' : 'collapsed' }} ra-acc-btn"
                            type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}"
                            aria-expanded="{{ $expanded ? 'true' : 'false' }}">
                        <span class="ra-acc-turma">{{ $turmaItem->nome }}</span>
                        @if($turmaItem->numeroClasse())
                        <span class="ra-pill ra-pill-sm">{{ $turmaItem->numeroClasse() }}ª</span>
                        @endif
                        <span class="ra-acc-curso d-none d-sm-inline">{{ $turmaItem->curso?->nome }}</span>
                        <span class="ra-acc-count ms-auto">
                            @if($rankingTurma->isNotEmpty())
                                {{ $rankingTurma->count() }} alunos
                            @else
                                sem elegíveis
                            @endif
                        </span>
                    </button>
                </h3>
                <div id="{{ $collapseId }}" class="accordion-collapse collapse {{ $expanded ? 'show' : '' }}"
                     data-bs-parent="#rankingTurmas">
                    <div class="accordion-body p-0">
                        @if($rankingTurma->isEmpty())
                        <div class="ra-empty py-3">Nenhum aluno sem negativas nesta turma.</div>
                        @else
                        <ul class="ra-list">
                            @foreach($rankingTurma as $i => $entry)
                            @php $pos = $i + 1; @endphp
                            <li class="ra-list-item {{ $pos <= 3 ? 'is-medal-'.$pos : '' }}">
                                <span class="ra-list-pos">
                                    @if($pos <= 3){{ $medalIcons[$pos] }}@else{{ $pos }}@endif
                                </span>
                                <div class="ra-list-body">
                                    <span class="ra-list-name">{{ $entry['aluno']->nome }}</span>
                                    <span class="ra-list-meta">
                                        N.º {{ $entry['aluno']->numero_ordem ?? '—' }}
                                        · {{ $entry['total_disc'] }} disc.
                                    </span>
                                </div>
                                <span class="ra-list-score">{{ $entry['media'] }}</span>
                            </li>
                            @endforeach
                        </ul>
                        @endif
                        <div class="ra-acc-footer">
                            <a href="{{ route('pautas.trimestral-completa', ['turma_id' => $turmaItem->id, 'trimestre' => $pautaTriDefault, 'ano_lectivo' => $anoLectivo]) }}">
                                Ver pauta <i class="bi bi-arrow-right-short"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
            @endforeach
        </div>
    @endif
</section>
