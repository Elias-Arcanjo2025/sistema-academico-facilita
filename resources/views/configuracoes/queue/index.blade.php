<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-list-task me-2"></i>
                Sistema de Filas
            </h2>
            <a href="{{ route('configuracoes.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Configurações', 'url' => route('configuracoes.index')],
                ['label' => 'Filas']
            ]" />

            <x-tip-card icon="bi-cpu" variant="info" title="Filas de Processamento" id="tip-filas">
                Monitorize os trabalhos em fila (geração de PDFs, boletins em lote, etc.). Os trabalhos falhados podem ser repetidos ou eliminados. Se houver muitos trabalhos pendentes, verifique se o processador de filas está activo.
            </x-tip-card>

            <!-- Métricas Principais -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h2 class="mb-0">{{ $totalPendentes }}</h2>
                            <small>Jobs Pendentes</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-{{ $totalFalhados > 0 ? 'danger' : 'success' }} text-white">
                        <div class="card-body text-center">
                            <h2 class="mb-0">{{ $totalFalhados }}</h2>
                            <small>Jobs Falhados</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h2 class="mb-0">{{ $jobsRecentes->count() }}</h2>
                            <small>Processados (24h)</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-secondary text-white">
                        <div class="card-body text-center">
                            <h2 class="mb-0">{{ $jobsPendentes->count() }}</h2>
                            <small>Filas Ativas</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Jobs Pendentes por Fila -->
            @if($jobsPendentes->isEmpty())
                <x-card title="📋 Jobs Pendentes por Fila" icon="bi bi-hourglass-split" class="mb-4">
                    <x-alert type="success" :dismissible="false">
                        <i class="bi bi-check-circle me-2"></i>
                        Não há jobs pendentes no momento. Todas as filas estão vazias!
                    </x-alert>
                </x-card>
            @else
                <x-data-table title="📋 Jobs Pendentes por Fila" icon="bi-hourglass-split" class="mb-4">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><i class="bi bi-list-ul me-1"></i> Fila</th>
                                <th class="text-center"><i class="bi bi-hash me-1"></i> Total de Jobs</th>
                                <th class="text-center"><i class="bi bi-bar-chart me-1"></i> Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($jobsPendentes as $fila)
                                <tr>
                                    <td>
                                        <span class="badge bg-primary">{{ $fila->queue }}</span>
                                    </td>
                                    <td class="text-center">
                                        <strong>{{ $fila->total }}</strong> jobs
                                    </td>
                                    <td class="text-center">
                                        @if($fila->total > 50)
                                            <span class="badge bg-danger">Alta Carga</span>
                                        @elseif($fila->total > 10)
                                            <span class="badge bg-warning">Carga Moderada</span>
                                        @else
                                            <span class="badge bg-success">Normal</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-data-table>
            @endif

            <!-- Jobs Falhados -->
            @if($jobsFalhados->isEmpty())
                <x-card title="⚠️ Jobs Falhados (Últimos 20)" icon="bi bi-exclamation-triangle" class="mb-4">
                    <x-alert type="success" :dismissible="false">
                        <i class="bi bi-check-circle me-2"></i>
                        Nenhum job falhado encontrado! Excelente!
                    </x-alert>
                </x-card>
            @else
                <div class="card shadow-soft fade-in mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>⚠️ Jobs Falhados (Últimos 20)
                        </h5>
                        <span class="text-muted">Total de jobs falhados: <strong class="text-danger">{{ $totalFalhados }}</strong></span>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-end mb-3">
                            <div class="btn-group">
                                <form action="{{ route('queue.retry-all') }}" method="POST" class="d-inline" onsubmit="event.preventDefault(); handleRetryAll(this, {{ $totalFalhados }});">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-sm">
                                        <i class="bi bi-arrow-repeat me-1"></i>
                                        Retentar Todos
                                    </button>
                                </form>
                                <form action="{{ route('queue.clear-failed') }}" method="POST" class="d-inline" onsubmit="event.preventDefault(); handleClearAllFailed(this, {{ $totalFalhados }});">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="bi bi-trash me-1"></i>
                                        Limpar Todos
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Job</th>
                                        <th class="text-center">Fila</th>
                                        <th class="text-center">Falhou em</th>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($jobsFalhados as $job)
                                        <tr>
                                            <td>
                                                <code class="small">{{ Str::limit($job['job_class'], 50) }}</code>
                                                @if($job['exception'])
                                                    <button type="button"
                                                            class="btn btn-sm btn-link p-0 ms-2"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#errorModal{{ $job['id'] }}">
                                                        <i class="bi bi-info-circle text-danger"></i>
                                                    </button>

                                                    <!-- Modal com erro -->
                                                    <div class="modal fade" id="errorModal{{ $job['id'] }}" tabindex="-1">
                                                        <div class="modal-dialog modal-lg">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-danger text-white">
                                                                    <h5 class="modal-title">Erro do Job</h5>
                                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <pre class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto;">{{ Str::limit($job['exception'], 2000) }}</pre>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary">{{ $job['queue'] }}</span>
                                            </td>
                                            <td class="text-center text-muted small">
                                                {{ \Carbon\Carbon::parse($job['failed_at'])->diffForHumans() }}
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <form action="{{ route('queue.retry-failed', $job['uuid']) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-warning btn-sm" title="Retentar">
                                                            <i class="bi bi-arrow-repeat"></i>
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('queue.delete-failed', $job['uuid']) }}" method="POST" class="d-inline" onsubmit="event.preventDefault(); handleDeleteJob(this, '{{ Str::limit($job['job_class'], 30) }}');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                                class="btn btn-danger btn-sm"
                                                                title="Eliminar">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Estatísticas por Tipo -->
            @if($estatisticasPorTipo->isNotEmpty())
            <x-card title="📊 Jobs Processados (Última Semana)" icon="bi bi-graph-up" class="mb-4">
                <div class="row text-center">
                    @foreach($estatisticasPorTipo as $tipo => $count)
                        <div class="col-md-4 mb-2">
                            <strong class="d-block text-muted">{{ $tipo }}</strong>
                            <span class="h4 text-primary">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </x-card>
            @endif

            <!-- Jobs Recentes (24h) -->
            @if($jobsRecentes->isEmpty())
                <x-card title="🕐 Jobs Processados (Últimas 24h)" icon="bi bi-clock-history" class="mb-4">
                    <x-alert type="info" :dismissible="false">
                        Nenhum job foi processado nas últimas 24 horas.
                    </x-alert>
                </x-card>
            @else
                <x-data-table title="🕐 Jobs Processados (Últimas 24h)" icon="bi-clock-history" class="mb-4">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Descrição</th>
                                <th class="text-center">Utilizador</th>
                                <th class="text-center">Data/Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($jobsRecentes as $job)
                                <tr>
                                    <td>
                                        <i class="bi bi-check-circle text-success me-2"></i>
                                        {{ $job->description }}
                                    </td>
                                    <td class="text-center">
                                        {{ $job->causer?->name ?? 'Sistema' }}
                                    </td>
                                    <td class="text-center text-muted small">
                                        {{ $job->created_at->format('d/m/Y H:i:s') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-data-table>
            @endif

            <!-- Instruções -->
            <div class="row">
                <div class="col-md-6">
                    <x-card title="📖 Como Executar o Worker" icon="bi bi-terminal" headerClass="bg-info text-white">
                        <p class="mb-2">Para processar jobs em segundo plano, execute:</p>
                        <pre class="bg-dark text-white p-3 rounded"><code>php artisan queue:work --queue=default,pdf,pauta</code></pre>

                        <hr>

                        <p class="mb-2"><strong>Para desenvolvimento</strong> (reinicia automaticamente):</p>
                        <pre class="bg-dark text-white p-3 rounded"><code>php artisan queue:listen</code></pre>

                        <hr>

                        <p class="mb-2"><strong>Para produção</strong> (com Supervisor):</p>
                        <p class="small text-muted mb-0">Configure um processo Supervisor para garantir que o worker esteja sempre em execução.</p>
                    </x-card>
                </div>
                <div class="col-md-6">
                    <x-card title="⚙️ Filas Disponíveis" icon="bi bi-list-check" headerClass="bg-success text-white">
                        <ul class="mb-0">
                            <li class="mb-2">
                                <span class="badge bg-primary">default</span> - Jobs gerais (notificações)
                            </li>
                            <li class="mb-2">
                                <span class="badge bg-warning">pdf</span> - Geração de PDFs (boletins)
                            </li>
                            <li class="mb-2">
                                <span class="badge bg-info">pauta</span> - Cálculos de pautas e médias
                            </li>
                        </ul>

                        <hr>

                        <p class="small text-muted mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Dica:</strong> Filas separadas permitem priorizar diferentes tipos de jobs.
                        </p>
                    </x-card>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

@once
@push('scripts')
<script>
/**
 * Queue Management Confirmation Handlers
 */

// Handle retry all failed jobs
async function handleRetryAll(form, totalFailed) {
    const confirmed = await confirmAction({
        title: 'Retentar Todos os Jobs?',
        html: `
            <div class="text-start">
                <p class="mb-3">Serão retentados <strong class="text-warning">${totalFailed} jobs falhados</strong>.</p>
                <p class="text-muted small mb-0">
                    <i class="bi bi-info-circle"></i> Os jobs serão adicionados novamente à fila para processamento.
                </p>
            </div>
        `,
        icon: 'question',
        confirmText: '<i class="bi bi-arrow-repeat"></i> Sim, retentar todos',
        confirmColor: '#ffc107'
    });

    if (confirmed) {
        showLoading('Retentando jobs...');
        form.submit();
    }
}

// Handle clear ALL failed jobs with DOUBLE CONFIRMATION (critical operation)
async function handleClearAllFailed(form, totalFailed) {
    // FIRST CONFIRMATION
    const firstConfirm = await Swal.fire({
        title: '⚠️ Limpar TODOS os Jobs Falhados?',
        html: `
            <div class="text-start">
                <p class="fw-bold text-danger mb-3">
                    <i class="bi bi-exclamation-triangle-fill"></i> ATENÇÃO: Esta ação NÃO pode ser desfeita!
                </p>
                <div class="bg-danger bg-opacity-10 p-3 rounded mb-3">
                    <p class="mb-2"><strong>Serão eliminados permanentemente:</strong></p>
                    <ul class="mb-0">
                        <li><strong class="text-danger">${totalFailed} jobs falhados</strong></li>
                        <li>Logs de erro completos</li>
                        <li>Stack traces de debug</li>
                    </ul>
                </div>
                <p class="text-danger small mb-0">
                    <i class="bi bi-shield-exclamation"></i> Certifique-se de que não precisa investigar estes erros antes de prosseguir!
                </p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Continuar <i class="bi bi-arrow-right"></i>',
        cancelButtonText: '<i class="bi bi-x-lg"></i> Cancelar',
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        customClass: {
            confirmButton: 'btn btn-warning',
            cancelButton: 'btn btn-secondary'
        },
        buttonsStyling: false
    });

    if (!firstConfirm.isConfirmed) {
        return;
    }

    // SECOND CONFIRMATION: Type confirmation (extra safety)
    const secondConfirm = await Swal.fire({
        title: '🔒 Confirmação Final',
        html: `
            <div class="text-start">
                <p class="mb-3">Para confirmar a eliminação de <strong>${totalFailed} jobs falhados</strong>, digite:</p>
                <div class="text-center mb-3">
                    <code class="fs-5 bg-danger text-white px-3 py-2 rounded">LIMPAR</code>
                </div>
            </div>
        `,
        input: 'text',
        inputPlaceholder: 'Digite LIMPAR (em maiúsculas)',
        icon: 'error',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-trash-fill"></i> Eliminar Permanentemente',
        cancelButtonText: '<i class="bi bi-x-lg"></i> Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-secondary'
        },
        buttonsStyling: false,
        inputValidator: (value) => {
            if (value !== 'LIMPAR') {
                return 'Deve digitar exatamente "LIMPAR" para confirmar!';
            }
        }
    });

    if (secondConfirm.isConfirmed) {
        showLoading('Limpando jobs falhados...');
        form.submit();
    }
}

// Handle delete single failed job
async function handleDeleteJob(form, jobClass) {
    const confirmed = await confirmDelete(`o job "${jobClass}"`);

    if (confirmed) {
        showLoading('Eliminando job...');
        form.submit();
    }
}
</script>
@endpush
@endonce
