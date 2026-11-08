<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-0">
                    <i class="bi bi-graph-up-arrow me-2 text-success"></i>
                    Lançamento de Melhoria — {{ $turma->nome }}
                </h2>
                <small class="text-muted">
                    {{ $turma->classe?->nome }} · {{ $turma->curso?->nome }} · Ano lectivo {{ $anoLectivo }}
                </small>
            </div>
            <a href="{{ route('melhorias.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container-fluid">
            @if(session('success'))
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
                </div>
            @endif

            @php
                $anoSistema = \App\Models\Configuracao::getAnoLectivoActual();
                $divergeAno = $anoLectivo !== $anoSistema;
            @endphp
            @if($divergeAno)
                <div class="alert alert-warning mt-2 mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Esta turma é do ano lectivo <strong>{{ $anoLectivo }}</strong>, mas o ano configurado como actual no sistema é <strong>{{ $anoSistema }}</strong>.
                    As notas serão lançadas no ano da turma — confirme antes de guardar.
                </div>
            @endif

            <x-tip-card icon="bi-info-circle" variant="success" title="Regras aplicadas (Art.º 34.º)" id="tip-lancar-melhoria" compact>
                Ano lectivo: <strong>{{ $anoLectivo }}</strong> ·
                Faixa elegível: <strong>CFD entre {{ $config['nota_min'] }} e {{ $config['nota_max'] }} valores</strong> (apenas aprovados) ·
                Regra final: <strong>MAX(CFD original, nota de melhoria)</strong> ·
                A melhoria só pode <em>aumentar</em> a nota — nunca a baixa.
            </x-tip-card>

            @if($elegibilidade->isEmpty())
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Nenhum aluno desta turma. Verifique se a turma tem alunos activos.
                </div>
            @else
                <form method="POST" action="{{ route('melhorias.guardar', $turma) }}">
                    @csrf
                    <input type="hidden" name="ano_lectivo" value="{{ $anoLectivo }}">

                    @php $elegiveis = $elegibilidade->where('elegivel', true); @endphp
                    <div class="card shadow-soft mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-check2-circle me-2 text-success"></i>
                                Alunos com disciplinas elegíveis a melhoria
                            </h5>
                            <span class="badge bg-success">{{ $elegiveis->count() }}</span>
                        </div>
                        <div class="card-body p-0">
                            @if($elegiveis->isEmpty())
                                <div class="text-center text-muted py-4">
                                    Nenhum aluno desta turma é elegível a melhoria.
                                </div>
                            @else
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 60px;">N.º</th>
                                            <th>Aluno</th>
                                            <th style="width: 60%;">Disciplinas elegíveis (CFD atual → Melhoria)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($elegiveis as $e)
                                            <tr>
                                                <td class="text-center fw-bold">{{ $e['numero_ordem'] ?? '—' }}</td>
                                                <td>{{ $e['nome'] }}</td>
                                                <td>
                                                    <div class="row g-2">
                                                        @foreach($e['disciplinas_elegiveis'] as $de)
                                                            @php
                                                                $disc = $disciplinas->get($de['disciplina_id']);
                                                                $valExistente = $melhoriasExistentes[$e['aluno_id']][$de['disciplina_id']] ?? null;
                                                                $melhorou = $valExistente !== null && $valExistente > $de['cfd'];
                                                            @endphp
                                                            <div class="col-md-6 col-lg-4">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div class="flex-grow-1">
                                                                        <small class="text-muted d-block">
                                                                            <strong>{{ $disc->codigo ?: $disc->nome }}</strong>
                                                                            · CFD: <span class="text-primary fw-bold">{{ (int) round($de['cfd']) }}</span>
                                                                        </small>
                                                                    </div>
                                                                    <input
                                                                        type="number"
                                                                        name="melhorias[{{ $e['aluno_id'] }}][{{ $de['disciplina_id'] }}]"
                                                                        value="{{ $valExistente !== null ? (int) round($valExistente) : '' }}"
                                                                        min="0" max="20" step="1"
                                                                        class="form-control form-control-sm text-center fw-bold {{ $valExistente !== null ? ($melhorou ? 'text-success' : 'text-muted') : '' }}"
                                                                        style="width: 70px;"
                                                                        placeholder="—">
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                        @if($elegiveis->isNotEmpty())
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check2-square me-1"></i>Guardar Melhorias
                                </button>
                            </div>
                        @endif
                    </div>

                    @php $naoElegiveis = $elegibilidade->where('elegivel', false); @endphp
                    @if($naoElegiveis->isNotEmpty())
                        <div class="card shadow-soft mt-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-x-circle me-2 text-muted"></i>
                                    Alunos sem disciplinas elegíveis
                                </h5>
                                <span class="badge bg-secondary">{{ $naoElegiveis->count() }}</span>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 60px;">N.º</th>
                                            <th>Aluno</th>
                                            <th>Motivo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($naoElegiveis as $e)
                                            <tr>
                                                <td class="text-center text-muted">{{ $e['numero_ordem'] ?? '—' }}</td>
                                                <td class="text-muted">{{ $e['nome'] }}</td>
                                                <td><small class="text-muted">{{ $e['motivo'] }}</small></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
