<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="h4 mb-0">Mini-Pauta Anual</h2>
                <small class="text-muted">{{ $turma->nome }} — {{ $disciplina->nome }} | 1.º + 2.º + 3.º · {{ $anoLectivo }}</small>
            </div>
            <div class="btn-group">
                <a href="{{ route('export.mini-pauta-anual.excel', ['turma_id' => $turma->id, 'disciplina_id' => $disciplina->id, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-success btn-sm">
                    <i class="bi bi-file-excel"></i> Excel
                </a>
                <a href="{{ route('export.mini-pauta-anual.pdf', ['turma_id' => $turma->id, 'disciplina_id' => $disciplina->id, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-danger btn-sm" target="_blank">
                    <i class="bi bi-file-pdf"></i> PDF
                </a>
                <a href="{{ route('mini-pautas.index', ['turma_id' => $turma->id, 'disciplina_id' => $disciplina->id, 'trimestre' => 'anual', 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-calendar3" variant="info" title="Documento anual" id="tip-minipauta-anual" compact>
                O PDF anual sai em paisagem, com os três trimestres lado a lado numa única folha (como a mini-pauta em papel). O Excel continua com uma folha por trimestre.
            </x-tip-card>

            @foreach($miniPautasAnuais as $t => $bloco)
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ $t }}.º Trimestre</h5>
                        <div class="btn-group">
                            <a href="{{ route('export.mini-pauta.excel', ['turma_id' => $turma->id, 'disciplina_id' => $disciplina->id, 'trimestre' => $t, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-sm btn-outline-success">Excel</a>
                            <a href="{{ route('export.mini-pauta.pdf', ['turma_id' => $turma->id, 'disciplina_id' => $disciplina->id, 'trimestre' => $t, 'ano_lectivo' => $anoLectivo]) }}" class="btn btn-sm btn-outline-danger" target="_blank">PDF</a>
                        </div>
                    </div>
                    <div class="card-body">
                        @include('mini-pautas.partials.tabela', [
                            'colunas' => $bloco['colunas'],
                            'miniPauta' => $bloco['miniPauta'],
                            'chaveResultado' => $bloco['chaveResultado'],
                        ])
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
