@php
    $dias = \App\Models\TurmaHorario::diasSemana();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">
                <i class="bi bi-calendar3 me-2 text-primary"></i>Horário — {{ $turma->nome }}
            </h2>
            <div class="d-flex gap-2">
                <a href="{{ route('horarios.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
                @if(!$horarios->isEmpty())
                    <a href="{{ route('horarios.pdf', $turma) }}" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-file-pdf"></i> Baixar PDF
                    </a>
                @endif
                @if(\App\Services\HorarioEscolarAccess::podeAprovar(auth()->user()))
                    @if($horarioConfig->estaAprovado())
                        <form method="POST" action="{{ route('horarios.reabrir', $turma) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-warning btn-sm">Reabrir</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('horarios.aprovar', $turma) }}">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="bi bi-check2-circle"></i> Aprovar horário
                            </button>
                        </form>
                    @endif
                @endif
                @can('horarios.gerir')
                    @if(\App\Services\HorarioEscolarAccess::podeGerir(auth()->user()))
                        <a href="{{ route('horarios.edit', $turma) }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-pencil"></i> Gerir horário
                        </a>
                    @endif
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Horários', 'url' => route('horarios.index')],
                ['label' => $turma->nome],
            ]" />

            <div class="card shadow-soft mb-3">
                <div class="card-body py-3">
                    <div class="row g-2 small">
                        <div class="col-md-3"><strong>Ano lectivo:</strong> {{ \App\Models\Configuracao::getAnoLectivoActual() }}</div>
                        <div class="col-md-3"><strong>Curso:</strong> {{ $turma->curso?->nome }}</div>
                        <div class="col-md-2"><strong>Classe:</strong> {{ $turma->classe?->nome ?? '-' }}</div>
                        <div class="col-md-2"><strong>Sala:</strong> {{ $turma->sala?->nome ?? '-' }}</div>
                        <div class="col-md-2"><strong>Turno:</strong> {{ $turma->turno?->nome ?? '-' }}</div>
                    </div>
                    <div class="mt-2">
                        @if($horarioConfig->estaAprovado())
                            <span class="badge bg-success">Horário aprovado</span>
                            @if($horarioConfig->aprovado_em)
                                <span class="small text-muted ms-1">em {{ $horarioConfig->aprovado_em->format('d/m/Y H:i') }}</span>
                            @endif
                        @else
                            <span class="badge bg-secondary">Rascunho — aguarda aprovação pedagógica</span>
                        @endif
                    </div>
                    @if($horarioConfig->intervalo_activo)
                        <div class="mt-2 small text-muted">
                            <i class="bi bi-pause-circle me-1"></i>
                            Intervalo após o {{ $horarioConfig->intervalo_apos_periodo }}º tempo:
                            <strong>{{ $horarioConfig->textoIntervalo() }}</strong>
                        </div>
                    @endif
                </div>
            </div>

            @if($horarios->isEmpty())
                <x-empty-state icon="bi-calendar-x" message="Ainda não há horário definido para esta turma." />
            @else
                <div class="card shadow-soft">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 align-middle text-center small">
                            <thead class="table-primary">
                                <tr>
                                    <th style="width:50px">Nº</th>
                                    <th style="width:100px">Hora</th>
                                    @foreach($dias as $num => $nome)
                                        @if($num <= 5 || $horarios->contains(fn ($h) => $h->dia_semana === 6))
                                            <th>{{ \Illuminate\Support\Str::before($nome, '-') }}</th>
                                        @endif
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($linhas as $linha)
                                    @if(($linha['tipo'] ?? '') === 'intervalo')
                                        <tr class="table-secondary">
                                            <td colspan="{{ 2 + min(6, $horarios->max('dia_semana') ?? 5) }}" class="fw-semibold">
                                                {{ $linha['texto'] }}
                                            </td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td class="fw-semibold bg-light">{{ $linha['numero'] }}º</td>
                                            <td class="fw-semibold bg-light">{{ $linha['hora'] }}</td>
                                            @foreach($dias as $num => $nome)
                                                @if($num <= 5 || $horarios->contains(fn ($h) => $h->dia_semana === 6))
                                                    @php $aula = $linha['celulas'][$num] ?? null; @endphp
                                                    <td class="{{ $aula ? 'bg-white' : 'bg-light text-muted' }}">
                                                        @if($aula)
                                                            <div class="fw-semibold text-primary">{{ $aula->disciplina?->nome }}</div>
                                                            <div class="text-muted" style="font-size:.75rem">{{ $aula->professor?->name }}</div>
                                                            @if($aula->sala)
                                                                <div class="text-muted" style="font-size:.7rem">{{ $aula->sala->nome }}</div>
                                                            @endif
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                @endif
                                            @endforeach
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
