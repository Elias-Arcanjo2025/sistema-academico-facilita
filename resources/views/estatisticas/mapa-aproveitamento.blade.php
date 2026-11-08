@extends('layouts.app')

@section('title', 'Mapa de Aproveitamento e Estatística')

@section('content')
<div class="page-content">
    <!-- Page Header -->
    <div class="page-header mb-4 fade-in-scale">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2><i class="bi bi-table me-2"></i>Mapa de Aproveitamento e Estatística</h2>
                <p class="text-muted mb-0">Ano Lectivo: {{ $anoLectivo }}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('estatisticas.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Voltar
                </a>
                <a href="{{ route('estatisticas.mapa-aproveitamento.pdf', array_filter(['trimestre' => $trimestre, 'curso_id' => $cursoId])) }}"
                   class="btn btn-primary" target="_blank">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Exportar PDF
                </a>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="content-card fade-in-scale mb-4">
        <div class="content-card-body">
            <form method="GET" action="{{ route('estatisticas.mapa-aproveitamento') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Trimestre</label>
                    <select name="trimestre" class="form-select">
                        @foreach($trimestresOpts as $val => $label)
                            <option value="{{ $val }}" {{ $trimestre == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Curso</label>
                    <select name="curso_id" class="form-select">
                        <option value="">— Todos os cursos —</option>
                        @foreach($cursos as $curso)
                            <option value="{{ $curso->id }}" {{ $cursoId == $curso->id ? 'selected' : '' }}>
                                {{ $curso->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if($mapa['por_curso']->isEmpty())
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Não foram encontrados dados para os filtros seleccionados.
        </div>
    @else
        @foreach($mapa['por_curso'] as $cursoNome => $cursoDados)
            <div class="content-card fade-in-scale mb-4">
                <div class="content-card-header">
                    <h5 class="content-card-title">
                        <i class="bi bi-mortarboard me-2"></i>
                        MAPA DE APROVEITAMENTO &mdash; {{ $trimestresOpts[$mapa['trimestre'] ?? $trimestre] ?? '' }}
                        &mdash; Ano Lectivo {{ $mapa['ano_lectivo'] }}
                    </h5>
                    <span class="badge bg-primary fs-6">CURSO: {{ strtoupper($cursoNome) }}</span>
                </div>
                <div class="content-card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0" style="font-size: 0.85rem;">
                            <thead>
                                <tr class="table-dark text-center">
                                    <th rowspan="2" class="align-middle">NÍVEL</th>
                                    <th rowspan="2" class="align-middle">CLASSE</th>
                                    <th colspan="2" class="text-center">MATRICULADOS</th>
                                    <th colspan="2" class="text-center">DESISTENTES</th>
                                    <th colspan="2" class="text-center">AVALIADOS</th>
                                    <th colspan="3" class="text-center">C/APROVEITAMENTO</th>
                                    <th colspan="3" class="text-center">S/APROVEITAMENTO</th>
                                    <th colspan="2" class="text-center">Nº PROFESSORES</th>
                                </tr>
                                <tr class="table-secondary text-center" style="font-size: 0.78rem;">
                                    <th>MF</th><th>F</th>
                                    <th>MF</th><th>F</th>
                                    <th>MF</th><th>F</th>
                                    <th>MF</th><th>F</th><th>%</th>
                                    <th>MF</th><th>F</th><th>%</th>
                                    <th>MF</th><th>F</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cursoDados['turmas'] as $turma)
                                    {{-- Linha da turma --}}
                                    <tr class="text-center">
                                        <td class="text-start fw-semibold">{{ $turma['nivel'] }}</td>
                                        <td class="text-start">{{ $turma['turma_nome'] }}</td>
                                        <td>{{ $turma['matriculados_mf'] }}</td>
                                        <td>{{ $turma['matriculados_f'] }}</td>
                                        <td>{{ $turma['desistentes_mf'] }}</td>
                                        <td>{{ $turma['desistentes_f'] }}</td>
                                        <td>{{ $turma['avaliados_mf'] }}</td>
                                        <td>{{ $turma['avaliados_f'] }}</td>
                                        <td>{{ $turma['com_mf'] }}</td>
                                        <td>{{ $turma['com_f'] }}</td>
                                        <td>{{ number_format($turma['com_pct'], 1, ',') }}</td>
                                        <td>{{ $turma['sem_mf'] }}</td>
                                        <td>{{ $turma['sem_f'] }}</td>
                                        <td>{{ number_format($turma['sem_pct'], 1, ',') }}</td>
                                        <td>—</td>
                                        <td>—</td>
                                    </tr>
                                    {{-- Linha SUB-TOTAL --}}
                                    <tr class="text-center table-light fw-semibold" style="font-size: 0.82rem;">
                                        <td colspan="2" class="text-start text-muted">SUB-TOTAL</td>
                                        <td>{{ $turma['matriculados_mf'] }}</td>
                                        <td>{{ $turma['matriculados_f'] }}</td>
                                        <td>{{ $turma['desistentes_mf'] }}</td>
                                        <td>{{ $turma['desistentes_f'] }}</td>
                                        <td>{{ $turma['avaliados_mf'] }}</td>
                                        <td>{{ $turma['avaliados_f'] }}</td>
                                        <td>{{ $turma['com_mf'] }}</td>
                                        <td>{{ $turma['com_f'] }}</td>
                                        <td>{{ number_format($turma['com_pct'], 1, ',') }}</td>
                                        <td>{{ $turma['sem_mf'] }}</td>
                                        <td>{{ $turma['sem_f'] }}</td>
                                        <td>{{ number_format($turma['sem_pct'], 1, ',') }}</td>
                                        <td>{{ $turma['professores_mf'] }}</td>
                                        <td>{{ $turma['professores_f'] }}</td>
                                    </tr>
                                @endforeach

                                {{-- TOTAL GERAL do curso --}}
                                <tr class="text-center table-dark fw-bold">
                                    <td colspan="2" class="text-start">TOTAL GERAL</td>
                                    <td>{{ $cursoDados['total']['matriculados_mf'] }}</td>
                                    <td>{{ $cursoDados['total']['matriculados_f'] }}</td>
                                    <td>{{ $cursoDados['total']['desistentes_mf'] }}</td>
                                    <td>{{ $cursoDados['total']['desistentes_f'] }}</td>
                                    <td>{{ $cursoDados['total']['avaliados_mf'] }}</td>
                                    <td>{{ $cursoDados['total']['avaliados_f'] }}</td>
                                    <td>{{ $cursoDados['total']['com_mf'] }}</td>
                                    <td>{{ $cursoDados['total']['com_f'] }}</td>
                                    <td>{{ number_format($cursoDados['total']['com_pct'], 1, ',') }}</td>
                                    <td>{{ $cursoDados['total']['sem_mf'] }}</td>
                                    <td>{{ $cursoDados['total']['sem_f'] }}</td>
                                    <td>{{ number_format($cursoDados['total']['sem_pct'], 1, ',') }}</td>
                                    <td>{{ $cursoDados['total']['professores_mf'] }}</td>
                                    <td>{{ $cursoDados['total']['professores_f'] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- TOTAL GERAL de todos os cursos (só aparece quando há mais de 1 curso) --}}
        @if($mapa['por_curso']->count() > 1)
            <div class="content-card fade-in-scale mb-4">
                <div class="content-card-header">
                    <h5 class="content-card-title"><i class="bi bi-sigma me-2"></i>TOTAL GERAL — Todos os Cursos</h5>
                </div>
                <div class="content-card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0" style="font-size: 0.85rem;">
                            <thead>
                                <tr class="table-dark text-center">
                                    <th>MATRICULADOS MF</th>
                                    <th>MATRICULADOS F</th>
                                    <th>DESISTENTES MF</th>
                                    <th>DESISTENTES F</th>
                                    <th>AVALIADOS MF</th>
                                    <th>AVALIADOS F</th>
                                    <th>C/APR MF</th>
                                    <th>C/APR F</th>
                                    <th>C/APR %</th>
                                    <th>S/APR MF</th>
                                    <th>S/APR F</th>
                                    <th>S/APR %</th>
                                    <th>PROF MF</th>
                                    <th>PROF F</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="text-center fw-bold table-warning">
                                    <td>{{ $mapa['total_geral']['matriculados_mf'] }}</td>
                                    <td>{{ $mapa['total_geral']['matriculados_f'] }}</td>
                                    <td>{{ $mapa['total_geral']['desistentes_mf'] }}</td>
                                    <td>{{ $mapa['total_geral']['desistentes_f'] }}</td>
                                    <td>{{ $mapa['total_geral']['avaliados_mf'] }}</td>
                                    <td>{{ $mapa['total_geral']['avaliados_f'] }}</td>
                                    <td>{{ $mapa['total_geral']['com_mf'] }}</td>
                                    <td>{{ $mapa['total_geral']['com_f'] }}</td>
                                    <td>{{ number_format($mapa['total_geral']['com_pct'], 1, ',') }}%</td>
                                    <td>{{ $mapa['total_geral']['sem_mf'] }}</td>
                                    <td>{{ $mapa['total_geral']['sem_f'] }}</td>
                                    <td>{{ number_format($mapa['total_geral']['sem_pct'], 1, ',') }}%</td>
                                    <td>{{ $mapa['total_geral']['professores_mf'] }}</td>
                                    <td>{{ $mapa['total_geral']['professores_f'] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="alert alert-light border mt-2" style="font-size: 0.82rem;">
            <strong>Legenda:</strong> MF = Masculino + Feminino (total) &nbsp;|&nbsp; F = Feminino &nbsp;|&nbsp;
            C/APROVEITAMENTO = alunos com até {{ (int) \App\Models\Configuracao::get('max_disciplinas_reprovacao', 2) }} disciplinas abaixo de {{ (int) \App\Models\Configuracao::get('nota_minima_aprovacao', 10) }} &nbsp;|&nbsp;
            @if(($trimestre ?? 1) === \App\Services\EstatisticasService::PERIODO_ANUAL)
                <strong>Anual</strong> = resultado final do ano lectivo (CA por disciplina, mesma base do III trimestre).
            @else
                Período: <strong>{{ $trimestresOpts[$trimestre] ?? '' }}</strong> — métrica {{ $mapa['metrica'] ?? '' }}.
            @endif
        </div>
    @endif
</div>
@endsection
