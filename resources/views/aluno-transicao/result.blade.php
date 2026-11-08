<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-check-circle me-2 text-success"></i>Resultado da Transição
            </h2>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Alunos', 'url' => route('alunos.index')],
                ['label' => 'Transição de Alunos', 'url' => route('alunos.transicao.index')],
                ['label' => 'Resultado']
            ]" />

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    @if($success)
                        <!-- Sucesso -->
                        <div class="card shadow-soft border-success mb-4 fade-in">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-check-circle-fill me-2"></i>Transição Concluída com Sucesso!
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="lead">{{ $message }}</p>

                                <!-- Estatísticas -->
                                <div class="row text-center mt-4">
                                    <div class="col-md-6 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h2 class="text-primary mb-1">{{ $stats['alunos_transitados'] }}</h2>
                                                <small class="text-muted">Alunos Transitados</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h2 class="text-success mb-1">{{ $stats['alunos_aprovados'] ?? 0 }}</h2>
                                                <small class="text-muted">Aprovados (próxima classe)</small>
                                            </div>
                                        </div>
                                    </div>
                                    @if(($stats['alunos_reprovados'] ?? 0) > 0)
                                        <div class="col-md-6 mb-3">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h2 class="text-warning mb-1">{{ $stats['alunos_reprovados'] }}</h2>
                                                    <small class="text-muted">Reprovados (mesma classe)</small>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                    @if(($stats['contas_portal_actualizadas'] ?? 0) > 0)
                                        <div class="col-md-6 mb-3">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h2 class="text-info mb-1">{{ $stats['contas_portal_actualizadas'] }}</h2>
                                                    <small class="text-muted">Contas de portal actualizadas</small>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                    @if(($stats['alunos_ignorados'] ?? 0) > 0)
                                        <div class="col-md-6 mb-3">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h2 class="text-secondary mb-1">{{ $stats['alunos_ignorados'] }}</h2>
                                                    <small class="text-muted">Não transitados</small>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <!-- Detalhes da Transição -->
                                <div class="alert alert-info mt-4">
                                    <h6 class="alert-heading">
                                        <i class="bi bi-info-circle me-2"></i>Detalhes da Transição
                                    </h6>
                                    <ul class="mb-0">
                                        <li><strong>Ano de Origem:</strong> {{ $anoOrigem }}</li>
                                        <li><strong>Ano de Destino:</strong> {{ $anoDestino }}</li>
                                        <li><strong>Data da Transição:</strong> {{ now()->format('d/m/Y H:i:s') }}</li>
                                    </ul>
                                </div>

                                <!-- Erros/Avisos (se houver) -->
                                @if(!empty($stats['erros']))
                                    <div class="alert alert-warning mt-3">
                                        <h6 class="alert-heading">
                                            <i class="bi bi-exclamation-triangle me-2"></i>Avisos
                                        </h6>
                                        <ul class="mb-0">
                                            @foreach($stats['erros'] as $erro)
                                                <li>{{ $erro }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <!-- Erro -->
                        <div class="card shadow-soft border-danger mb-4 fade-in">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-x-circle-fill me-2"></i>Erro na Transição
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="lead text-danger">{{ $message }}</p>

                                <div class="alert alert-info mt-3">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Nenhum dado foi alterado. Todos os registos permaneceram intactos.
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Próximas Ações -->
                    <div class="card shadow-soft">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-signpost me-2"></i>Próximas Ações
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="{{ route('alunos.index') }}" class="btn btn-outline-primary">
                                    <i class="bi bi-people me-2"></i>Ver Lista de Alunos
                                </a>
                                <a href="{{ route('turmas.index') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-collection me-2"></i>Ver Turmas
                                </a>
                                <a href="{{ route('alunos.transicao.index') }}" class="btn btn-outline-info">
                                    <i class="bi bi-arrow-repeat me-2"></i>Nova Transição
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Informação Adicional -->
                    <div class="alert alert-light mt-4">
                        <h6 class="alert-heading">
                            <i class="bi bi-lightbulb me-2"></i>Informações Importantes
                        </h6>
                        <ul class="mb-0 small">
                            <li>Os alunos transitados foram <strong>duplicados</strong> - os registos originais permanecem no ano de origem</li>
                            <li>Contas de portal dos alunos são <strong>actualizadas</strong> para apontar ao novo registo</li>
                            <li>O mesmo <strong>número de processo</strong> é mantido (único por ano lectivo)</li>
                            <li>O <strong>número de ordem</strong> foi recalculado para cada turma de destino</li>
                            <li>Esta operação foi registada nos logs de auditoria do sistema</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
