<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-arrow-right-circle me-2 text-primary"></i>Pré-visualização da Transição
            </h2>
            <a href="{{ route('alunos.transicao.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Alunos', 'url' => route('alunos.index')],
                ['label' => 'Transição de Alunos', 'url' => route('alunos.transicao.index')],
                ['label' => 'Pré-visualização']
            ]" />

            <!-- Estatísticas -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h2 class="mb-0">{{ $stats['total_alunos'] }}</h2>
                            <small>Total de Alunos</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h2 class="mb-0">{{ $stats['aprovados'] }}</h2>
                            <small>Aprovados</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <h2 class="mb-0">{{ $stats['reprovados'] }}</h2>
                            <small>Reprovados</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h2 class="mb-0">{{ $stats['pendentes'] }}</h2>
                            <small>Pendentes</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Avisos -->
            @if(!empty($warnings))
                <div class="alert alert-warning mb-4">
                    <h6 class="alert-heading">
                        <i class="bi bi-exclamation-triangle me-2"></i>Avisos
                    </h6>
                    <ul class="mb-0">
                        @foreach($warnings as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('alunos.transicao.execute') }}" method="POST" id="formTransicao">
                @csrf

                <!-- Campos Hidden -->
                <input type="hidden" name="ano_origem" value="{{ $ano_origem }}">
                <input type="hidden" name="ano_destino" value="{{ $ano_destino }}">
                <input type="hidden" name="transitar_reprovados" value="{{ $opcoes['transitar_reprovados'] ?? 0 }}">

                <!-- Mapeamento de Turmas -->
                <div class="card shadow-soft mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-2-circle me-2"></i>Passo 2: Mapeamento de Turmas
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-nowrap">Turma de Origem</th>
                                        <th class="text-nowrap">Curso</th>
                                        <th class="text-nowrap text-center">Alunos</th>
                                        <th class="text-nowrap text-center">Aprovados</th>
                                        <th class="text-nowrap text-center">Reprovados</th>
                                        <th class="text-nowrap">Destino (Promoção)</th>
                                        <th class="text-nowrap">Destino (Repetência)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($turmas_origem as $turmaOrigem)
                                        @php
                                            $alunosTurma = collect($situacoes_alunos)->filter(fn($s) => $s['aluno']->turma_id === $turmaOrigem->id);
                                            $aprovadosTurma = $alunosTurma->where('situacao', 'aprovado')->count();
                                            $reprovadosTurma = $alunosTurma->where('situacao', 'reprovado')->count();
                                        @endphp
                                        <tr>
                                            <td class="text-nowrap">
                                                <strong>{{ $turmaOrigem->nome }}</strong>
                                            </td>
                                            <td class="text-nowrap">
                                                <small class="text-muted">{{ $turmaOrigem->curso->nome }}</small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary">{{ $alunosTurma->count() }}</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-success">{{ $aprovadosTurma }}</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-danger">{{ $reprovadosTurma }}</span>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm"
                                                        name="mapeamento_promocao[{{ $turmaOrigem->id }}]">
                                                    <option value="">Não transitar aprovados</option>
                                                    @foreach($turmas_destino as $turmaDestino)
                                                        <option value="{{ $turmaDestino->id }}"
                                                                {{ ($mapeamento_promocao[$turmaOrigem->id] ?? null) == $turmaDestino->id ? 'selected' : '' }}>
                                                            {{ $turmaDestino->nome }} - {{ $turmaDestino->curso->nome }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <small class="text-muted">Classe seguinte</small>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm"
                                                        name="mapeamento_repetencia[{{ $turmaOrigem->id }}]">
                                                    <option value="">Não transitar reprovados</option>
                                                    @foreach($turmas_destino as $turmaDestino)
                                                        <option value="{{ $turmaDestino->id }}"
                                                                {{ ($mapeamento_repetencia[$turmaOrigem->id] ?? null) == $turmaDestino->id ? 'selected' : '' }}>
                                                            {{ $turmaDestino->nome }} - {{ $turmaDestino->curso->nome }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <small class="text-muted">Mesma classe</small>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Seleção Individual (se ativada) -->
                @if($opcoes['selecao_individual'] ?? false)
                    <div class="card shadow-soft mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-3-circle me-2"></i>Passo 3: Seleção Individual de Alunos
                            </h5>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="selecionarTodos()">
                                    <i class="bi bi-check-all me-1"></i>Seleccionar Todos
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="desselecionarTodos()">
                                    <i class="bi bi-x-lg me-1"></i>Desseleccionar Todos
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            @foreach($turmas_origem as $turmaOrigem)
                                @php
                                    $alunosTurma = collect($situacoes_alunos)
                                        ->filter(fn($s) => $s['aluno']->turma_id === $turmaOrigem->id)
                                        ->sortBy('aluno.numero_ordem');
                                @endphp

                                @if($alunosTurma->isNotEmpty())
                                    <h6 class="mt-3 mb-3">
                                        <i class="bi bi-mortarboard me-2"></i>{{ $turmaOrigem->nome }} - {{ $turmaOrigem->curso->nome }}
                                    </h6>
                                    <div class="row">
                                        @foreach($alunosTurma as $alunoId => $dados)
                                            @php
                                                $aluno = $dados['aluno'];
                                                $situacao = $dados['situacao'];
                                                $reprovacoes = $dados['reprovacoes'];

                                                // Auto-selecionar aprovados por padrão
                                                $autoSelecionar = $situacao === 'aprovado';
                                                if ($situacao === 'reprovado' && ($opcoes['transitar_reprovados'] ?? false)) {
                                                    $autoSelecionar = true;
                                                }
                                            @endphp
                                            <div class="col-md-6 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input aluno-checkbox"
                                                           type="checkbox"
                                                           name="alunos_selecionados[{{ $aluno->id }}]"
                                                           id="aluno_{{ $aluno->id }}"
                                                           value="1"
                                                           {{ $autoSelecionar ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="aluno_{{ $aluno->id }}">
                                                        <strong>{{ $aluno->nome }}</strong>
                                                        <code class="ms-2">{{ $aluno->numero_processo }}</code>
                                                        <br>
                                                        <small class="text-muted">Nº Ordem: {{ $aluno->numero_ordem }}</small>
                                                        @if($situacao === 'aprovado')
                                                            <span class="badge bg-success ms-2">Aprovado</span>
                                                        @elseif($situacao === 'reprovado')
                                                            <span class="badge bg-danger ms-2">Reprovado ({{ $reprovacoes }} disc.)</span>
                                                        @else
                                                            <span class="badge bg-warning ms-2">Pendente</span>
                                                        @endif
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <hr>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Resumo e Confirmação -->
                <div class="card shadow-soft mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="bi bi-check-circle me-2"></i>Confirmação
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Resumo da Transição:</strong>
                            <ul class="mt-2 mb-0">
                                <li><strong>Ano de Origem:</strong> {{ $ano_origem }}</li>
                                <li><strong>Ano de Destino:</strong> {{ $ano_destino }}</li>
                                <li><strong>Total de Alunos:</strong> {{ $stats['total_alunos'] }}</li>
                                <li><strong>Aprovados:</strong> {{ $stats['aprovados'] }} (serão transitados para próxima classe)</li>
                                @if($opcoes['transitar_reprovados'] ?? false)
                                    <li><strong>Reprovados:</strong> {{ $stats['reprovados'] }} (repetirão a mesma classe)</li>
                                @endif
                                <li><strong>Pendentes:</strong> {{ $stats['pendentes'] }} (não serão transitados automaticamente)</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Botões de Ação -->
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('alunos.transicao.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Voltar
                    </a>
                    <button type="button"
                            class="btn btn-primary btn-lg"
                            onclick="handleConfirmTransicao({{ $stats['total_alunos'] }}, {{ $stats['aprovados'] }}, {{ $stats['reprovados'] }}, '{{ $ano_origem }}', '{{ $ano_destino }}')">
                        <i class="bi bi-check-circle me-1"></i>Executar Transição
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

@once
@push('scripts')
<script>
/**
 * Student Transition Confirmation Handler (CRITICAL MASS OPERATION)
 */
function selecionarTodos() {
    document.querySelectorAll('.aluno-checkbox').forEach(cb => cb.checked = true);
}

function desselecionarTodos() {
    document.querySelectorAll('.aluno-checkbox').forEach(cb => cb.checked = false);
}

async function handleConfirmTransicao(totalAlunos, aprovados, reprovados, anoOrigem, anoDestino) {
    const confirmed = await Swal.fire({
        title: '⚠️ Executar Transição de Alunos?',
        html: `
            <div class="text-start">
                <p class="fw-bold text-warning mb-3">
                    <i class="bi bi-exclamation-triangle-fill"></i> Esta é uma operação em MASSA que afetará muitos registos!
                </p>
                <div class="bg-primary bg-opacity-10 p-3 rounded mb-3">
                    <p class="mb-2"><i class="bi bi-calendar-check"></i> <strong>Ano de Origem:</strong> <span class="badge bg-secondary">${anoOrigem}</span></p>
                    <p class="mb-2"><i class="bi bi-calendar-plus"></i> <strong>Ano de Destino:</strong> <span class="badge bg-success">${anoDestino}</span></p>
                    <hr class="my-2">
                    <p class="mb-2"><strong>Total de Alunos:</strong> <span class="text-primary fw-bold">${totalAlunos}</span></p>
                    <p class="mb-2"><strong>Aprovados:</strong> <span class="text-success fw-bold">${aprovados}</span> (próxima classe)</p>
                    ${reprovados > 0 ? `<p class="mb-0"><strong>Reprovados:</strong> <span class="text-danger fw-bold">${reprovados}</span> (mesma classe)</p>` : ''}
                </div>
                <div class="alert alert-info small mb-0">
                    <i class="bi bi-info-circle"></i> <strong>O que acontecerá:</strong>
                    <ul class="mb-0 mt-1">
                        <li>Novos registos serão criados no ano de destino</li>
                        <li>Contas de portal serão actualizadas para o novo registo</li>
                        <li>Os dados originais permanecerão intactos</li>
                        <li>A operação será registada no log</li>
                    </ul>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-check-circle"></i> Sim, executar transição',
        cancelButtonText: '<i class="bi bi-x-lg"></i> Cancelar',
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        customClass: {
            confirmButton: 'btn btn-primary btn-lg',
            cancelButton: 'btn btn-secondary'
        },
        buttonsStyling: false
    });

    if (confirmed.isConfirmed) {
        showLoading('Transitando alunos...');
        document.getElementById('formTransicao').submit();
    }
}
</script>
@endpush
@endonce
