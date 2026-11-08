<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0"><i class="bi bi-bar-chart me-2"></i>Rendimento da disciplina</h2>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-graph-up" variant="info" title="Relatório da disciplina" id="tip-coord-relatorio" compact>
                Médias por turma e por professor, negativas e evolução — só no âmbito das disciplinas/classes que coordena.
            </x-tip-card>

            <div class="card shadow-soft mb-3">
                <div class="card-body py-3">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <x-ano-lectivo-selector
                                :anoLectivoId="$anoLectivoId"
                                :anosLectivos="$anosLectivos"
                                size="sm"
                                label="Ano Lectivo"
                                class="mb-0"
                            />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Trimestre</label>
                            <select name="trimestre" class="form-select form-select-sm">
                                @for($t = 1; $t <= 3; $t++)
                                    <option value="{{ $t }}" @selected($trimestre == $t)>{{ $t }}º</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-outline-secondary w-100">Filtrar</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row g-2 mb-3">
                @foreach(['MAC','PP','PT','MT'] as $tipo)
                    <div class="col-md-2">
                        <div class="card text-center p-3">
                            <div class="fw-semibold">{{ $dados['medias_globais'][$tipo] ?? '—' }}</div>
                            <small>Média {{ $tipo }}</small>
                        </div>
                    </div>
                @endforeach
                <div class="col-md-2">
                    <div class="card text-center p-3">
                        <div class="fw-semibold text-danger">{{ $dados['negativas']['total'] ?? 0 }}</div>
                        <small>Negativas MT
                            @if(($dados['negativas']['percentagem'] ?? null) !== null)
                                ({{ $dados['negativas']['percentagem'] }}%)
                            @endif
                        </small>
                    </div>
                </div>
            </div>

            <div class="card shadow-soft mb-3">
                <div class="card-header">Evolução das médias MT</div>
                <div class="card-body row text-center">
                    @foreach($dados['evolucao'] as $ev)
                        <div class="col-md-4">
                            <div class="fw-semibold">{{ $ev['media_mt'] ?? '—' }}</div>
                            <small>{{ $ev['trimestre'] }}º trimestre</small>
                        </div>
                    @endforeach
                </div>
            </div>

            @if($dados['alertas']->isNotEmpty())
                <div class="alert alert-warning">
                    <strong>Alertas (MT &lt; {{ $dados['nota_minima'] }}):</strong>
                    <ul class="mb-0 mt-2">
                        @foreach($dados['alertas'] as $alerta)
                            <li>{{ $alerta['disciplina'] }} · {{ $alerta['turma'] }} — MT {{ $alerta['media_mt'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card shadow-soft mb-3">
                <div class="card-header">Aproveitamento por turma</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Turma</th>
                                <th>Disciplina</th>
                                <th>MAC</th>
                                <th>PP</th>
                                <th>PT</th>
                                <th>MT</th>
                                <th>Negativas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dados['por_turma'] as $linha)
                                <tr>
                                    <td>{{ $linha['turma'] }}</td>
                                    <td>{{ $linha['disciplina'] }}</td>
                                    @foreach(['MAC','PP','PT','MT'] as $tipo)
                                        <td>{{ $linha['medias'][$tipo] ?? '—' }}</td>
                                    @endforeach
                                    <td>{{ $linha['negativas_mt'] }}/{{ $linha['total_mt'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Sem dados de notas no seu âmbito.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow-soft">
                <div class="card-header">Aproveitamento por professor</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Professor</th>
                                <th>Turmas</th>
                                <th>Média MT</th>
                                <th>Negativas MT</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dados['por_professor'] as $linha)
                                <tr>
                                    <td>{{ $linha['professor'] }}</td>
                                    <td>{{ $linha['turmas'] }}</td>
                                    <td>{{ $linha['media_mt'] ?? '—' }}</td>
                                    <td>{{ $linha['negativas_mt'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Sem professores no âmbito.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
