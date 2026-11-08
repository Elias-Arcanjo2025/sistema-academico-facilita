@extends('layouts.portal-aluno')

@section('title', 'As Minhas Notas')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3 py-md-4">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
        <div>
            <h2 class="h4 mb-1">As Minhas Notas</h2>
            <p class="text-muted mb-0 small">{{ $aluno->nome }} · {{ $aluno->turma?->codigo }}</p>
        </div>
        <a href="{{ route('portal.aluno.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('portal.aluno.notas') }}" class="row g-2 g-md-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="ano_lectivo_id" class="form-label small mb-1">Ano lectivo</label>
                    <select name="ano_lectivo_id" id="ano_lectivo_id" class="form-select form-select-sm">
                        @foreach($anosLectivos as $ano)
                            <option value="{{ $ano->id }}" @selected((int)$filtros['ano_lectivo_id'] === $ano->id)>
                                {{ $ano->nome }}{{ $ano->isActual() ? ' (actual)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label for="periodo" class="form-label small mb-1">Período</label>
                    <select name="periodo" id="periodo" class="form-select form-select-sm">
                        <option value="todos" @selected($filtros['periodo'] === 'todos')>Todos</option>
                        <option value="1" @selected($filtros['periodo'] === '1')>1º trimestre</option>
                        <option value="2" @selected($filtros['periodo'] === '2')>2º trimestre</option>
                        <option value="3" @selected($filtros['periodo'] === '3')>3º trimestre</option>
                        <option value="anual" @selected($filtros['periodo'] === 'anual')>Anual / finais</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label for="disciplina_id" class="form-label small mb-1">Disciplina</label>
                    <select name="disciplina_id" id="disciplina_id" class="form-select form-select-sm">
                        <option value="todas" @selected($filtros['disciplina_id'] === 'todas')>Todas</option>
                        @foreach($disciplinas as $disciplina)
                            <option value="{{ $disciplina->id }}" @selected((string)$filtros['disciplina_id'] === (string)$disciplina->id)>
                                {{ $disciplina->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="bi bi-funnel me-1"></i> Filtrar
                    </button>
                    <a href="{{ route('portal.aluno.notas') }}" class="btn btn-outline-secondary btn-sm" title="Limpar">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
            <span class="fw-semibold small text-muted">
                {{ count($tabelaNotas['linhas']) }} disciplina(s)
                @if($tabelaNotas['mostrar_trimestre'])
                    · agrupado por trimestre
                @elseif($filtros['periodo'] !== 'todos' && $filtros['periodo'] !== 'anual')
                    · {{ $filtros['periodo'] }}º trimestre
                @endif
            </span>
        </div>
        <div class="card-body p-0">
            @if(empty($tabelaNotas['linhas']))
                <div class="alert alert-info m-3 mb-0">Nenhuma nota encontrada com estes filtros.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle text-center portal-notas-tabela">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start ps-3">Disciplina</th>
                                @if($tabelaNotas['mostrar_trimestre'])
                                    <th>Trim.</th>
                                @endif
                                @foreach($tabelaNotas['colunas'] as $coluna)
                                    <th>{{ $coluna }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tabelaNotas['linhas'] as $linha)
                                <tr>
                                    <td class="text-start ps-3 fw-medium">{{ $linha['disciplina'] }}</td>
                                    @if($tabelaNotas['mostrar_trimestre'])
                                        <td class="text-muted">{{ $linha['trimestre'] ? $linha['trimestre'].'º' : '—' }}</td>
                                    @endif
                                    @foreach($tabelaNotas['colunas'] as $coluna)
                                        @php
                                            $valor = $linha['celulas'][$coluna] ?? null;
                                            $ehFinal = $coluna === 'Final';
                                            $classeValor = $valor !== null && $valor < $notaMinima
                                                ? 'text-danger fw-semibold'
                                                : ($ehFinal && $valor !== null ? 'text-success fw-semibold' : '');
                                        @endphp
                                        <td class="{{ $classeValor }}">
                                            {{ $valor !== null ? number_format($valor, 1) : '—' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .portal-notas-tabela th,
    .portal-notas-tabela td {
        white-space: nowrap;
    }
    @media (max-width: 576px) {
        .portal-notas-tabela {
            font-size: 0.8rem;
        }
        .portal-notas-tabela th,
        .portal-notas-tabela td {
            padding: 0.45rem 0.35rem;
        }
        .portal-notas-tabela th:first-child,
        .portal-notas-tabela td:first-child {
            position: sticky;
            left: 0;
            background: var(--bg-card, #fff);
            z-index: 1;
            min-width: 7rem;
        }
    }
</style>
@endpush
