@php
    $colsT3 = ($usaPtT3 ?? true) ? 4 : 3;
    $colsPorDisc = ($colsPorDisciplina ?? (4 + 4 + $colsT3 + 5));

    $renderNota = function ($val) {
        if ($val === null || $val === '') {
            return '<span class="text-muted">—</span>';
        }
        $n = (int) round((float) $val);
        $cls = $n >= 10 ? 'text-primary' : 'text-danger';

        return '<span class="'.$cls.'">'.$n.'</span>';
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="h4 mb-0">Pauta Final Completa</h2>
                <small class="text-muted">{{ $turma->nome }} — {{ $turma->curso->nome }} | {{ $anoLectivo }}</small>
            </div>
            <div class="btn-group">
                <a href="{{ route('pautas.index', ['turma_id' => $turma->id, 'trimestre' => 3, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
                <a href="{{ route('export.pauta-final-completa.excel', ['turma_id' => $turma->id, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-success">
                    <i class="bi bi-file-excel"></i> Excel
                </a>
                <a href="{{ route('export.pauta-final-completa.pdf', ['turma_id' => $turma->id, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-danger" target="_blank">
                    <i class="bi bi-file-pdf"></i> PDF
                </a>
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container-fluid px-3">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="alert alert-light border d-print-none mb-3">
                <i class="bi bi-arrows-expand"></i>
                Deslize horizontalmente para ver todas as disciplinas.
                T1/T2: MAC · PP · PT · MT — T3: MAC · PP @if($usaPtT3 ?? true) · PT @endif · MT — Finais: MFT2 · CF · PG · CA · CFD.
            </div>

            <div class="card">
                <div class="card-header d-print-none py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="bi bi-grid-3x3-gap"></i>
                            {{ count($alunos) }} alunos · {{ count($disciplinas) }} disciplinas
                        </h6>
                        <span class="badge bg-secondary">{{ $colsPorDisc }} colunas / disciplina</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="d-none d-print-block p-3 text-center border-bottom">
                        <h5 class="mb-1">PAUTA FINAL COMPLETA</h5>
                        <p class="mb-0 small">
                            {{ $turma->nome }} | {{ $turma->classe->nome ?? '' }} | {{ $turma->curso->nome }} | {{ $anoLectivo }}
                        </p>
                    </div>

                    @if(count($alunos) > 0)
                        <div class="table-responsive pauta-scroll">
                            <table class="table table-bordered table-sm mb-0 pauta-completa-table">
                                <thead class="table-light">
                                    <tr>
                                        <th class="sticky-col sticky-col-1 text-center" rowspan="3">N.º</th>
                                        <th class="sticky-col sticky-col-2 text-center" rowspan="3">Proc.</th>
                                        <th class="sticky-col sticky-col-3" rowspan="3">Nome</th>
                                        <th class="sticky-col sticky-col-4 text-center" rowspan="3">Sx</th>
                                        @foreach($disciplinas as $disc)
                                            <th class="text-center bg-body-secondary" colspan="{{ $colsPorDisc }}" style="font-size:0.72rem;">
                                                {{ $disc->codigo ?: $disc->nome }}
                                            </th>
                                        @endforeach
                                        <th class="text-center" rowspan="3">Obs.</th>
                                        <th class="text-center" rowspan="3">Result.</th>
                                    </tr>
                                    <tr>
                                        @foreach($disciplinas as $disc)
                                            <th class="text-center trim-t1" colspan="4">T1</th>
                                            <th class="text-center trim-t2" colspan="4">T2</th>
                                            <th class="text-center trim-t3" colspan="{{ $colsT3 }}">T3</th>
                                            <th class="text-center trim-fin" colspan="5">Finais</th>
                                        @endforeach
                                    </tr>
                                    <tr>
                                        @foreach($disciplinas as $disc)
                                            @foreach([1, 2] as $t)
                                                <th class="sub-h">M</th>
                                                <th class="sub-h">P</th>
                                                <th class="sub-h">T</th>
                                                <th class="sub-h bg-warning bg-opacity-25"><b>MT</b></th>
                                            @endforeach
                                            <th class="sub-h">M</th>
                                            <th class="sub-h">P</th>
                                            @if($usaPtT3 ?? true)
                                                <th class="sub-h">T</th>
                                            @endif
                                            <th class="sub-h bg-warning bg-opacity-25"><b>MT</b></th>
                                            @foreach(['MFT2','CF','PG','CA','CFD'] as $fh)
                                                <th class="sub-h trim-fin {{ $fh === 'CFD' ? 'bg-success bg-opacity-25' : 'bg-info bg-opacity-10' }}">
                                                    <b>{{ $fh }}</b>
                                                </th>
                                            @endforeach
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($alunos as $alunoData)
                                        <tr class="{{ !empty($alunoData['desistente']) ? 'table-secondary' : '' }}">
                                            <td class="sticky-col sticky-col-1 text-center fw-bold">{{ $alunoData['numero_ordem'] }}</td>
                                            <td class="sticky-col sticky-col-2 text-center">{{ $alunoData['numero_processo'] ?? '—' }}</td>
                                            <td class="sticky-col sticky-col-3 nome-aluno">{{ $alunoData['nome'] }}</td>
                                            <td class="sticky-col sticky-col-4 text-center">{{ strtoupper(substr($alunoData['genero'] ?? '—', 0, 1)) }}</td>

                                            @foreach($disciplinas as $disc)
                                                @php $d = $alunoData['disciplinas'][$disc->id] ?? null; @endphp
                                                @for($t = 1; $t <= 2; $t++)
                                                    @php $tr = $d['trimestres'][$t] ?? []; @endphp
                                                    <td class="text-center">{!! $renderNota($tr['mac'] ?? null) !!}</td>
                                                    <td class="text-center">{!! $renderNota($tr['pp'] ?? null) !!}</td>
                                                    <td class="text-center">{!! $renderNota($tr['pt'] ?? null) !!}</td>
                                                    <td class="text-center bg-warning bg-opacity-10">{!! $renderNota($tr['mt'] ?? null) !!}</td>
                                                @endfor
                                                @php $t3 = $d['trimestres'][3] ?? []; $fin = $d['finais'] ?? []; @endphp
                                                <td class="text-center">{!! $renderNota($t3['mac'] ?? null) !!}</td>
                                                <td class="text-center">{!! $renderNota($t3['pp'] ?? null) !!}</td>
                                                @if($usaPtT3 ?? true)
                                                    <td class="text-center">{!! $renderNota($t3['pt'] ?? null) !!}</td>
                                                @endif
                                                <td class="text-center bg-warning bg-opacity-10">{!! $renderNota($t3['mt'] ?? null) !!}</td>
                                                <td class="text-center bg-info bg-opacity-10">{!! $renderNota($fin['mft2'] ?? null) !!}</td>
                                                <td class="text-center bg-info bg-opacity-10">{!! $renderNota($fin['cf'] ?? null) !!}</td>
                                                <td class="text-center">{!! $renderNota($fin['pg'] ?? null) !!}</td>
                                                <td class="text-center bg-primary bg-opacity-10 fw-bold">{!! $renderNota($fin['ca'] ?? null) !!}</td>
                                                <td class="text-center bg-success bg-opacity-25 fw-bold">{!! $renderNota($fin['cfd'] ?? null) !!}</td>
                                            @endforeach

                                            <td class="small">{{ $alunoData['observacoes'] ?: '—' }}</td>
                                            <td class="text-center">
                                                @php $res = $alunoData['resultado'] ?? ''; @endphp
                                                @if($res === 'DESISTENTE')
                                                    <span class="badge bg-secondary">DESISTENTE</span>
                                                @elseif($res === 'Aprovado')
                                                    <span class="badge bg-primary">Aprovado</span>
                                                @elseif($res === 'Reprovado')
                                                    <span class="badge bg-danger">Reprovado</span>
                                                @elseif($res === 'Pendente')
                                                    <span class="badge bg-warning text-dark">Pendente</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox display-4"></i>
                            <p class="mt-2 mb-0">Nenhum aluno nesta turma.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('styles')
    <style>
        .pauta-scroll { max-height: calc(100vh - 220px); overflow: auto; }
        .pauta-completa-table { font-size: 0.72rem; white-space: nowrap; }
        .pauta-completa-table th, .pauta-completa-table td { padding: 0.2rem 0.35rem !important; vertical-align: middle; }
        .pauta-completa-table .sub-h { font-size: 0.62rem; min-width: 28px; }
        .pauta-completa-table .nome-aluno { min-width: 140px; max-width: 180px; white-space: normal; font-size: 0.7rem; }
        .pauta-completa-table .trim-t1 { background: #e8f4fc !important; font-size: 0.65rem; }
        .pauta-completa-table .trim-t2 { background: #fef3e2 !important; font-size: 0.65rem; }
        .pauta-completa-table .trim-t3 { background: #e8f8ef !important; font-size: 0.65rem; }
        .pauta-completa-table .trim-fin { background: #f0f0f5 !important; font-size: 0.65rem; }
        .sticky-col { position: sticky; z-index: 2; background: #fff; }
        .sticky-col-1 { left: 0; min-width: 36px; }
        .sticky-col-2 { left: 36px; min-width: 44px; }
        .sticky-col-3 { left: 80px; }
        .sticky-col-4 { left: 260px; min-width: 32px; }
        thead .sticky-col { z-index: 4; background: #f8f9fa !important; }
        @media print {
            .navbar, .sidebar, .btn, .alert, .d-print-none { display: none !important; }
            .pauta-scroll { max-height: none; overflow: visible; }
            .pauta-completa-table { font-size: 5px !important; }
            .sticky-col { position: static; }
            @page { size: landscape; margin: 8mm; }
        }
    </style>
    @endpush
</x-app-layout>
