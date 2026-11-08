<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-arrow-repeat me-2 text-warning"></i>Transferência de Ano Lectivo
            </h2>
            <a href="{{ route('configuracoes.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <!-- Progress Steps -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="step-item completed">
                                <div class="step-number">1</div>
                                <div class="step-label">Configuração</div>
                            </div>
                            <div class="step-line completed"></div>
                            <div class="step-item active">
                                <div class="step-number">2</div>
                                <div class="step-label">Pré-visualização</div>
                            </div>
                            <div class="step-line"></div>
                            <div class="step-item">
                                <div class="step-number">3</div>
                                <div class="step-label">Execução</div>
                            </div>
                        </div>
                    </div>

                    <!-- Warnings -->
                    @if(count($preview['warnings']) > 0)
                        <div class="alert alert-warning alert-modern mb-4 fade-in">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div>
                                <strong>Avisos!</strong>
                                <ul class="mb-0 mt-2">
                                    @foreach($preview['warnings'] as $warning)
                                        <li>{{ $warning }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    <!-- Conflicts -->
                    @if(count($preview['conflicts']) > 0)
                        <div class="alert alert-danger alert-modern mb-4 fade-in">
                            <i class="bi bi-exclamation-octagon-fill"></i>
                            <div>
                                <strong>Conflitos Detectados!</strong>
                                <ul class="mb-0 mt-2">
                                    @foreach($preview['conflicts'] as $conflict)
                                        <li>{{ $conflict }}</li>
                                    @endforeach
                                </ul>
                                <small class="d-block mt-2">
                                    <strong>Nota:</strong> Turmas duplicadas serão ignoradas automaticamente.
                                </small>
                            </div>
                        </div>
                    @endif

                    <!-- Statistics Card -->
                    <div class="card shadow-soft mb-4 fade-in">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-2-circle me-2"></i>Passo 2: Resumo da Transferência
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <strong><i class="bi bi-calendar-check me-1"></i>Ano de Origem:</strong>
                                        <span class="badge bg-secondary ms-2">{{ $anoOrigem }}</span>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <strong><i class="bi bi-calendar-plus me-1"></i>Ano de Destino:</strong>
                                        <span class="badge bg-success ms-2">{{ $anoDestino }}</span>
                                    </p>
                                </div>
                            </div>

                            <hr>

                            <h6 class="mb-3"><i class="bi bi-bar-chart-fill me-2"></i>Estatísticas da Transferência</h6>

                            <div class="row g-3">
                                <!-- Turmas -->
                                <div class="col-md-6 col-lg-3">
                                    <div class="stat-card stat-card-primary">
                                        <div class="stat-icon">
                                            <i class="bi bi-people-fill"></i>
                                        </div>
                                        <div class="stat-content">
                                            <div class="stat-number">{{ $preview['stats']['turmas'] }}</div>
                                            <div class="stat-label">Turmas</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Associações -->
                                <div class="col-md-6 col-lg-3">
                                    <div class="stat-card stat-card-info">
                                        <div class="stat-icon">
                                            <i class="bi bi-link-45deg"></i>
                                        </div>
                                        <div class="stat-content">
                                            <div class="stat-number">
                                                {{ $opcoes['transferir_associacoes'] ? $preview['stats']['associacoes'] : 0 }}
                                            </div>
                                            <div class="stat-label">Associações</div>
                                            @if(!$opcoes['transferir_associacoes'])
                                                <small class="text-muted">(não selecionado)</small>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Directores -->
                                <div class="col-md-6 col-lg-3">
                                    <div class="stat-card stat-card-warning">
                                        <div class="stat-icon">
                                            <i class="bi bi-person-badge-fill"></i>
                                        </div>
                                        <div class="stat-content">
                                            <div class="stat-number">
                                                {{ $opcoes['transferir_directores'] ? $preview['stats']['directores'] : 0 }}
                                            </div>
                                            <div class="stat-label">Directores</div>
                                            @if(!$opcoes['transferir_directores'])
                                                <small class="text-muted">(não selecionado)</small>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Coordenadores -->
                                <div class="col-md-6 col-lg-3">
                                    <div class="stat-card stat-card-success">
                                        <div class="stat-icon">
                                            <i class="bi bi-mortarboard-fill"></i>
                                        </div>
                                        <div class="stat-content">
                                            <div class="stat-number">
                                                {{ $opcoes['transferir_coordenadores'] ? $preview['stats']['coordenadores'] : 0 }}
                                            </div>
                                            <div class="stat-label">Coordenadores</div>
                                            @if(!$opcoes['transferir_coordenadores'])
                                                <small class="text-muted">(não selecionado)</small>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sample Turmas -->
                    @if(count($preview['sample_turmas']) > 0)
                        <div class="card shadow-soft fade-in mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-eye me-2"></i>Amostra de Turmas a Transferir
                                </h5>
                                <small class="text-muted">(máximo 10)</small>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Código</th>
                                                <th>Nome</th>
                                                <th>Curso</th>
                                                <th>Classe</th>
                                                <th>Sala</th>
                                                <th>Turno</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($preview['sample_turmas'] as $turma)
                                                <tr>
                                                    <td><span class="badge bg-secondary">{{ $turma->codigo }}</span></td>
                                                    <td>{{ $turma->nome }}</td>
                                                    <td>{{ $turma->curso->nome ?? 'N/A' }}</td>
                                                    <td>{{ $turma->classe->nome ?? 'N/A' }}</td>
                                                    <td>{{ $turma->sala->nome ?? 'N/A' }}</td>
                                                    <td>{{ $turma->turno->nome ?? 'N/A' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @if($preview['stats']['turmas'] > 10)
                                <div class="card-footer bg-white">
                                    <p class="text-muted mb-0">
                                        <small>... e mais {{ $preview['stats']['turmas'] - 10 }} turma(s)</small>
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Info Alert -->
                    <div class="alert alert-info mb-4">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <strong>Importante:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Esta operação será executada numa <strong>transacção</strong> - se houver erro, todas as alterações serão revertidas</li>
                            <li>Os dados do ano de origem <strong>permanecerão intactos</strong></li>
                            <li>As turmas novas ficarão <strong>sem alunos</strong> - registo manual necessário</li>
                            <li>A operação será registada no <strong>log de actividades</strong></li>
                        </ul>
                    </div>

                    <!-- Action Buttons -->
                    <form action="{{ route('ano-lectivo-transfer.execute') }}" method="POST" id="formTransferencia">
                        @csrf
                        <input type="hidden" name="ano_origem" value="{{ $anoOrigem }}">
                        <input type="hidden" name="ano_destino" value="{{ $anoDestino }}">
                        <input type="hidden" name="transferir_associacoes" value="{{ $opcoes['transferir_associacoes'] ? '1' : '0' }}">
                        <input type="hidden" name="transferir_directores" value="{{ $opcoes['transferir_directores'] ? '1' : '0' }}">
                        <input type="hidden" name="transferir_coordenadores" value="{{ $opcoes['transferir_coordenadores'] ? '1' : '0' }}">
                        <input type="hidden" name="transitar_alunos" value="{{ $opcoes['transitar_alunos'] ? '1' : '0' }}">
                        <input type="hidden" name="transitar_reprovados" value="{{ $opcoes['transitar_reprovados'] ?? 0 ? '1' : '0' }}">

                        <div class="d-flex justify-content-between gap-2">
                            <a href="{{ route('ano-lectivo-transfer.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Voltar
                            </a>
                            <button type="button" class="btn btn-success btn-lg" onclick="handleConfirmTransfer({{ json_encode($preview['stats']) }}, '{{ $anoOrigem }}', '{{ $anoDestino }}')">
                                <i class="bi bi-check-circle me-1"></i>Confirmar e Executar Transferência
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            transition: all 0.3s;
        }

        .step-item.active .step-number {
            background: #0d6efd;
            color: white;
        }

        .step-item.completed .step-number {
            background: #198754;
            color: white;
        }

        .step-label {
            font-size: 0.875rem;
            color: #6c757d;
            font-weight: 500;
        }

        .step-item.active .step-label {
            color: #0d6efd;
            font-weight: 600;
        }

        .step-item.completed .step-label {
            color: #198754;
        }

        .step-line {
            flex: 1;
            height: 2px;
            background: #e9ecef;
            margin: 0 1rem;
            align-self: center;
            margin-bottom: 28px;
        }

        .step-line.completed {
            background: #198754;
        }

        .stat-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px solid #e9ecef;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-card-primary .stat-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card-info .stat-icon {
            background: linear-gradient(135deg, #0dceda 0%, #0d52d1 100%);
        }

        .stat-card-warning .stat-icon {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-card-success .stat-icon {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-content {
            flex: 1;
        }

        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            color: #212529;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .alert-modern {
            display: flex;
            gap: 1rem;
            align-items: start;
        }

        .alert-modern i {
            font-size: 1.5rem;
            margin-top: 0.25rem;
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .step-label {
                font-size: 0.75rem;
            }

            .step-number {
                width: 32px;
                height: 32px;
                font-size: 0.875rem;
            }

            .step-line {
                margin: 0 0.5rem;
                margin-bottom: 20px;
            }
        }
    </style>
</x-app-layout>

@once
@push('scripts')
<script>
/**
 * Academic Year Transfer Confirmation Handler (CRITICAL SYSTEM OPERATION)
 */
async function handleConfirmTransfer(stats, anoOrigem, anoDestino) {
    const confirmed = await Swal.fire({
        title: '⚠️ Confirmar Transferência de Ano Lectivo?',
        html: `
            <div class="text-start">
                <p class="fw-bold text-warning mb-3">
                    <i class="bi bi-exclamation-triangle-fill"></i> Esta é uma operação CRÍTICA do sistema!
                </p>
                <div class="bg-warning bg-opacity-10 p-3 rounded mb-3">
                    <p class="mb-2"><i class="bi bi-calendar-check"></i> <strong>De:</strong> <span class="badge bg-secondary">${anoOrigem}</span></p>
                    <p class="mb-3"><i class="bi bi-calendar-plus"></i> <strong>Para:</strong> <span class="badge bg-success">${anoDestino}</span></p>
                    <hr class="my-2">
                    <p class="mb-1"><i class="bi bi-people-fill"></i> <strong>Turmas:</strong> <span class="text-primary fw-bold">${stats.turmas}</span></p>
                    ${stats.associacoes > 0 ? `<p class="mb-1"><i class="bi bi-link-45deg"></i> <strong>Associações:</strong> <span class="text-info fw-bold">${stats.associacoes}</span></p>` : ''}
                    ${stats.directores > 0 ? `<p class="mb-1"><i class="bi bi-person-badge-fill"></i> <strong>Directores:</strong> <span class="text-warning fw-bold">${stats.directores}</span></p>` : ''}
                    ${stats.coordenadores > 0 ? `<p class="mb-0"><i class="bi bi-mortarboard-fill"></i> <strong>Coordenadores:</strong> <span class="text-success fw-bold">${stats.coordenadores}</span></p>` : ''}
                </div>
                <div class="alert alert-info small mb-0">
                    <i class="bi bi-info-circle"></i> <strong>Garantias:</strong>
                    <ul class="mb-0 mt-1">
                        <li>Operação em transação (tudo ou nada)</li>
                        <li>Dados de origem permanecem intactos</li>
                        <li>Turmas novas ficam sem alunos (registo manual)</li>
                        <li>Registrado no log de atividades</li>
                    </ul>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-arrow-repeat"></i> Sim, executar transferência',
        cancelButtonText: '<i class="bi bi-x-lg"></i> Cancelar',
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        customClass: {
            confirmButton: 'btn btn-success btn-lg',
            cancelButton: 'btn btn-secondary'
        },
        buttonsStyling: false
    });

    if (confirmed.isConfirmed) {
        showLoading('Transferindo dados...');
        document.getElementById('formTransferencia').submit();
    }
}
</script>
@endpush
@endonce
