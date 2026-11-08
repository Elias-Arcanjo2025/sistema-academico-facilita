<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Painel do Inspetor</h2>
            <div class="d-flex gap-2">
                @can('presencas.ver')
                    <a href="{{ route('presencas.index', ['data' => $hoje, 'ano_lectivo_id' => $anoLectivoId]) }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-clipboard-check me-1"></i> Presenças
                    </a>
                @endcan
                @can('ocorrencias.ver')
                    <a href="{{ route('ocorrencias.index', ['ano_lectivo_id' => $anoLectivoId]) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-exclamation-triangle me-1"></i> Ocorrências
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Assiduidade', 'url' => route('presencas.index')],
                ['label' => 'Painel Inspetor', 'url' => route('inspetor.painel')],
            ]" />

            <x-tip-card icon="bi-shield-check" variant="info" title="Visão de disciplina escolar" id="tip-inspetor-painel" compact>
                Resumo do dia: chamadas feitas, faltas e ocorrências abertas. Use as listas abaixo para lançar chamada ou tratar ocorrências graves.
            </x-tip-card>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('inspetor.painel') }}" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <x-ano-lectivo-selector
                                :anoLectivoId="$anoLectivoId"
                                :anosLectivos="$anosLectivos"
                                size="sm"
                                label="Ano Lectivo"
                                class="mb-0"
                            />
                        </div>
                    </form>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-1"><i class="bi bi-clipboard-check me-1"></i>Chamadas hoje</div>
                            <div class="fs-3 fw-semibold">{{ $aulasHoje }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-1"><i class="bi bi-person-x me-1"></i>Faltas hoje</div>
                            <div class="fs-3 fw-semibold">{{ $faltasHoje }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Ocorrências abertas</div>
                            <div class="fs-3 fw-semibold">{{ $ocorrenciasAbertas }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    @if($slotsHoje->isEmpty())
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span class="fw-semibold"><i class="bi bi-calendar-day me-1"></i>Aulas de hoje</span>
                                <a href="{{ route('presencas.index', ['data' => $hoje]) }}" class="btn btn-sm btn-outline-primary">Ver todas</a>
                            </div>
                            <div class="card-body">
                                <x-empty-state icon="bi-calendar-x" message="Sem aulas no horário para hoje." :can-create="false" />
                            </div>
                        </div>
                    @else
                        <x-data-table
                            title="Aulas de hoje"
                            icon="bi-calendar-day"
                            :badge="$slotsHoje->take(8)->count()"
                            :searchable="true">
                            <x-slot name="actions">
                                <a href="{{ route('presencas.index', ['data' => $hoje]) }}" class="btn btn-sm btn-outline-primary">Ver todas</a>
                            </x-slot>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Hora</th>
                                        <th>Turma / Disciplina</th>
                                        <th>Professor</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($slotsHoje->take(8) as $slot)
                                        <tr>
                                            <td class="text-nowrap">{{ \Illuminate\Support\Str::substr($slot->hora_inicio, 0, 5) }}</td>
                                            <td>
                                                <div class="fw-semibold">{{ $slot->turma?->nome }}</div>
                                                <small class="text-muted">{{ $slot->disciplina?->nome }}</small>
                                            </td>
                                            <td>{{ $slot->professor?->name ?? '—' }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('presencas.aula', ['horario' => $slot->id, 'data' => $hoje]) }}" class="btn btn-sm btn-primary">
                                                    Chamada
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-data-table>
                    @endif
                </div>
                <div class="col-lg-6">
                    @if($ocorrenciasGraves->isEmpty())
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span class="fw-semibold"><i class="bi bi-exclamation-octagon me-1"></i>Ocorrências graves abertas</span>
                                <a href="{{ route('ocorrencias.index') }}" class="btn btn-sm btn-outline-primary">Ver todas</a>
                            </div>
                            <div class="card-body">
                                <x-empty-state icon="bi-check2-circle" message="Sem ocorrências de severidade alta." :can-create="false" />
                            </div>
                        </div>
                    @else
                        <x-data-table
                            title="Ocorrências graves abertas"
                            icon="bi-exclamation-octagon"
                            :badge="$ocorrenciasGraves->count()"
                            :searchable="true">
                            <x-slot name="actions">
                                <a href="{{ route('ocorrencias.index') }}" class="btn btn-sm btn-outline-primary">Ver todas</a>
                            </x-slot>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Aluno</th>
                                        <th>Turma / Data</th>
                                        <th>Tipo</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ocorrenciasGraves as $oc)
                                        <tr>
                                            <td class="fw-semibold">{{ $oc->aluno?->nome }}</td>
                                            <td>
                                                <div>{{ $oc->turma?->nome }}</div>
                                                <small class="text-muted">{{ $oc->data->format('d/m/Y') }}</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger">{{ \App\Models\Ocorrencia::tipos()[$oc->tipo] ?? $oc->tipo }}</span>
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('ocorrencias.show', $oc) }}" class="btn btn-sm btn-outline-primary" title="Ver">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-data-table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
