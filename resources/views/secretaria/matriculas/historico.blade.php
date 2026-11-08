<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Histórico — {{ $aluno->nome }}</h2>
            <div class="d-flex gap-2">
                @can('secretaria.matriculas.gerir')
                    <a href="{{ route('secretaria.matriculas.renovar', $aluno) }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-arrow-repeat me-1"></i> Renovar
                    </a>
                @endcan
                <a href="{{ route('alunos.show', $aluno) }}" class="btn btn-sm btn-outline-secondary">Ficha do aluno</a>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Secretaria', 'url' => route('secretaria.dashboard')],
                ['label' => 'Matrículas', 'url' => route('secretaria.matriculas.index')],
                ['label' => $aluno->nome, 'url' => route('secretaria.matriculas.historico', $aluno)],
            ]" />

            <x-tip-card icon="bi-clock-history" variant="info" title="Histórico do aluno" id="tip-secretaria-matriculas-historico" compact>
                Cronologia de matrículas, transferências e renovações deste aluno. Use Renovar para o próximo ano lectivo.
            </x-tip-card>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-body row g-2">
                    <div class="col-md-4"><strong>Processo:</strong> <code>{{ $aluno->numero_processo }}</code></div>
                    <div class="col-md-8">
                        <strong>Turma actual:</strong>
                        {{ $aluno->turma->nome ?? '—' }}
                        @if($aluno->turma?->curso)
                            <small class="text-muted">· {{ $aluno->turma->curso->nome }}</small>
                        @endif
                    </div>
                </div>
            </div>

            @if($aluno->historicosMatricula->isEmpty())
                <div class="card">
                    <div class="card-body">
                        <x-empty-state icon="bi-clock-history" message="Sem histórico para este aluno." :can-create="false" />
                    </div>
                </div>
            @else
                <x-data-table
                    title="Eventos de matrícula"
                    icon="bi-clock-history"
                    :badge="$aluno->historicosMatricula->count()">
                    <table>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th>Detalhe</th>
                                <th>Motivo</th>
                                <th>Por</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($aluno->historicosMatricula as $h)
                                <tr>
                                    <td class="text-nowrap">{{ $h->created_at?->format('d/m/Y H:i') }}</td>
                                    <td><span class="badge bg-secondary">{{ $tipos[$h->tipo] ?? $h->tipo }}</span></td>
                                    <td>
                                        @if($h->de_turma_id || $h->para_turma_id)
                                            {{ $h->deTurma->nome ?? '—' }} → {{ $h->paraTurma->nome ?? '—' }}
                                        @else
                                            {{ $h->turma->nome ?? '—' }}
                                        @endif
                                    </td>
                                    <td><small class="text-muted">{{ $h->motivo ?: '—' }}</small></td>
                                    <td>{{ $h->registadoPor->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-data-table>
            @endif
        </div>
    </div>
</x-app-layout>
