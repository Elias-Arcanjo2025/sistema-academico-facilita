@extends('layouts.portal-aluno')

@section('title', 'Calendário')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3 py-md-4 portal-aluno-page">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h2 class="h4 mb-1">Calendário</h2>
            <p class="text-muted mb-0 small">{{ $aluno->turma?->nome }} · {{ $aluno->turma?->curso?->nome }}</p>
        </div>
        <a href="{{ route('portal.aluno.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'agenda' ? 'active' : '' }}" href="{{ route('portal.aluno.calendario', ['tab' => 'agenda']) }}">Agenda escolar</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'provas' ? 'active' : '' }}" href="{{ route('portal.aluno.calendario', ['tab' => 'provas']) }}">Provas</a>
        </li>
    </ul>

    @if($tab === 'agenda')
        @if($eventos->isEmpty())
            <div class="alert alert-info border-0 shadow-sm">Ainda não há eventos na agenda para a sua turma.</div>
        @else
            <div class="card border-0 shadow-sm portal-card">
                <div class="list-group list-group-flush">
                    @foreach($eventos as $ev)
                        <div class="list-group-item">
                            <div class="fw-semibold">{{ $ev->titulo }}</div>
                            <div class="small text-muted">
                                {{ $ev->inicia_em->format('d/m/Y H:i') }}
                                · {{ \App\Models\AgendaEvento::tipos()[$ev->tipo] ?? $ev->tipo }}
                                @if($ev->visibilidade === 'escola') · Escola
                                @elseif($ev->visibilidade === 'curso') · Curso
                                @else · Turma
                                @endif
                            </div>
                            @if($ev->descricao)
                                <div class="small mt-1">{{ $ev->descricao }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @else
        @if($provas->isEmpty())
            <div class="alert alert-info border-0 shadow-sm">Ainda não há provas agendadas.</div>
        @else
            <div class="mb-2 text-end">
                <a href="{{ route('portal.aluno.provas.pdf') }}" class="btn btn-portal-pdf btn-sm">
                    <i class="bi bi-file-pdf me-1"></i> Baixar PDF
                </a>
            </div>
            <div class="card border-0 shadow-sm portal-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle portal-schedule-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Horário</th>
                                <th>Disciplina</th>
                                <th>Tipo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($provas as $prova)
                                <tr>
                                    <td class="fw-semibold">{{ $prova->dataFormatada() }}</td>
                                    <td>{{ $prova->horarioFormatado() }}</td>
                                    <td>{{ $prova->disciplina?->nome }}</td>
                                    <td>{{ \App\Models\CalendarioProva::tipos()[$prova->tipo] ?? $prova->tipo }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
