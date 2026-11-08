@php
    $dias = \App\Models\TurmaHorario::diasSemana();
@endphp

@extends('layouts.portal-aluno')

@section('title', 'Horário da Turma')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3 py-md-4 portal-aluno-page">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h2 class="h4 mb-1">Horário da Turma</h2>
            <p class="text-muted mb-0 small">{{ $aluno->turma?->nome }} · {{ $aluno->turma?->curso?->nome }}</p>
        </div>
        <div class="d-flex gap-2">
            @if($horarios->isNotEmpty())
                <a href="{{ route('portal.aluno.horario.pdf') }}" class="btn btn-portal-pdf btn-sm">
                    <i class="bi bi-file-pdf me-1"></i> Baixar PDF
                </a>
            @endif
            <a href="{{ route('portal.aluno.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    @if($horarios->isEmpty())
        <div class="alert alert-info border-0 shadow-sm">
            O horário da sua turma ainda não foi publicado pela secretaria.
        </div>
    @else
        @if($horarioConfig->intervalo_activo)
            <div class="small text-muted mb-2">
                <i class="bi bi-pause-circle me-1"></i>{{ $horarioConfig->textoIntervalo() }}
            </div>
        @endif
        <div class="card border-0 shadow-sm portal-card">
            <div class="table-responsive d-none d-lg-block">
                <table class="table table-bordered mb-0 align-middle text-center small portal-schedule-table">
                    <thead>
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
                                <tr class="portal-interval-row">
                                    <td colspan="{{ 2 + min(6, $horarios->max('dia_semana') ?? 5) }}" class="fw-semibold">
                                        {{ $linha['texto'] }}
                                    </td>
                                </tr>
                            @else
                                <tr>
                                    <td class="fw-semibold">{{ $linha['numero'] }}º</td>
                                    <td class="fw-semibold">{{ $linha['hora'] }}</td>
                                    @foreach($dias as $num => $nome)
                                        @if($num <= 5 || $horarios->contains(fn ($h) => $h->dia_semana === 6))
                                            @php $aula = $linha['celulas'][$num] ?? null; @endphp
                                            <td class="{{ $aula ? '' : 'text-muted' }}">
                                                @if($aula)
                                                    <div class="fw-semibold text-primary">{{ $aula->disciplina?->nome }}</div>
                                                    <div class="text-muted" style="font-size:.75rem">{{ $aula->professor?->name }}</div>
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

        <div class="d-lg-none portal-schedule-mobile mt-3">
            <h3 class="h6 mb-2">Vista rápida (mobile)</h3>
            <div class="list-group list-group-flush">
                @foreach($horarios as $aula)
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-semibold text-primary">{{ $aula->disciplina?->nome }}</div>
                                <div class="small text-muted">{{ $aula->professor?->name }}</div>
                            </div>
                            <div class="text-end small">
                                <div class="fw-medium">{{ \App\Models\TurmaHorario::diasSemana()[$aula->dia_semana] ?? '' }}</div>
                                <div class="text-muted">{{ substr((string) $aula->hora_inicio, 0, 5) }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
