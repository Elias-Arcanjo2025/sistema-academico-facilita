@php
    $trimestreActual = \App\Models\Configuracao::getTrimestreActual();
    $ignoraTrimestreFechado = auth()->user()->podeIgnorarTrimestreFechado();
    $trimestreAberto = $ignoraTrimestreFechado || \App\Models\Configuracao::trimestreAbertoParaLancamento((int) $trimestre);
    $podeImportar = \App\Support\NotaLancamentoRules::utilizadorPodeLancarNaTurmaDisciplina(auth()->user(), (int) $turmaId, (int) $disciplinaId);
    $colunasLancaveis = (int) $trimestre === 3
        ? ((($turma ?? null)?->curso?->regime()?->id() ?? '') === \App\Models\Curso::REGIME_ADMINISTRACAO ? 'PG' : 'MAC3, PP3 e PG')
        : 'MAC, PP e PT';
@endphp

@if($podeImportar && $trimestreAberto)
    <div class="card border-success mb-4 d-print-none">
        <div class="card-header bg-success bg-opacity-10">
            <h6 class="mb-0">
                <i class="bi bi-file-earmark-excel me-1 text-success"></i>
                Importar notas via Excel
            </h6>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Descarregue a mini-pauta em Excel, preencha <strong>{{ $colunasLancaveis }}</strong> e reimporte o ficheiro.
                As médias (MT, MFT2, CF, CA) são recalculadas automaticamente. Não altere a estrutura do modelo nem a ordem dos alunos.
            </p>

            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show py-2">
                    <small>{{ session('warning') }}</small>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('erros_importacao'))
                <div class="alert alert-danger py-2">
                    <strong class="small">Erros na importação:</strong>
                    <ul class="mb-0 mt-1 small" style="max-height: 160px; overflow-y: auto;">
                        @foreach(session('erros_importacao') as $erro)
                            <li>{{ $erro }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <a href="{{ route('export.mini-pauta.excel', ['turma_id' => $turmaId, 'disciplina_id' => $disciplinaId, 'trimestre' => $trimestre, 'ano_lectivo' => $anoLectivo]) }}"
                       class="btn btn-outline-primary w-100">
                        <i class="bi bi-download"></i> Descarregar modelo Excel
                    </a>
                </div>
                <div class="col-md-7">
                    <form action="{{ route('mini-pautas.importacao.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="turma_id" value="{{ $turmaId }}">
                        <input type="hidden" name="disciplina_id" value="{{ $disciplinaId }}">
                        <input type="hidden" name="trimestre" value="{{ $trimestre }}">
                        <input type="hidden" name="ano_lectivo" value="{{ $anoLectivo }}">

                        <div class="input-group">
                            <input type="file"
                                   name="arquivo"
                                   class="form-control form-control-sm @error('arquivo') is-invalid @enderror"
                                   accept=".xlsx,.xls"
                                   required>
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="bi bi-upload"></i> Importar
                            </button>
                        </div>
                        @error('arquivo')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </form>
                </div>
            </div>
        </div>
    </div>
@elseif($podeImportar && ! $trimestreAberto)
    <div class="alert alert-secondary small d-print-none">
        <i class="bi bi-lock me-1"></i>
        O {{ $trimestre }}.º trimestre está fechado para importação de notas.
    </div>
@endif
