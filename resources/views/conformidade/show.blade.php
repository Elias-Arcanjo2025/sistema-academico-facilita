<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">{{ $turma->nome ?? $turma->codigo }}</h2>
            <div class="d-flex gap-2">
                <a href="{{ route('pauta-certificada.inspeccao', ['turma' => $turma, 'trimestre' => $analise['meta']['trimestre'] ?? 1]) }}"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-download me-1"></i> Exportar JSON
                </a>
                <a href="{{ route('pauta-certificada.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Voltar
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Análise', 'url' => route('radar.index')],
                ['label' => 'Pauta Certificada', 'url' => route('pauta-certificada.index')],
                ['label' => $turma->nome ?? $turma->codigo, 'url' => '#'],
            ]" />

            @php
                $selo = $analise['selo'] ?? '—';
                $badge = match ($selo) {
                    'conforme' => 'bg-success',
                    'alerta' => 'bg-warning text-dark',
                    default => 'bg-danger',
                };
            @endphp

            <x-tip-card icon="bi-patch-check" variant="info" title="Análise de conformidade" id="tip-pauta-certificada-show" compact>
                Resultado da verificação DE 424/25 para esta turma.
                {{ $analise['meta']['norma'] ?? 'DE 424/25' }} · T{{ $analise['meta']['trimestre'] ?? '' }} · {{ $analise['meta']['ano_lectivo'] ?? '' }}.
                Use o simulador abaixo para testar impactos sem gravar notas.
            </x-tip-card>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div class="text-muted small">
                    <a href="{{ route('turmas.show', $turma) }}" class="text-decoration-none">Ver ficha da turma</a>
                </div>
                <span class="badge {{ $badge }} fs-6 px-3 py-2 text-uppercase">Selo: {{ $selo }}</span>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-md-2">
                    <div class="card h-100"><div class="card-body py-3">
                        <div class="small text-muted">Checks</div>
                        <div class="fs-4 fw-semibold">{{ $analise['total_checks'] }}</div>
                    </div></div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card h-100"><div class="card-body py-3">
                        <div class="small text-muted">OK</div>
                        <div class="fs-4 fw-semibold text-success">{{ $analise['passed'] }}</div>
                    </div></div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card h-100"><div class="card-body py-3">
                        <div class="small text-muted">Alertas</div>
                        <div class="fs-4 fw-semibold text-warning">{{ $analise['warnings'] }}</div>
                    </div></div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card h-100"><div class="card-body py-3">
                        <div class="small text-muted">Falhas</div>
                        <div class="fs-4 fw-semibold text-danger">{{ $analise['failed'] }}</div>
                    </div></div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card h-100"><div class="card-body py-3">
                        <div class="small text-muted">Cobertura</div>
                        <div class="fs-4 fw-semibold">{{ $analise['meta']['cobertura_pct'] ?? 0 }}%</div>
                    </div></div>
                </div>
            </div>

            <x-data-table
                title="Verificações"
                icon="bi-list-check"
                :badge="count($analise['checks'] ?? [])"
                :searchable="true">
                <table>
                    <thead>
                        <tr>
                            <th>Verificação</th>
                            <th>Estado</th>
                            <th>Detalhe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($analise['checks'] as $check)
                            <tr>
                                <td class="fw-semibold">{{ $check['titulo'] }}</td>
                                <td>
                                    <span class="badge {{ $check['status'] === 'ok' ? 'bg-success' : ($check['status'] === 'warn' ? 'bg-warning text-dark' : 'bg-danger') }}">
                                        {{ $check['status'] }}
                                    </span>
                                </td>
                                <td><small class="text-muted">{{ $check['detalhe'] }}</small></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-data-table>

            <div class="card mt-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-sliders me-1"></i> Simulador de conselho
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Teste o impacto de subir ou baixar uma avaliação <strong>sem gravar</strong> na base de dados.
                        Escolha a disciplina (e opcionalmente um aluno), o tipo de nota e o delta.
                    </p>

                    @php
                        $disciplinas = $disciplinas ?? collect();
                        $alunos = $alunos ?? collect();
                    @endphp

                    @if($disciplinas->isEmpty())
                        <div class="alert alert-warning mb-0">
                            Esta turma não tem disciplinas no plano curricular — não é possível simular.
                        </div>
                    @else
                        <form method="POST" action="{{ route('pauta-certificada.simular', $turma) }}" class="row g-3 align-items-end" id="formSimuladorConselho">
                            @csrf
                            <input type="hidden" name="trimestre" value="{{ $analise['meta']['trimestre'] }}">

                            <div class="col-md-4">
                                <label class="form-label" for="sim_aluno_id">Aluno</label>
                                <select name="aluno_id" id="sim_aluno_id" class="form-select">
                                    <option value="">Todos os alunos</option>
                                    @foreach($alunos as $aluno)
                                        <option value="{{ $aluno->id }}">
                                            {{ $aluno->numero_ordem ? $aluno->numero_ordem.'. ' : '' }}{{ $aluno->nome }}
                                            @if($aluno->numero_processo) ({{ $aluno->numero_processo }}) @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="sim_disciplina_id">Disciplina *</label>
                                <select name="disciplina_id" id="sim_disciplina_id" class="form-select" required>
                                    <option value="">Seleccione a disciplina…</option>
                                    @foreach($disciplinas as $disciplina)
                                        <option value="{{ $disciplina->id }}">
                                            {{ $disciplina->nome }}@if($disciplina->codigo) ({{ $disciplina->codigo }})@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label" for="sim_tipo">Tipo *</label>
                                <select name="tipo" id="sim_tipo" class="form-select" required>
                                    <option value="MAC">MAC</option>
                                    <option value="PP">PP</option>
                                    <option value="PT">PT</option>
                                    <option value="PG">PG</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label" for="sim_delta">Delta *</label>
                                <input type="number" step="0.5" min="-5" max="5" name="delta" id="sim_delta" value="-1" class="form-control" required>
                                <div class="form-text">Ex.: -1 ou +0.5</div>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary" id="btnSimularConselho">
                                    <i class="bi bi-play-circle me-1"></i> Simular
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        $(function () {
            const opts = {
                theme: 'bootstrap-5',
                width: '100%',
                language: {
                    noResults: () => 'Nenhum resultado',
                    searching: () => 'A procurar...'
                }
            };

            $('#sim_aluno_id').select2({
                ...opts,
                allowClear: true,
                placeholder: 'Todos os alunos'
            });

            $('#sim_disciplina_id').select2({
                ...opts,
                placeholder: 'Seleccione a disciplina…'
            });

            $('#formSimuladorConselho').on('submit', function () {
                const $btn = $('#btnSimularConselho');
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> A simular…');
            });
        });
    </script>
    @endpush
</x-app-layout>
