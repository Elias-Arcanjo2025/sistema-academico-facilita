<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-0">
                    <i class="bi bi-arrow-counterclockwise me-2 text-danger"></i>
                    Lançamento de Recurso — {{ $turma->nome }}
                </h2>
                <small class="text-muted">
                    {{ $turma->classe?->nome }} · {{ $turma->curso?->nome }} · Ano lectivo {{ $anoLectivo }}
                </small>
            </div>
            <a href="{{ route('recursos.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container-fluid">
            @include('layouts.partials.assistente-contextual')

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

            <x-tip-card icon="bi-info-circle" variant="warning" title="Regras aplicadas" id="tip-lancar-recurso" compact>
                Ano lectivo: <strong>{{ $anoLectivo }}</strong> ·
                Faixa elegível: <strong>{{ $config['nota_min'] }}–{{ $config['nota_max'] }} valores</strong> ·
                Máximo de disciplinas: <strong>{{ $config['max_disciplinas'] }}</strong> ·
                Aprovação na disciplina via recurso: ≥ {{ \App\Services\RecursoService::APROVACAO_MINIMA }} valores.
                A nota lançada <strong>substitui</strong> a CFD anterior para efeitos de resultado final.
                @if($permiteLancamentoSemElegibilidade ?? false)
                    A escola permite lançar <strong>fora da elegibilidade formal</strong> — essas notas ficam registadas no histórico.
                @endif
                @if(!empty($visaoProfessor))
                    Só pode lançar nas disciplinas que lecciona nesta turma.
                @endif
            </x-tip-card>

            @php
                $permiteFora = $permiteLancamentoSemElegibilidade ?? false;
                $alunosLancamento = $elegibilidade->filter(function ($e) use ($permiteFora, $recursosExistentes) {
                    if ($e['elegivel']) {
                        return true;
                    }
                    if (! $permiteFora) {
                        return false;
                    }
                    $naFaixa = collect($e['disciplinas_negativas_faixa'] ?? []);
                    $comNota = ! empty($recursosExistentes[$e['aluno_id']] ?? []);

                    return $naFaixa->isNotEmpty() || $comNota;
                });
            @endphp

            @if($elegibilidade->isEmpty())
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Nenhum aluno desta turma. Verifique se a turma tem alunos activos.
                </div>
            @else
                <form method="POST" action="{{ route('recursos.guardar', $turma) }}" id="form-recurso"
                    data-tipo-exame="recurso"
                    data-simulador-url="{{ route('recursos.simular', ['turma' => $turma, 'aluno' => '__ALUNO__']) }}">
                    @csrf
                    <input type="hidden" name="ano_lectivo" value="{{ $anoLectivo }}">

                    <div class="card shadow-soft mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="mb-0">
                                <i class="bi bi-pencil-square me-2 text-primary"></i>
                                Lançamento de recurso
                            </h5>
                            <span class="badge bg-primary">{{ $alunosLancamento->count() }} aluno(s)</span>
                        </div>
                        <div class="card-body p-0">
                            @if($alunosLancamento->isEmpty())
                                <div class="text-center text-muted py-4">
                                    Nenhum aluno disponível para lançamento de recurso.
                                </div>
                            @else
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 60px;">N.º</th>
                                            <th>Aluno</th>
                                            <th style="width: 60%;">Disciplinas (CFD anterior → Recurso)</th>
                                            <th style="width: 140px;">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($alunosLancamento as $e)
                                            @php
                                                $foraElegibilidade = ! ($e['elegivel'] ?? false) && $permiteFora;
                                                $discsLancar = collect($e['elegivel'] ? ($e['disciplinas_elegiveis'] ?? []) : ($e['disciplinas_negativas_faixa'] ?? []));
                                                foreach (array_keys($recursosExistentes[$e['aluno_id']] ?? []) as $discId) {
                                                    if (! $discsLancar->contains(fn ($d) => (int) ($d['disciplina_id'] ?? 0) === (int) $discId)) {
                                                        $discsLancar->push(['disciplina_id' => (int) $discId, 'cfd' => null]);
                                                    }
                                                }
                                            @endphp
                                            <tr class="{{ $foraElegibilidade ? 'table-warning' : '' }}" data-aluno-id="{{ $e['aluno_id'] }}">
                                                <td class="text-center fw-bold">{{ $e['numero_ordem'] ?? '—' }}</td>
                                                <td>{{ $e['nome'] }}</td>
                                                <td>
                                                    <div class="row g-2 exame-disc-grid">
                                                        @foreach($discsLancar as $de)
                                                            @if(!empty($visaoProfessor) && !in_array((int) $de['disciplina_id'], $disciplinasProfessorIds ?? [], true))
                                                                @continue
                                                            @endif
                                                            @php
                                                                $disc = $disciplinas->get($de['disciplina_id']);
                                                                $valExistente = $recursosExistentes[$e['aluno_id']][$de['disciplina_id']] ?? null;
                                                                $passa = $valExistente !== null && $valExistente >= \App\Services\RecursoService::APROVACAO_MINIMA;
                                                            @endphp
                                                            <div class="col-md-6 col-lg-4">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div class="flex-grow-1">
                                                                        <small class="text-muted d-block">
                                                                            <strong>{{ $disc?->codigo ?: $disc?->nome ?? 'Disciplina' }}</strong>
                                                                            @if(($de['cfd'] ?? null) !== null)
                                                                                · CFD: <span class="text-danger fw-bold">{{ (int) round($de['cfd']) }}</span>
                                                                            @endif
                                                                        </small>
                                                                    </div>
                                                                    <input
                                                                        type="number"
                                                                        name="recursos[{{ $e['aluno_id'] }}][{{ $de['disciplina_id'] }}]"
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
                                                <td>
                                                    @if($foraElegibilidade)
                                                        <span class="badge bg-warning text-dark" title="{{ $e['motivo'] ?? '' }}">Fora elegibilidade</span>
                                                    @elseif($e['elegivel'] ?? false)
                                                        <span class="badge bg-success">Elegível</span>
                                                    @else
                                                        <span class="badge bg-secondary">{{ $e['motivo'] ?? '—' }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                        @if($alunosLancamento->isNotEmpty())
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check2-square me-1"></i>Guardar Recursos
                                </button>
                            </div>
                        @endif
                    </div>

                    @if(! $permiteFora)
                        @php $naoElegiveis = $elegibilidade->where('elegivel', false); @endphp
                        @if($naoElegiveis->isNotEmpty())
                            <div class="card shadow-soft mt-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="bi bi-x-circle me-2 text-muted"></i>
                                        Alunos NÃO Elegíveis
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
                    @endif
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
