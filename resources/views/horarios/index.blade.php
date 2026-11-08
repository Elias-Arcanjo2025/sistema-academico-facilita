<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">
                <i class="bi bi-calendar-week me-2 text-primary"></i>Horários Anuais de Aula
            </h2>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[['label' => 'Horários Anuais']]" />

            <x-tip-card icon="bi-calendar-week" variant="primary" title="Horário anual por turma">
                O horário de aulas é definido para todo o <strong>ano lectivo</strong> e reflecte-se no portal do aluno.
                Para o período de provas, use o <a href="{{ route('calendario-provas.index') }}">Calendário de avaliações</a> (com geração por IA).
            </x-tip-card>

            @if($turmas->isEmpty())
                <x-empty-state icon="bi-calendar-x" message="Nenhuma turma disponível para gestão de horários." />
            @else
                <div class="card shadow-soft">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Turma</th>
                                    <th>Curso</th>
                                    <th>Classe</th>
                                    <th class="text-center">Aulas/semana</th>
                                    <th class="text-end">Acções</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($turmas as $turma)
                                    <tr>
                                        <td class="fw-semibold">{{ $turma->nome }}</td>
                                        <td>{{ $turma->curso?->nome }}</td>
                                        <td>{{ $turma->classe?->nome ?? '-' }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-{{ ($contagens[$turma->id] ?? 0) > 0 ? 'success' : 'secondary' }}">
                                                {{ $contagens[$turma->id] ?? 0 }}
                                            </span>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <a href="{{ route('horarios.show', $turma) }}" class="btn btn-sm btn-outline-info">
                                                <i class="bi bi-eye"></i> Ver
                                            </a>
                                            @can('horarios.gerir')
                                                @if(\App\Services\HorarioEscolarAccess::podeGerir(auth()->user()))
                                                    <a href="{{ route('horarios.edit', $turma) }}" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-pencil"></i> Gerir
                                                    </a>
                                                @endif
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
