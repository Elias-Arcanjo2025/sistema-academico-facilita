<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Histórico de matrículas</h2>
            @can('secretaria.matriculas.gerir')
                <div class="d-flex gap-2">
                    <a href="{{ route('secretaria.matriculas.import') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Importar Excel
                    </a>
                    <a href="{{ route('secretaria.matriculas.create') }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> Nova matrícula
                    </a>
                </div>
            @endcan
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Secretaria', 'url' => route('secretaria.dashboard')],
                ['label' => 'Matrículas', 'url' => route('secretaria.matriculas.index')],
            ]" />

            <x-tip-card icon="bi-journal-bookmark" variant="info" title="Histórico de matrículas" id="tip-secretaria-matriculas-index" compact>
                Consulte matrículas, transferências e mudanças de curso. Use Nova matrícula para registar um aluno, ou Importar Excel para carga em lote.
            </x-tip-card>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('secretaria.matriculas.index') }}" class="row g-3 align-items-end">
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
                            <label class="form-label"><i class="bi bi-tag me-1"></i>Tipo</label>
                            <select name="tipo" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                @foreach($tipos as $valor => $label)
                                    <option value="{{ $valor }}" @selected(($tipoFiltro ?? '') === $valor)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-funnel me-1"></i>Filtrar
                            </button>
                            <a href="{{ route('secretaria.matriculas.index') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x-lg me-1"></i> Limpar
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            @if($historicos->isEmpty())
                <div class="card">
                    <div class="card-body">
                        <x-empty-state
                            icon="bi-journal-bookmark"
                            message="Sem histórico de matrículas."
                            :create-url="route('secretaria.matriculas.create')"
                            create-label="Registar primeira matrícula"
                            :can-create="auth()->user()->can('secretaria.matriculas.gerir')"
                        />
                    </div>
                </div>
            @else
                <x-data-table
                    title="Histórico de matrículas"
                    icon="bi-journal-bookmark"
                    :badge="$historicos->total()"
                    :pagination="$historicos">
                    <table>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th>Aluno</th>
                                <th>Turma</th>
                                <th>Motivo</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($historicos as $h)
                                <tr>
                                    <td class="text-nowrap">{{ $h->created_at?->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $tipos[$h->tipo] ?? $h->tipo }}</span>
                                    </td>
                                    <td class="fw-semibold">{{ $h->aluno->nome ?? '—' }}</td>
                                    <td>
                                        @if($h->tipo === 'transferencia' || $h->tipo === 'mudanca_curso')
                                            {{ $h->deTurma->nome ?? '—' }}
                                            <span class="text-muted">→</span>
                                            {{ $h->paraTurma->nome ?? '—' }}
                                        @else
                                            {{ $h->turma->nome ?? '—' }}
                                        @endif
                                    </td>
                                    <td class="text-wrap" style="min-width: 10rem;">
                                        <small class="text-muted">{{ $h->motivo ?: '—' }}</small>
                                    </td>
                                    <td class="text-end">
                                        @if($h->aluno)
                                            <a href="{{ route('secretaria.matriculas.historico', $h->aluno) }}" class="btn btn-sm btn-outline-primary" title="Ver histórico">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-data-table>
            @endif
        </div>
    </div>
</x-app-layout>
