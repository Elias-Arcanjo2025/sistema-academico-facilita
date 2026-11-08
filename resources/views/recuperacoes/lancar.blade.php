<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="h4 mb-0">
                    <i class="bi bi-bandaid me-2 text-primary"></i>
                    Recuperação anual — {{ $turma->nome }}
                </h2>
                <small class="text-muted">
                    {{ $turma->classe?->nome }} · {{ $turma->curso?->nome }} · Ano {{ $anoLectivo }}
                </small>
            </div>
            <a href="{{ route('recuperacoes.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    @php
        $permiteFora = $permiteLancamentoSemElegibilidade ?? false;
        $alunosLancamento = $elegibilidade->filter(function ($e) use ($permiteFora, $recuperacoesExistentes) {
            if ($e['elegivel']) {
                return true;
            }
            if (! $permiteFora) {
                return false;
            }
            $terminais = collect($e['disciplinas_terminais'] ?? []);
            $comNota = ! empty($recuperacoesExistentes[$e['aluno_id']] ?? []);

            return $terminais->isNotEmpty() || $comNota;
        });
        $totalElegiveis = $alunosLancamento->filter(fn ($e) => $e['elegivel'] ?? false)->count();
        $totalFora = $alunosLancamento->count() - $totalElegiveis;
    @endphp

    @push('styles')
    @include('partials.exame-lancamento-estilos')
    @endpush

    <div class="py-4">
        <div class="container-fluid">
            @include('layouts.partials.assistente-contextual')

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <details class="exame-regras-collapse mb-3">
                <summary class="d-inline-flex align-items-center gap-1">
                    <i class="bi bi-info-circle"></i>
                    Regras de recuperação anual
                    <i class="bi bi-chevron-down small"></i>
                </summary>
                <div class="mt-2 ps-1 small text-muted">
                    Elegível: aluno em <strong>TRANS. C/DEF.</strong> na pauta T3 (1 a 2 disciplinas negativas, com pelo menos 1 terminal).
                    A nota <strong>substitui a CFD</strong>. Campo vazio remove a nota activa (histórico mantido).
                    @if($permiteFora)
                        A escola permite lançar <strong>fora da elegibilidade formal</strong> — registado no histórico.
                    @endif
                    @if(!empty($visaoProfessor))
                        Só pode lançar nas disciplinas que lecciona nesta turma.
                    @endif
                </div>
            </details>

            <form method="POST" action="{{ route('recuperacoes.guardar', $turma) }}" id="form-recuperacao"
                data-tipo-exame="recuperacao"
                data-simulador-url="{{ route('recuperacoes.simular', ['turma' => $turma, 'aluno' => '__ALUNO__']) }}">
                @csrf
                <input type="hidden" name="ano_lectivo" value="{{ $anoLectivo }}">

                <div class="card shadow-soft">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">
                            <i class="bi bi-pencil-square text-primary me-2"></i>Lançamento de recuperação
                        </h5>
                    </div>

                    @if($alunosLancamento->isNotEmpty())
                        <div class="exame-lancamento-toolbar">
                            <div class="exame-lancamento-stats">
                                <span class="exame-lancamento-stat exame-lancamento-stat--total">
                                    <i class="bi bi-people"></i>{{ $alunosLancamento->count() }} alunos
                                </span>
                                @if($totalElegiveis > 0)
                                    <span class="exame-lancamento-stat exame-lancamento-stat--ok">
                                        <i class="bi bi-check-circle"></i>{{ $totalElegiveis }} elegíveis
                                    </span>
                                @endif
                                @if($totalFora > 0)
                                    <span class="exame-lancamento-stat exame-lancamento-stat--warn">
                                        <i class="bi bi-exclamation-triangle"></i>{{ $totalFora }} fora elegibilidade
                                    </span>
                                @endif
                            </div>
                            <div class="exame-lancamento-filtros btn-group btn-group-sm" role="group" aria-label="Filtrar alunos">
                                <button type="button" class="btn btn-outline-secondary active" data-filtro-exame="todos">Todos</button>
                                <button type="button" class="btn btn-outline-success" data-filtro-exame="elegivel">Elegíveis</button>
                                @if($totalFora > 0)
                                    <button type="button" class="btn btn-outline-warning" data-filtro-exame="fora">Fora eleg.</button>
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="card-body p-0">
                        @if($alunosLancamento->isEmpty())
                            <x-empty-state
                                icon="bi-inbox"
                                message="Não há alunos para lançamento de recuperação nesta turma."
                            />
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 exame-lancamento-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:3rem;">N.º</th>
                                            <th class="exame-aluno-celula">Aluno</th>
                                            <th>Disciplinas · CFD/CA → Recuperação</th>
                                            <th class="exame-estado-celula">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($alunosLancamento as $e)
                                            @php
                                                $foraElegibilidade = ! ($e['elegivel'] ?? false) && $permiteFora;
                                                $estadoFiltro = $foraElegibilidade ? 'fora' : 'elegivel';
                                                $discsLancar = collect($e['elegivel'] ? ($e['disciplinas_elegiveis'] ?? []) : ($e['disciplinas_terminais'] ?? []));
                                                foreach (array_keys($recuperacoesExistentes[$e['aluno_id']] ?? []) as $discId) {
                                                    if (! $discsLancar->contains(fn ($d) => (int) ($d['disciplina_id'] ?? 0) === (int) $discId)) {
                                                        $discsLancar->push(['disciplina_id' => (int) $discId, 'nota_referencia' => null]);
                                                    }
                                                }
                                            @endphp
                                            <tr
                                                data-estado-exame="{{ $estadoFiltro }}"
                                                data-aluno-id="{{ $e['aluno_id'] }}"
                                                class="exame-lancamento-row {{ $foraElegibilidade ? 'exame-lancamento-row--fora' : 'exame-lancamento-row--elegivel' }}">
                                                <td class="text-center">
                                                    <span class="exame-aluno-num">{{ $e['numero_ordem'] ?? '—' }}</span>
                                                </td>
                                                <td class="exame-aluno-celula">
                                                    <span class="exame-aluno-nome">{{ $e['nome'] }}</span>
                                                </td>
                                                <td>
                                                    <div class="exame-disc-grid">
                                                        @foreach($discsLancar as $de)
                                                            @if(!empty($visaoProfessor) && !in_array((int) $de['disciplina_id'], $disciplinasProfessorIds ?? [], true))
                                                                @continue
                                                            @endif
                                                            @php
                                                                $disc = $disciplinas->get($de['disciplina_id']);
                                                                $val = $recuperacoesExistentes[$e['aluno_id']][$de['disciplina_id']] ?? null;
                                                                $passa = $val !== null && $val >= \App\Services\RecuperacaoService::APROVACAO_MINIMA;
                                                                $codigo = $disc?->codigo ?: $disc?->nome ?? 'Disc.';
                                                            @endphp
                                                            <x-exame-lancamento-disciplina
                                                                :codigo="$codigo"
                                                                :referencia="$de['nota_referencia'] ?? null"
                                                                rotulo-ref="CA"
                                                                :name="'recuperacoes['.$e['aluno_id'].']['.$de['disciplina_id'].']'"
                                                                :value="$val"
                                                                :passa="$passa"
                                                            />
                                                        @endforeach
                                                    </div>
                                                </td>
                                                <td class="exame-estado-celula">
                                                    @if($foraElegibilidade)
                                                        <span class="badge bg-warning text-dark" title="{{ $e['motivo'] ?? '' }}">
                                                            <i class="bi bi-exclamation-triangle me-1"></i>Fora eleg.
                                                        </span>
                                                    @elseif($e['elegivel'] ?? false)
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                            <i class="bi bi-check-circle me-1"></i>Elegível
                                                        </span>
                                                    @else
                                                        <span class="badge bg-secondary">{{ $e['motivo'] ?? '—' }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    @if($alunosLancamento->isNotEmpty())
                        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>Deixe vazio para remover nota activa.
                            </small>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check2-square me-1"></i>Guardar recuperações
                            </button>
                        </div>
                    @endif
                </div>

                @if(! $permiteFora)
                    @php $naoElegiveis = $elegibilidade->where('elegivel', false); @endphp
                    @if($naoElegiveis->isNotEmpty())
                        <div class="card shadow-soft mt-3">
                            <div class="card-header">
                                <h6 class="mb-0 text-muted">
                                    <i class="bi bi-x-circle me-2"></i>Não elegíveis ({{ $naoElegiveis->count() }})
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width:3rem;">N.º</th>
                                                <th>Aluno</th>
                                                <th>Motivo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($naoElegiveis as $e)
                                                <tr>
                                                    <td class="text-center text-muted">{{ $e['numero_ordem'] ?? '—' }}</td>
                                                    <td class="text-muted">{{ $e['nome'] }}</td>
                                                    <td><small>{{ $e['motivo'] }}</small></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </form>
        </div>
    </div>

    @push('scripts')
    @include('partials.exame-lancamento-filtro-script')
    @endpush
</x-app-layout>
