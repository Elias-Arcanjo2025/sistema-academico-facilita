<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Detalhes do Ficheiro de Pauta</h2>
            <a href="{{ route('pauta-arquivos.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row">
                <!-- Coluna Principal -->
                <div class="col-lg-8">
                    <!-- Informações Gerais -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-info-circle"></i> Informações Gerais
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold text-muted">Ficheiro</label>
                                    <div>
                                        <i class="bi bi-file-earmark-excel text-success fs-4"></i>
                                        <span class="fs-5">{{ $pautaArquivo->nome_original }}</span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted">Curso</label>
                                    <div>{{ $pautaArquivo->curso->nome }}</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted">Turma</label>
                                    <div>
                                        @if($pautaArquivo->turma)
                                            {{ $pautaArquivo->turma->nome }}
                                            @if($pautaArquivo->turma->classe)
                                                <span class="text-muted">({{ $pautaArquivo->turma->classe->nome }})</span>
                                            @endif
                                        @elseif($pautaArquivo->turma_manual)
                                            {{ $pautaArquivo->turma_manual }}
                                            <span class="badge bg-secondary ms-1">Manual</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted">Sala</label>
                                    <div>
                                        @if($pautaArquivo->sala)
                                            {{ $pautaArquivo->sala->nome }}
                                        @elseif($pautaArquivo->sala_manual)
                                            {{ $pautaArquivo->sala_manual }}
                                            <span class="badge bg-secondary ms-1">Manual</span>
                                        @else
                                            -
                                        @endif
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted">Ano Lectivo</label>
                                    <div>{{ $pautaArquivo->ano_lectivo }}</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted">Trimestre</label>
                                    <div>
                                        @if($pautaArquivo->trimestre === 0)
                                            <span class="badge bg-info">Anual</span>
                                        @elseif($pautaArquivo->trimestre === 1)
                                            <span class="badge bg-primary">1.º Trimestre</span>
                                        @elseif($pautaArquivo->trimestre === 2)
                                            <span class="badge bg-warning">2.º Trimestre</span>
                                        @elseif($pautaArquivo->trimestre === 3)
                                            <span class="badge bg-success">3.º Trimestre</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted">Tipo de Pauta</label>
                                    <div>{{ $pautaArquivo->nome_tipo }}</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted">Disciplina</label>
                                    <div>
                                        @if($pautaArquivo->disciplina)
                                            {{ $pautaArquivo->disciplina->nome }}
                                        @elseif($pautaArquivo->disciplina_manual)
                                            {{ $pautaArquivo->disciplina_manual }}
                                            <span class="badge bg-secondary ms-1">Manual</span>
                                        @else
                                            Turma Completa
                                        @endif
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted">Versão</label>
                                    <div>
                                        <span class="badge bg-secondary fs-6">{{ $pautaArquivo->versao_formatada }}</span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted">Origem</label>
                                    <div>
                                        @if($pautaArquivo->origem === 'sistema')
                                            <span class="badge bg-primary">{{ $pautaArquivo->nome_origem }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ $pautaArquivo->nome_origem }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Descrição -->
                    @if($pautaArquivo->descricao)
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-chat-left-text"></i> Descrição
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-0">{{ $pautaArquivo->descricao }}</p>
                            </div>
                        </div>
                    @endif

                    <!-- Metadata Técnica -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-gear"></i> Metadata Técnica
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted">Tamanho</label>
                                    <div>{{ $pautaArquivo->tamanho_formatado }}</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted">Tipo MIME</label>
                                    <div><code>{{ $pautaArquivo->mime_type }}</code></div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold text-muted">Hash SHA-256</label>
                                    <div>
                                        <code class="small">{{ Str::limit($pautaArquivo->hash_arquivo, 40) }}</code>
                                        <button class="btn btn-sm btn-outline-secondary ms-2"
                                                onclick="copiarHash()"
                                                title="Copiar hash completo">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                    <input type="hidden" id="hashCompleto" value="{{ $pautaArquivo->hash_arquivo }}">
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold text-muted">Caminho de Armazenamento</label>
                                    <div><code class="small">{{ $pautaArquivo->caminho_arquivo }}</code></div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold text-muted">Estado do Ficheiro</label>
                                    <div>
                                        @if($pautaArquivo->existe)
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle"></i> Ficheiro existe no armazenamento
                                            </span>
                                        @else
                                            <span class="badge bg-danger">
                                                <i class="bi bi-exclamation-triangle"></i> Ficheiro não encontrado
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Coluna Lateral -->
                <div class="col-lg-4">
                    <!-- Upload Info -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-cloud-upload"></i> Informações de Upload
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted">Enviado por</label>
                                <div>{{ $pautaArquivo->uploadedBy->name }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted">Data de Envio</label>
                                <div>
                                    {{ $pautaArquivo->created_at->format('d/m/Y') }}<br>
                                    <small class="text-muted">{{ $pautaArquivo->created_at->format('H:i:s') }}</small>
                                </div>
                            </div>

                            @if($pautaArquivo->created_at != $pautaArquivo->updated_at)
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-muted">Última Actualização</label>
                                    <div>
                                        {{ $pautaArquivo->updated_at->format('d/m/Y') }}<br>
                                        <small class="text-muted">{{ $pautaArquivo->updated_at->format('H:i:s') }}</small>
                                    </div>
                                </div>
                            @endif

                            <div>
                                <label class="form-label fw-bold text-muted">Status</label>
                                <div>
                                    @if($pautaArquivo->activo)
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle"></i> Activo
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-x-circle"></i> Inactivo
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Acções -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-lightning"></i> Acções
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                @can('download', $pautaArquivo)
                                    @if($pautaArquivo->existe)
                                        <a href="{{ route('pauta-arquivos.download', $pautaArquivo) }}"
                                           class="btn btn-success">
                                            <i class="bi bi-download"></i> Download Ficheiro
                                        </a>
                                    @else
                                        <button class="btn btn-secondary" disabled>
                                            <i class="bi bi-exclamation-triangle"></i> Ficheiro Indisponível
                                        </button>
                                    @endif
                                @endcan

                                @can('update', $pautaArquivo)
                                    <a href="{{ route('pauta-arquivos.edit', $pautaArquivo) }}"
                                       class="btn btn-warning">
                                        <i class="bi bi-pencil"></i> Editar Metadata
                                    </a>
                                @endcan

                                @can('delete', $pautaArquivo)
                                    <form action="{{ route('pauta-arquivos.destroy', $pautaArquivo) }}"
                                          method="POST"
                                          id="deactivatePautaForm"
                                          onsubmit="event.preventDefault(); handleDeactivatePauta(this);">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger w-100">
                                            <i class="bi bi-trash"></i> Desactivar Ficheiro
                                        </button>
                                    </form>
                                @endcan

                                @can('forceDelete', $pautaArquivo)
                                    <form action="{{ route('pauta-arquivos.force-delete', $pautaArquivo) }}"
                                          method="POST"
                                          id="forceDeletePautaForm"
                                          onsubmit="event.preventDefault(); handleForceDeletePauta(this);">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger w-100">
                                            <i class="bi bi-trash-fill"></i> Eliminar Permanentemente
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    </div>

                    <!-- Histórico de Versões -->
                    @if($versoes->count() > 1)
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-clock-history"></i> Histórico de Versões
                                    <span class="badge bg-secondary">{{ $versoes->count() }}</span>
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    @foreach($versoes as $versao)
                                        <div class="list-group-item {{ $versao->id === $pautaArquivo->id ? 'active' : '' }}">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <span class="badge {{ $versao->id === $pautaArquivo->id ? 'bg-light text-dark' : 'bg-secondary' }}">
                                                            {{ $versao->versao_formatada }}
                                                        </span>
                                                        @if($versao->id === $pautaArquivo->id)
                                                            <span class="badge bg-light text-dark">Actual</span>
                                                        @endif
                                                    </h6>
                                                    <p class="mb-1 small {{ $versao->id === $pautaArquivo->id ? 'text-light' : 'text-muted' }}">
                                                        {{ $versao->created_at->format('d/m/Y H:i') }}
                                                    </p>
                                                    <p class="mb-0 small {{ $versao->id === $pautaArquivo->id ? 'text-light' : 'text-muted' }}">
                                                        {{ $versao->uploadedBy->name }}
                                                    </p>
                                                </div>
                                                <div>
                                                    @if($versao->id !== $pautaArquivo->id)
                                                        <div class="btn-group-vertical btn-group-sm">
                                                            @can('view', $versao)
                                                                <a href="{{ route('pauta-arquivos.show', $versao) }}"
                                                                   class="btn btn-outline-primary btn-sm"
                                                                   title="Ver">
                                                                    <i class="bi bi-eye"></i>
                                                                </a>
                                                            @endcan
                                                            @can('download', $versao)
                                                                @if($versao->existe)
                                                                    <a href="{{ route('pauta-arquivos.download', $versao) }}"
                                                                       class="btn btn-outline-success btn-sm"
                                                                       title="Download">
                                                                        <i class="bi bi-download"></i>
                                                                    </a>
                                                                @endif
                                                            @endcan
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Handler para confirmação de desactivação de pauta
        async function handleDeactivatePauta(form) {
            const confirmed = await confirmAction({
                title: 'Desactivar Ficheiro de Pauta?',
                html: '<p>Tem a certeza que deseja desactivar este ficheiro?</p><p class="text-muted"><small>O ficheiro ficará inativo mas não será eliminado.</small></p>',
                icon: 'warning',
                confirmText: '<i class="bi bi-trash"></i> Sim, desactivar'
            });

            if (confirmed) {
                showLoading('Desactivando ficheiro...');
                form.submit();
            }
        }

        // Handler para confirmação de eliminação permanente
        async function handleForceDeletePauta(form) {
            const confirmed = await confirmAction({
                title: 'ATENÇÃO: Acção Irreversível!',
                html: '<p class="text-danger"><strong>O ficheiro será PERMANENTEMENTE eliminado.</strong></p><p>Esta ação NÃO pode ser desfeita. Confirma?</p>',
                icon: 'error',
                dangerMode: true,
                confirmText: '<i class="bi bi-trash-fill"></i> Sim, eliminar permanentemente'
            });

            if (confirmed) {
                showLoading('Eliminando ficheiro...');
                form.submit();
            }
        }

        function copiarHash() {
            const hash = document.getElementById('hashCompleto').value;
            navigator.clipboard.writeText(hash).then(function() {
                showToast('success', 'Hash copiado para a área de transferência!');
            }, function(err) {
                showNotification('error', 'Erro ao copiar hash: ' + err, 'Erro');
            });
        }
    </script>
    @endpush
</x-app-layout>
