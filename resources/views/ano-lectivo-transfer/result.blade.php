<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-arrow-repeat me-2 text-warning"></i>Transferência de Ano Lectivo
            </h2>
            <a href="{{ route('configuracoes.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar para Configurações
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
                                <div class="step-number"><i class="bi bi-check"></i></div>
                                <div class="step-label">Configuração</div>
                            </div>
                            <div class="step-line completed"></div>
                            <div class="step-item completed">
                                <div class="step-number"><i class="bi bi-check"></i></div>
                                <div class="step-label">Pré-visualização</div>
                            </div>
                            <div class="step-line completed"></div>
                            <div class="step-item completed">
                                <div class="step-number"><i class="bi bi-check"></i></div>
                                <div class="step-label">Concluído</div>
                            </div>
                        </div>
                    </div>

                    @if($success)
                        <!-- Success Message -->
                        <div class="alert alert-success alert-modern mb-4 fade-in">
                            <i class="bi bi-check-circle-fill"></i>
                            <div>
                                <strong>Transferência Concluída com Sucesso!</strong>
                                <p class="mb-0 mt-2">
                                    Os dados do ano lectivo <strong>{{ $anoOrigem }}</strong> foram transferidos para <strong>{{ $anoDestino }}</strong>.
                                </p>
                            </div>
                        </div>

                        <!-- Statistics Card -->
                        <div class="card shadow-soft mb-4 fade-in">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-3-circle me-2"></i>Resultado da Transferência
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

                                <h6 class="mb-3"><i class="bi bi-clipboard-check-fill me-2"></i>Dados Transferidos</h6>

                                <div class="row g-3">
                                    <!-- Turmas -->
                                    <div class="col-md-6 col-lg-3">
                                        <div class="result-card result-card-primary">
                                            <div class="result-icon">
                                                <i class="bi bi-people-fill"></i>
                                            </div>
                                            <div class="result-content">
                                                <div class="result-number">{{ $stats['turmas'] ?? 0 }}</div>
                                                <div class="result-label">Turmas</div>
                                                <div class="result-status">
                                                    <i class="bi bi-check-circle-fill text-success"></i> Transferidas
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Associações -->
                                    <div class="col-md-6 col-lg-3">
                                        <div class="result-card result-card-info">
                                            <div class="result-icon">
                                                <i class="bi bi-link-45deg"></i>
                                            </div>
                                            <div class="result-content">
                                                <div class="result-number">{{ $stats['associacoes'] ?? 0 }}</div>
                                                <div class="result-label">Associações</div>
                                                <div class="result-status">
                                                    @if(isset($stats['associacoes']) && $stats['associacoes'] > 0)
                                                        <i class="bi bi-check-circle-fill text-success"></i> Transferidas
                                                    @else
                                                        <i class="bi bi-dash-circle text-muted"></i> Não selecionado
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Directores -->
                                    <div class="col-md-6 col-lg-3">
                                        <div class="result-card result-card-warning">
                                            <div class="result-icon">
                                                <i class="bi bi-person-badge-fill"></i>
                                            </div>
                                            <div class="result-content">
                                                <div class="result-number">{{ $stats['directores'] ?? 0 }}</div>
                                                <div class="result-label">Directores</div>
                                                <div class="result-status">
                                                    @if(isset($stats['directores']) && $stats['directores'] > 0)
                                                        <i class="bi bi-check-circle-fill text-success"></i> Transferidos
                                                    @else
                                                        <i class="bi bi-dash-circle text-muted"></i> Não selecionado
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Coordenadores -->
                                    <div class="col-md-6 col-lg-3">
                                        <div class="result-card result-card-success">
                                            <div class="result-icon">
                                                <i class="bi bi-mortarboard-fill"></i>
                                            </div>
                                            <div class="result-content">
                                                <div class="result-number">{{ $stats['coordenadores'] ?? 0 }}</div>
                                                <div class="result-label">Coordenadores</div>
                                                <div class="result-status">
                                                    @if(isset($stats['coordenadores']) && $stats['coordenadores'] > 0)
                                                        <i class="bi bi-check-circle-fill text-success"></i> Transferidos
                                                    @else
                                                        <i class="bi bi-dash-circle text-muted"></i> Não selecionado
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @if(isset($stats['skipped']) && $stats['skipped'] > 0)
                                    <div class="alert alert-info mt-4 mb-0">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <strong>{{ $stats['skipped'] }}</strong> turma(s) foram ignoradas porque já existiam no ano de destino.
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Next Steps -->
                        <div class="card shadow-soft mb-4 fade-in">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-signpost-2 me-2"></i>Próximos Passos
                                </h6>
                            </div>
                            <div class="card-body">
                                <ol class="mb-0">
                                    <li class="mb-2">
                                        <strong>Registar Alunos:</strong> As turmas foram criadas mas estão vazias. É necessário registar os alunos manualmente.
                                    </li>
                                    <li class="mb-2">
                                        <strong>Verificar Associações:</strong> Confirme que os professores estão atribuídos correctamente às disciplinas e turmas.
                                    </li>
                                    <li class="mb-2">
                                        <strong>Verificar Directores de Turma:</strong> Confirme que cada turma tem o director correcto.
                                    </li>
                                    <li class="mb-0">
                                        <strong>Activar Ano Lectivo:</strong> Se necessário, altere o ano lectivo actual nas configurações do sistema.
                                    </li>
                                </ol>
                            </div>
                        </div>

                    @else
                        <!-- Error Message -->
                        <div class="alert alert-danger alert-modern mb-4 fade-in">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div>
                                <strong>Erro na Transferência!</strong>
                                <p class="mb-0 mt-2">
                                    {{ $message ?? 'Ocorreu um erro desconhecido durante a transferência.' }}
                                </p>
                                <small class="d-block mt-2 text-muted">
                                    Todas as alterações foram revertidas automaticamente. Nenhum dado foi modificado.
                                </small>
                            </div>
                        </div>

                        <div class="card shadow-soft mb-4">
                            <div class="card-body text-center">
                                <i class="bi bi-x-circle text-danger" style="font-size: 4rem;"></i>
                                <h5 class="mt-3">A transferência não pôde ser completada</h5>
                                <p class="text-muted">
                                    Por favor, verifique o erro acima e tente novamente. Se o problema persistir, contacte o suporte técnico.
                                </p>
                            </div>
                        </div>
                    @endif

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between gap-2">
                        @if($success)
                            <a href="{{ route('turmas.index') }}" class="btn btn-primary">
                                <i class="bi bi-people-fill me-1"></i>Ver Turmas
                            </a>
                            <div class="d-flex gap-2">
                                <a href="{{ route('ano-lectivo-transfer.index') }}" class="btn btn-outline-warning">
                                    <i class="bi bi-arrow-repeat me-1"></i>Nova Transferência
                                </a>
                                <a href="{{ route('configuracoes.index') }}" class="btn btn-success">
                                    <i class="bi bi-check-lg me-1"></i>Concluir
                                </a>
                            </div>
                        @else
                            <a href="{{ route('configuracoes.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Voltar para Configurações
                            </a>
                            <a href="{{ route('ano-lectivo-transfer.index') }}" class="btn btn-warning">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Tentar Novamente
                            </a>
                        @endif
                    </div>
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

        .step-item.completed .step-number {
            background: #198754;
            color: white;
        }

        .step-label {
            font-size: 0.875rem;
            color: #6c757d;
            font-weight: 500;
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

        .result-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px solid #e9ecef;
            transition: all 0.3s;
            height: 100%;
        }

        .result-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .result-icon {
            width: 50px;
            height: 50px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
        }

        .result-card-primary .result-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .result-card-info .result-icon {
            background: linear-gradient(135deg, #0dceda 0%, #0d52d1 100%);
        }

        .result-card-warning .result-icon {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .result-card-success .result-icon {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .result-content {
            flex: 1;
        }

        .result-number {
            font-size: 1.75rem;
            font-weight: 700;
            color: #212529;
            line-height: 1;
        }

        .result-label {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .result-status {
            font-size: 0.75rem;
            margin-top: 0.5rem;
            font-weight: 500;
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
