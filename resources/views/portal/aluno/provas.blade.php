@extends('layouts.portal-aluno')

@section('title', 'Calendário de Provas')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3 py-md-4 portal-aluno-page">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h2 class="h4 mb-1">Calendário de Provas</h2>
            <p class="text-muted mb-0 small">{{ $aluno->turma?->nome }} · {{ $aluno->turma?->curso?->nome }}</p>
        </div>
        <div class="d-flex gap-2">
            @if($provas->isNotEmpty())
                <a href="{{ route('portal.aluno.provas.pdf') }}" class="btn btn-portal-pdf btn-sm">
                    <i class="bi bi-file-pdf me-1"></i> Baixar PDF
                </a>
            @endif
            <a href="{{ route('portal.aluno.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    @if($provas->isEmpty())
        <div class="alert alert-info border-0 shadow-sm">
            Ainda não há provas agendadas para a sua turma no período actual.
        </div>
    @else
        <div class="card border-0 shadow-sm portal-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle portal-schedule-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Dia da semana</th>
                            <th>Horário</th>
                            <th>Disciplina</th>
                            <th>Tipo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($provas as $prova)
                            <tr>
                                <td class="fw-semibold">{{ $prova->dataFormatada() }}</td>
                                <td>{{ $prova->diaSemanaNome() }}</td>
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
</div>
@endsection
