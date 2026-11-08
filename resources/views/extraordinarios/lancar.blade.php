<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-0">
                    <i class="bi bi-clipboard-check me-2 text-warning"></i>
                    Exame Extraordinário — {{ $turma->nome }}
                </h2>
                <small class="text-muted">
                    {{ $turma->classe?->nome }} · {{ $turma->curso?->nome }} · Ano lectivo {{ $anoLectivo }}
                </small>
            </div>
            <a href="{{ route('extraordinarios.index') }}" class="btn btn-outline-secondary">
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

            <x-tip-card icon="bi-info-circle" variant="warning" title="Regras aplicadas" id="tip-lancar-extra" compact>
                Ano lectivo: <strong>{{ $anoLectivo }}</strong> ·
                Aprovação na disciplina via extraordinário: ≥ {{ \App\Services\ExtraordinarioService::APROVACAO_MINIMA }} valores ·
                A nota lançada <strong>substitui</strong> a anterior (CFD e/ou recurso) para efeitos de resultado final ·
                Realizado uma única vez por disciplina/ano (n.º 3 do Art.º 32).
            </x-tip-card>

            @if($elegibilidade->isEmpty())
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Nenhum aluno desta turma. Verifique se a turma tem alunos activos.
                </div>
            @else
                <form method="POST" action="{{ route('extraordinarios.guardar', $turma) }}">
                    @csrf
                    <input type="hidden" name="ano_lectivo" value="{{ $anoLectivo }}">

                    @php $elegiveis = $elegibilidade->where('elegivel', true); @endphp
                    <div class="card shadow-soft mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-check2-circle me-2 text-warning"></i>
                                Alunos Elegíveis ao Extraordinário
                            </h5>
                            <span class="badge bg-warning text-dark">{{ $elegiveis->count() }}</span>
                        </div>
                        <div class="card-body p-0">
                            @if($elegiveis->isEmpty())
                                <div class="text-center text-muted py-4">
                                    Nenhum aluno desta turma cumpre as condições do Art.º 32.
                                </div>
                            @else
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 60px;">N.º</th>
                                            <th>Aluno / Motivo</th>
                                            <th style="width: 55%;">Disciplinas elegíveis (CFD atual → Extraordinário)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($elegiveis as $e)
                                            <tr>
                                                <td class="text-center fw-bold">{{ $e['numero_ordem'] ?? '—' }}</td>
                                                <td>
                                                    <div>{{ $e['nome'] }}</div>
                                                    <small class="text-muted">{{ $e['motivo'] }}</small>
                                                </td>
                                                <td>
                                                    <div class="row g-2">
                                                        @foreach($e['disciplinas_elegiveis'] as $de)
                                                            @php
                                                                $disc = $disciplinas->get($de['disciplina_id']);
                                                                $valExistente = $extraordinariosExistentes[$e['aluno_id']][$de['disciplina_id']] ?? null;
                                                                $passa = $valExistente !== null && $valExistente >= \App\Services\ExtraordinarioService::APROVACAO_MINIMA;
                                                            @endphp
                                                            <div class="col-md-6 col-lg-4">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div class="flex-grow-1">
                                                                        <small class="text-muted d-block">
                                                                            <strong>{{ $disc->codigo ?: $disc->nome }}</strong>
                                                                            · CFD: <span class="text-danger fw-bold">{{ (int) round($de['cfd']) }}</span>
                                                                        </small>
                                                                    </div>
                                                                    <input
                                                                        type="number"
                                                                        name="extraordinarios[{{ $e['aluno_id'] }}][{{ $de['disciplina_id'] }}]"
                                                                        value="{{ $valExistente !== null ? (int) round($valExistente) : '' }}"
                                                                        min="0" max="20" step="1"
                                                                        class="form-control form-control-sm text-center fw-bold {{ $valExistente !== null ? ($passa ? 'text-success' : 'text-danger') : '' }}"
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
                                <button type="submit" class="btn btn-warning text-dark">
                                    <i class="bi bi-check2-square me-1"></i>Guardar Notas Extraordinárias
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
                                    Alunos sem condição prevista
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
