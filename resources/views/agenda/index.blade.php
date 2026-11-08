<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Agenda escolar</h2>
            @if($podeGerir)
                <a href="{{ route('agenda.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Novo evento
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Agenda escolar', 'url' => route('agenda.index')],
            ]" />

            <x-tip-card icon="bi-calendar3" variant="info" title="Agenda escolar" id="tip-agenda-index" compact>
                Eventos da escola, curso ou turma (feriados, reuniões, actividades). Alunos e encarregados vêem o que for relevante para a sua turma. Não substitui o calendário de provas.
            </x-tip-card>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('agenda.index') }}" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-calendar3 me-1"></i>Ano lectivo</label>
                            <select name="ano_lectivo_id" class="form-select form-select-sm">
                                @foreach($anosLectivos as $id => $nome)
                                    <option value="{{ $id }}" @selected((string)$anoLectivoId === (string)$id)>{{ $nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-calendar-month me-1"></i>Mês</label>
                            <select name="mes" class="form-select form-select-sm">
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" @selected($mes === $m)>{{ \Carbon\Carbon::create(null, $m, 1)->translatedFormat('F') }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><i class="bi bi-calendar me-1"></i>Ano</label>
                            <input type="number" name="ano" class="form-control form-control-sm" value="{{ $ano }}">
                        </div>
                        <div class="col-md-4 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-funnel me-1"></i>Filtrar
                            </button>
                            <a href="{{ route('agenda.index') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x-lg me-1"></i> Limpar
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            @php
                $totalEventos = $porDia->sum(fn ($eventos) => $eventos->count());
            @endphp

            @if($porDia->isEmpty())
                <div class="card">
                    <div class="card-body">
                        <x-empty-state
                            icon="bi-calendar3"
                            message="Sem eventos neste mês."
                            :create-url="route('agenda.create')"
                            create-label="Criar primeiro evento"
                            :can-create="$podeGerir"
                        />
                    </div>
                </div>
            @else
                <x-data-table
                    title="Eventos do mês"
                    icon="bi-calendar3"
                    :badge="$totalEventos">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-nowrap">Data</th>
                                <th class="text-nowrap">Hora</th>
                                <th class="text-nowrap">Título</th>
                                <th class="text-nowrap">Tipo</th>
                                <th class="text-nowrap">Visibilidade</th>
                                <th class="text-nowrap text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($porDia as $dia => $eventos)
                                @foreach($eventos as $ev)
                                    <tr>
                                        <td class="text-nowrap">{{ \Carbon\Carbon::parse($dia)->format('d/m/Y') }}</td>
                                        <td class="text-nowrap">{{ $ev->inicia_em->format('H:i') }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $ev->titulo }}</div>
                                            @if($ev->descricao)
                                                <small class="text-muted">{{ \Illuminate\Support\Str::limit($ev->descricao, 80) }}</small>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            <span class="badge bg-secondary">{{ $tipos[$ev->tipo] ?? $ev->tipo }}</span>
                                        </td>
                                        <td class="text-nowrap">
                                            @if($ev->visibilidade === 'turma')
                                                {{ $ev->turma?->nome ?? 'Turma' }}
                                            @elseif($ev->visibilidade === 'curso')
                                                {{ $ev->curso?->nome ?? 'Curso' }}
                                            @else
                                                Escola
                                            @endif
                                        </td>
                                        <td class="text-nowrap text-end">
                                            @if($podeGerir)
                                                <form method="POST" action="{{ route('agenda.destroy', $ev) }}" class="d-inline"
                                                      data-confirm-title="Remover evento"
                                                      data-confirm="Remover este evento da agenda?"
                                                      data-confirm-ok="Sim, remover"
                                                      data-confirm-danger="1">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remover">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </x-data-table>
            @endif
        </div>
    </div>
</x-app-layout>
