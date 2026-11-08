<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="h4 mb-0">Plano de Aula v{{ $planoAula->versao }}</h2>
                <small class="text-muted">
                    {{ $planoAula->tempos ?? 1 }} tempo{{ ($planoAula->tempos ?? 1) > 1 ? 's' : '' }}
                    ({{ ($planoAula->tempos ?? 1) * 45 }} min)
                    · Estado: <span class="badge bg-{{ $planoAula->estado === 'dado' ? 'success' : 'secondary' }}">{{ $planoAula->estado }}</span>
                </small>
            </div>
            <div class="d-flex gap-2">
                @can('pedagogico.planificacao.editar')
                    @if($planoAula->estado !== 'dado')
                    <form method="POST" action="{{ route('pedagogico.planos.marcar-dado', $planoAula) }}" class="d-inline"
                          data-confirm-title="Marcar como dado"
                          data-confirm="Marcar este plano como dado/ministrado?"
                          data-confirm-ok="Sim, marcar"
                          data-confirm-color="#198754">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="bi bi-check-circle"></i> Marcar como dado
                        </button>
                    </form>
                    @endif
                @endcan
                <a href="{{ route('pedagogico.planos.pdf', $planoAula) }}" class="btn btn-outline-secondary btn-sm">Exportar PDF</a>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @php
                $c = $planoAula->conteudo_json ?? [];
                $meta = $c['_meta'] ?? [];
                $textoDitar = $c['conteudo_ditar'] ?? '';
                $palavrasGeradas = count(preg_split('/\s+/u', trim(strip_tags($textoDitar)), -1, PREG_SPLIT_NO_EMPTY));
                $palavrasAlvo = $meta['palavras_alvo'] ?? null;
                $exerciciosGerados = is_array($c['exercicios_sala'] ?? null) ? count($c['exercicios_sala']) : 0;
                $conceitosGerados = is_array($c['conceitos'] ?? null) ? count($c['conceitos']) : 0;
                $tarefasGeradas = is_array($c['tarefa_casa'] ?? null) ? count($c['tarefa_casa']) : 0;
            @endphp

            @if(!empty($meta))
            <div class="alert alert-light border small py-2 mb-3">
                <i class="bi bi-bar-chart me-1"></i>
                <strong>Volume gerado ({{ $meta['tempos'] ?? $planoAula->tempos }}T / {{ $meta['duracao_minutos'] ?? (($planoAula->tempos ?? 1) * 45) }} min):</strong>
                {{ $palavrasGeradas }} palavras a ditar
                @if($palavrasAlvo) (alvo: ≈{{ $palavrasAlvo }}) @endif,
                {{ $conceitosGerados }} conceitos
                @if(!empty($meta['conceitos_alvo'])) (alvo: {{ $meta['conceitos_alvo'] }}) @endif,
                {{ $exerciciosGerados }} exercícios
                @if(!empty($meta['exercicios_alvo'])) (alvo: {{ $meta['exercicios_alvo'] }}) @endif,
                {{ $tarefasGeradas }} tarefas de casa
                @if(!empty($meta['tarefas_alvo'])) (alvo: {{ $meta['tarefas_alvo'] }}) @endif.
                @if($palavrasAlvo && $palavrasGeradas < ($palavrasAlvo * 0.7))
                    <span class="text-warning d-block mt-1">O texto está abaixo do volume ideal — considere regenerar com mais tempos ou editar manualmente.</span>
                @endif
            </div>
            @endif

            @if($planoAula->ministrado_em)
            <div class="alert alert-success py-2">
                <i class="bi bi-check2-circle me-1"></i>
                Ministrado em {{ $planoAula->ministrado_em->format('d/m/Y \à\s H:i') }}
            </div>
            @endif

            <div class="card mb-3 border-info">
                <div class="card-header bg-info text-white"><i class="bi bi-book me-1"></i> Definição de conceitos</div>
                <div class="card-body">
                    @forelse($c['conceitos'] ?? [] as $conceito)
                        @php
                            $termo = is_array($conceito) ? ($conceito['termo'] ?? $conceito['nome'] ?? '') : '';
                            $definicao = is_array($conceito) ? ($conceito['definicao'] ?? $conceito['descricao'] ?? '') : (string) $conceito;
                        @endphp
                        @if($termo || $definicao)
                        <div class="mb-3 pb-3 border-bottom">
                            @if($termo)<strong>{{ $termo }}</strong>@endif
                            @if($definicao)
                                <p class="mb-0 mt-1 {{ $termo ? '' : 'mb-0' }}">{{ $definicao }}</p>
                            @endif
                        </div>
                        @endif
                    @empty
                        <p class="text-muted mb-0">—</p>
                    @endforelse
                </div>
            </div>

            <div class="card mb-3 border-primary">
                <div class="card-header bg-primary text-white"><i class="bi bi-megaphone me-1"></i> Conteúdo a ditar na sala de aula</div>
                <div class="card-body">{!! nl2br(e($c['conteudo_ditar'] ?? '—')) !!}</div>
            </div>

            <div class="card mb-3 border-success">
                <div class="card-header bg-success text-white"><i class="bi bi-chat-dots me-1"></i> Exercícios, perguntas e casos práticos (sala)</div>
                <div class="card-body">
                    @forelse($c['exercicios_sala'] ?? [] as $item)
                        @php
                            $tipo = is_array($item) ? ($item['tipo'] ?? 'exercicio') : 'exercicio';
                            $enunciado = is_array($item) ? ($item['enunciado'] ?? json_encode($item)) : $item;
                            $resolucao = is_array($item) ? ($item['resolucao_orientadora'] ?? null) : null;
                        @endphp
                        <div class="mb-3 pb-3 border-bottom">
                            <span class="badge bg-secondary text-uppercase">{{ $tipo }}</span>
                            <p class="mb-1 mt-1">{{ $enunciado }}</p>
                            @if($resolucao)
                                <small class="text-muted"><strong>Orientação:</strong> {{ $resolucao }}</small>
                            @endif
                        </div>
                    @empty
                        @foreach(array_merge($c['perguntas_interacao'] ?? [], $c['exemplos'] ?? []) as $item)
                            <p class="mb-2">• {{ is_array($item) ? json_encode($item) : $item }}</p>
                        @endforeach
                        @if(empty($c['perguntas_interacao']) && empty($c['exemplos']))
                            <p class="text-muted mb-0">—</p>
                        @endif
                    @endforelse
                </div>
            </div>

            <div class="card mb-3 border-warning">
                <div class="card-header bg-warning"><i class="bi bi-house me-1"></i> Tarefas para casa</div>
                <div class="card-body">
                    @if(is_array($c['tarefa_casa'] ?? null) && count($c['tarefa_casa']))
                        <ol class="mb-0">
                            @foreach($c['tarefa_casa'] as $tarefa)
                                <li>{{ is_array($tarefa) ? ($tarefa['enunciado'] ?? json_encode($tarefa)) : $tarefa }}</li>
                            @endforeach
                        </ol>
                    @elseif(!empty($c['tarefa_casa']))
                        {!! nl2br(e($c['tarefa_casa'])) !!}
                    @else
                        <p class="text-muted mb-0">—</p>
                    @endif
                </div>
            </div>

            @if(!empty($c['tema']))
            <div class="card mb-3">
                <div class="card-header">Tema</div>
                <div class="card-body">{{ $c['tema'] }}</div>
            </div>
            @endif

            @if(!empty($c['objetivos']))
            <div class="card mb-3">
                <div class="card-header">Objectivos</div>
                <div class="card-body">
                    <ul class="mb-0">@foreach((array)$c['objetivos'] as $o)<li>{{ $o }}</li>@endforeach</ul>
                </div>
            </div>
            @endif

            <a href="{{ route('pedagogico.planos.index', $planoAula->planificacao) }}" class="btn btn-outline-secondary">Voltar</a>
        </div>
    </div>
</x-app-layout>
