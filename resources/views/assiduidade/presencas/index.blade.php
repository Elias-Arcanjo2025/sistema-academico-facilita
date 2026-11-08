<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Presenças</h2>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Assiduidade', 'url' => route('presencas.index')],
                ['label' => 'Presenças', 'url' => route('presencas.index')],
            ]" />

            <x-tip-card icon="bi-clipboard-check" variant="info" title="Chamada por aula" id="tip-presencas-index" compact>
                Seleccione a data e uma aula do horário para lançar ou editar a chamada. Trimestre actual: {{ $trimestre ?? '—' }}.
            </x-tip-card>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('presencas.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <x-ano-lectivo-selector
                                :anoLectivoId="$anoLectivoId"
                                :anosLectivos="$anosLectivos"
                                size="sm"
                                label="Ano Lectivo"
                                class="mb-0"
                            />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-calendar-date me-1"></i>Data</label>
                            <input type="date" name="data" value="{{ $data }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-funnel me-1"></i>Filtrar
                            </button>
                            <a href="{{ route('presencas.index') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x-lg me-1"></i> Limpar
                            </a>
                        </div>
                    </form>
                    @if(!($podeEditar ?? true))
                        <p class="text-muted small mb-0 mt-2">
                            <i class="bi bi-lock-fill me-1"></i>Ano anterior em modo consulta — só o administrador pode alterar chamadas.
                        </p>
                    @endif
                </div>
            </div>

            @if($slots->isEmpty())
                <div class="card">
                    <div class="card-body">
                        <x-empty-state
                            icon="bi-clipboard-check"
                            message="Não há aulas no horário para esta data (ou não tem acesso a nenhum slot)."
                            :can-create="false"
                        />
                    </div>
                </div>
            @else
                <x-data-table
                    title="Aulas do dia"
                    icon="bi-clipboard-check"
                    :badge="$slots->count()"
                    :searchable="true">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-nowrap">Hora</th>
                                <th class="text-nowrap">Turma</th>
                                <th class="text-nowrap">Disciplina</th>
                                <th class="text-nowrap">Professor</th>
                                <th class="text-nowrap">Sala</th>
                                <th class="text-nowrap">Estado</th>
                                <th class="text-nowrap text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($slots as $slot)
                                @php $existente = $existentes->get($slot->id); @endphp
                                <tr>
                                    <td class="text-nowrap">{{ \Illuminate\Support\Str::substr($slot->hora_inicio, 0, 5) }}–{{ \Illuminate\Support\Str::substr($slot->hora_fim, 0, 5) }}</td>
                                    <td class="text-nowrap">{{ $slot->turma?->nome }}</td>
                                    <td class="text-nowrap">{{ $slot->disciplina?->nome }}</td>
                                    <td class="text-nowrap">{{ $slot->professor?->name ?? '—' }}</td>
                                    <td class="text-nowrap">{{ $slot->sala?->nome ?? '—' }}</td>
                                    <td class="text-nowrap">
                                        @if($existente)
                                            <span class="badge bg-success">Registada</span>
                                        @else
                                            <span class="badge bg-secondary">Pendente</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap text-end">
                                        <a href="{{ route('presencas.aula', ['horario' => $slot->id, 'data' => $data, 'ano_lectivo_id' => $anoLectivoId]) }}" class="btn btn-sm {{ ($podeEditar ?? true) ? 'btn-primary' : 'btn-outline-secondary' }}">
                                            @if(!($podeEditar ?? true))
                                                Consultar
                                            @else
                                                {{ $existente ? 'Editar' : 'Lançar' }}
                                            @endif
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
</x-app-layout>
