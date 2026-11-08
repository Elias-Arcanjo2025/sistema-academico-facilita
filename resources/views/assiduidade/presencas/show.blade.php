<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Registo de presença</h2>
            <div class="d-flex gap-2">
                <a href="{{ route('presencas.index', ['data' => $aula->data->toDateString()]) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Voltar
                </a>
                @can('presencas.lancar')
                    @if($aula->turma_horario_id)
                        <a href="{{ route('presencas.aula', ['horario' => $aula->turma_horario_id, 'data' => $aula->data->toDateString()]) }}" class="btn btn-outline-primary btn-sm">
                            Editar chamada
                        </a>
                    @endif
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Assiduidade', 'url' => route('presencas.index')],
                ['label' => 'Presenças', 'url' => route('presencas.index', ['data' => $aula->data->toDateString()])],
                ['label' => 'Registo', 'url' => route('presencas.show', $aula)],
            ]" />

            <x-tip-card icon="bi-clipboard-data" variant="info" title="Registo da aula" id="tip-presencas-show" compact>
                Consulte o estado de cada aluno. Com permissão, pode justificar faltas ou atrasos directamente nesta lista.
            </x-tip-card>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="fw-semibold">{{ $aula->turma?->nome }} · {{ $aula->disciplina?->nome }}</div>
                    <div class="small text-muted">
                        {{ $aula->data->format('d/m/Y') }} · {{ \Illuminate\Support\Str::substr($aula->hora_inicio, 0, 5) }}
                        · Registado por {{ $aula->registadoPor?->name }}
                    </div>
                </div>
            </div>

            @php $registosOrdenados = $aula->registos->sortBy(fn ($r) => $r->aluno?->numero_ordem); @endphp

            @if($registosOrdenados->isEmpty())
                <div class="card">
                    <div class="card-body">
                        <x-empty-state icon="bi-clipboard-x" message="Sem registos nesta chamada." :can-create="false" />
                    </div>
                </div>
            @else
                <x-data-table
                    title="Alunos da chamada"
                    icon="bi-people"
                    :badge="$registosOrdenados->count()"
                    :searchable="true">
                    <table>
                        <thead>
                            <tr>
                                <th>N.º</th>
                                <th>Aluno</th>
                                <th>Estado</th>
                                <th>Justificação</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($registosOrdenados as $registo)
                                <tr>
                                    <td>{{ $registo->aluno?->numero_ordem }}</td>
                                    <td class="fw-semibold">{{ $registo->aluno?->nome }}</td>
                                    <td>
                                        @php
                                            $badge = match ($registo->estado) {
                                                'presente' => 'bg-success',
                                                'falta_justificada' => 'bg-info',
                                                'atraso' => 'bg-warning text-dark',
                                                default => 'bg-danger',
                                            };
                                        @endphp
                                        <span class="badge {{ $badge }}">{{ $estados[$registo->estado] ?? $registo->estado }}</span>
                                    </td>
                                    <td><small class="text-muted">{{ $registo->justificacao ?: '—' }}</small></td>
                                    <td class="text-end">
                                        @can('presencas.justificar')
                                            @if(in_array($registo->estado, ['falta_injustificada', 'atraso'], true))
                                                <form method="POST" action="{{ route('presencas.justificar', $registo) }}" class="d-inline-flex gap-1 justify-content-end">
                                                    @csrf
                                                    <input type="text" name="justificacao" class="form-control form-control-sm" placeholder="Motivo" required maxlength="500" style="min-width:10rem">
                                                    <button class="btn btn-sm btn-outline-primary">Justificar</button>
                                                </form>
                                            @else
                                                —
                                            @endif
                                        @else
                                            —
                                        @endcan
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
