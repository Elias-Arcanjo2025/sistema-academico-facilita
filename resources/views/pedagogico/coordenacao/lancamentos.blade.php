<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0"><i class="bi bi-clipboard-check me-2"></i>Lançamentos da disciplina</h2>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-eye" variant="info" title="Supervisão (só leitura)" id="tip-coord-lancamentos" compact>
                Acompanhe quem já lançou MAC, PP e PT nas turmas das disciplinas e classes que coordena. Não permite lançar nem editar notas.
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
                <div class="col-md-3"><div class="card text-center p-2"><div class="fw-semibold text-success">{{ $dados['resumo']['completo'] ?? 0 }}</div><small>Completos</small></div></div>
                <div class="col-md-3"><div class="card text-center p-2"><div class="fw-semibold text-warning">{{ $dados['resumo']['parcial'] ?? 0 }}</div><small>Parciais</small></div></div>
                <div class="col-md-3"><div class="card text-center p-2"><div class="fw-semibold text-danger">{{ $dados['resumo']['em_falta'] ?? 0 }}</div><small>Em falta</small></div></div>
                <div class="col-md-3"><div class="card text-center p-2"><div class="fw-semibold text-muted">{{ $dados['resumo']['sem_alunos'] ?? 0 }}</div><small>Sem alunos</small></div></div>
            </div>

            <div class="card shadow-soft">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Turma</th>
                                <th>Disciplina</th>
                                <th>Professor</th>
                                <th>Alunos</th>
                                <th>MAC</th>
                                <th>PP</th>
                                <th>PT</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dados['linhas'] as $linha)
                                <tr>
                                    <td>{{ $linha['turma'] }}</td>
                                    <td>{{ $linha['disciplina'] }}</td>
                                    <td>{{ $linha['professor'] }}</td>
                                    <td>{{ $linha['total_alunos'] }}</td>
                                    @foreach(['MAC','PP','PT'] as $tipo)
                                        @php $e = $linha['estados'][$tipo] ?? 'em_falta'; @endphp
                                        <td>
                                            <span class="badge bg-{{ $e === 'completo' ? 'success' : ($e === 'parcial' ? 'warning' : ($e === 'sem_alunos' ? 'secondary' : 'danger')) }}">
                                                {{ $linha['contagens'][$tipo] ?? 0 }}/{{ $linha['total_alunos'] }}
                                            </span>
                                        </td>
                                    @endforeach
                                    <td>
                                        @php $g = $linha['estado_geral']; @endphp
                                        <span class="badge bg-{{ $g === 'completo' ? 'success' : ($g === 'parcial' ? 'warning' : ($g === 'sem_alunos' ? 'secondary' : 'danger')) }}">
                                            {{ str_replace('_', ' ', $g) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Sem associações no seu âmbito de coordenação.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
