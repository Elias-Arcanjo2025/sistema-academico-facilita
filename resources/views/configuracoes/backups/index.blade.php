<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-database me-2"></i>
                Backups da Base de Dados
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
                ['label' => 'Backups']
            ]" />

            <x-tip-card icon="bi-shield-check" variant="warning" title="Cópias de Segurança" id="tip-backups">
                Gira as cópias de segurança da base de dados. Crie backups regulares para proteger os dados. Pode descarregar ou eliminar backups antigos. Recomenda-se manter pelo menos uma cópia recente.
            </x-tip-card>

            <!-- Informações do Agendamento -->
            <x-card title="📅 Agendamento Automático" icon="bi bi-clock-history" class="mb-4">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <strong class="d-block text-muted small">Frequência</strong>
                        <span class="h5 mb-0">{{ $agendamento['frequencia'] }}</span>
                    </div>
                    <div class="col-md-3 mb-2">
                        <strong class="d-block text-muted small">Horário</strong>
                        <span class="h5 mb-0">{{ $agendamento['horario'] }}</span>
                    </div>
                    <div class="col-md-3 mb-2">
                        <strong class="d-block text-muted small">Retenção</strong>
                        <span class="h5 mb-0">{{ $agendamento['retencao'] }}</span>
                    </div>
                    <div class="col-md-3 mb-2">
                        <strong class="d-block text-muted small">Próximo Backup</strong>
                        <span class="h5 mb-0 text-primary">{{ $agendamento['proximo'] }}</span>
                    </div>
                </div>

                @if($ultimoBackupLog)
                    <div class="mt-3 pt-3 border-top">
                        <strong class="text-muted small d-block mb-2">Último Log:</strong>
                        <code class="small">{{ $ultimoBackupLog }}</code>
                    </div>
                @endif
            </x-card>

            <!-- Ação Manual -->
            <div class="row mb-4">
                <div class="col-12">
                    <x-card icon="bi bi-play-circle">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Criar Backup Manual</h6>
                                <p class="text-muted mb-0 small">
                                    Execute um backup imediato da base de dados. O processo pode levar alguns minutos dependendo do tamanho.
                                </p>
                            </div>
                            <form action="{{ route('backups.create') }}" method="POST" class="d-inline" onsubmit="event.preventDefault(); handleCreateBackup(this);">
                                @csrf
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-database-fill-add me-2"></i>
                                    Criar Backup Agora
                                </button>
                            </form>
                        </div>
                    </x-card>
                </div>
            </div>

            <!-- Lista de Backups -->
            @if(count($backups) === 0)
                <x-card title="📦 Backups Existentes (0)" icon="bi bi-archive">
                    <x-alert type="info" :dismissible="false">
                        <i class="bi bi-info-circle me-2"></i>
                        Nenhum backup encontrado. Crie um backup manual ou aguarde a execução automática.
                    </x-alert>
                </x-card>
            @else
                <div class="card shadow-soft fade-in">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-archive me-2"></i>📦 Backups Existentes ({{ count($backups) }})
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th><i class="bi bi-file-earmark-zip me-1"></i> Ficheiro</th>
                                        <th class="text-center"><i class="bi bi-hdd me-1"></i> Tamanho</th>
                                        <th class="text-center"><i class="bi bi-calendar me-1"></i> Data de Criação</th>
                                        <th class="text-center"><i class="bi bi-gear me-1"></i> Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($backups as $backup)
                                        <tr>
                                            <td>
                                                <i class="bi bi-file-earmark-zip-fill text-warning me-2"></i>
                                                <code class="small">{{ $backup['nome'] }}</code>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary">{{ $backup['tamanho_formatado'] }}</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="text-muted">{{ $backup['data_formatada'] }}</span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <!-- Download -->
                                                    <a href="{{ route('backups.download', $backup['nome']) }}"
                                                       class="btn btn-sm btn-info"
                                                       title="Baixar">
                                                        <i class="bi bi-download"></i>
                                                    </a>

                                                    <!-- Eliminar -->
                                                    <form action="{{ route('backups.destroy', $backup['nome']) }}"
                                                          method="POST"
                                                          class="d-inline"
                                                          onsubmit="event.preventDefault(); handleDeleteBackup(this, '{{ $backup['nome'] }}', '{{ $backup['tamanho_formatado'] }}', '{{ $backup['data_formatada'] }}');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                                class="btn btn-sm btn-danger"
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
                    <div class="card-footer bg-white">
                        <!-- Estatísticas -->
                        <div class="row text-center">
                            <div class="col-md-4">
                                <strong class="d-block text-muted small">Total de Backups</strong>
                                <span class="h5 text-primary">{{ count($backups) }}</span>
                            </div>
                            <div class="col-md-4">
                                <strong class="d-block text-muted small">Tamanho Total</strong>
                                <span class="h5 text-success">
                                    {{ number_format(array_sum(array_column($backups, 'tamanho')) / 1024 / 1024, 2) }} MB
                                </span>
                            </div>
                            <div class="col-md-4">
                                <strong class="d-block text-muted small">Backup Mais Recente</strong>
                                <span class="h5 text-info">
                                    {{ count($backups) > 0 ? \Carbon\Carbon::createFromTimestamp($backups[0]['data_criacao'])->diffForHumans() : 'N/A' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Informações Importantes -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <x-card title="⚠️ Importante" icon="bi bi-exclamation-triangle-fill" headerClass="bg-warning">
                        <ul class="mb-0 small">
                            <li>Backups são armazenados em <code>storage/app/backups/</code></li>
                            <li>Backups com mais de <strong>30 dias</strong> são eliminados automaticamente</li>
                            <li>Recomenda-se manter backups em local externo adicional</li>
                            <li>Backups são comprimidos em formato <strong>GZIP</strong></li>
                        </ul>
                    </x-card>
                </div>
                <div class="col-md-6">
                    <x-card title="📖 Como Restaurar" icon="bi bi-info-circle-fill" headerClass="bg-info text-white">
                        <ol class="mb-0 small">
                            <li>Baixe o ficheiro de backup desejado</li>
                            <li>Descomprima o ficheiro <code>.gz</code> se necessário</li>
                            <li>Conecte-se ao MySQL: <code>mysql -u root -p</code></li>
                            <li>Execute: <code>use facilita;</code></li>
                            <li>Restaure: <code>source /caminho/backup.sql;</code></li>
                        </ol>
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
 * Backup Management Confirmation Handlers
 */

// Handle backup creation
async function handleCreateBackup(form) {
    const confirmed = await confirmAction({
        title: 'Criar Backup Manual?',
        html: `
            <div class="text-start">
                <p><i class="bi bi-info-circle text-info"></i> Será criado um backup completo da base de dados.</p>
                <p class="text-muted small mb-0">
                    <i class="bi bi-clock"></i> O processo pode levar alguns minutos dependendo do tamanho da base de dados.
                </p>
            </div>
        `,
        icon: 'question',
        confirmText: '<i class="bi bi-database-fill-add"></i> Sim, criar backup',
        confirmColor: '#0d6efd'
    });

    if (confirmed) {
        showLoading('Criando backup...');
        form.submit();
    }
}

// Handle backup deletion with DOUBLE CONFIRMATION (critical operation)
async function handleDeleteBackup(form, backupName, backupSize, backupDate) {
    // FIRST CONFIRMATION: Show backup details
    const firstConfirm = await Swal.fire({
        title: '⚠️ Eliminar Backup?',
        html: `
            <div class="text-start">
                <p class="fw-bold text-danger mb-3">
                    <i class="bi bi-exclamation-triangle-fill"></i> Esta ação NÃO pode ser desfeita!
                </p>
                <div class="bg-light p-3 rounded mb-3">
                    <p class="mb-2"><i class="bi bi-file-earmark-zip-fill text-warning"></i> <strong>Ficheiro:</strong></p>
                    <code class="d-block mb-3">${backupName}</code>
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted">Tamanho:</small><br>
                            <span class="badge bg-secondary">${backupSize}</span>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Data:</small><br>
                            <span class="text-muted">${backupDate}</span>
                        </div>
                    </div>
                </div>
                <p class="text-danger small mb-0">
                    <i class="bi bi-shield-exclamation"></i> Certifique-se de que não precisará deste backup no futuro.
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

    // SECOND CONFIRMATION: Type confirmation (extra safety layer)
    const secondConfirm = await Swal.fire({
        title: '🔒 Confirmação Final',
        html: `
            <div class="text-start">
                <p class="mb-3">Para confirmar a eliminação definitiva, digite:</p>
                <div class="text-center mb-3">
                    <code class="fs-5 bg-danger text-white px-3 py-2 rounded">ELIMINAR</code>
                </div>
            </div>
        `,
        input: 'text',
        inputPlaceholder: 'Digite ELIMINAR (em maiúsculas)',
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
            if (value !== 'ELIMINAR') {
                return 'Deve digitar exatamente "ELIMINAR" para confirmar!';
            }
        }
    });

    if (secondConfirm.isConfirmed) {
        showLoading('Eliminando backup...');
        form.submit();
    }
}
</script>
@endpush
@endonce
