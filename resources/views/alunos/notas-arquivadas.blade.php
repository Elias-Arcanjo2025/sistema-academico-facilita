<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-archive me-2"></i>Notas Arquivadas
            </h2>
            <a href="{{ route('alunos.show', $aluno) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar para Aluno
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container-fluid">
            <x-breadcrumb :items="[
                ['label' => 'Alunos', 'url' => route('alunos.index')],
                ['label' => $aluno->nome, 'url' => route('alunos.show', $aluno)],
                ['label' => 'Notas Arquivadas']
            ]" />

            {{-- Info Card do Aluno --}}
            <div class="card mb-4 shadow-soft">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-person me-2"></i>Informações do Aluno</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Nome:</strong> {{ $aluno->nome }}</p>
                            <p class="mb-1"><strong>N.º Processo:</strong> {{ $aluno->numero_processo }}</p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Turma Actual:</strong> <span class="badge bg-info">{{ $aluno->turma->nome }}</span></p>
                            <p class="mb-1"><strong>Curso:</strong> {{ $aluno->turma->curso->nome }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Estatísticas --}}
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card shadow-soft border-warning">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-archive-fill text-warning" style="font-size: 2rem;"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5 class="mb-0">{{ $notasArquivadas->count() }}</h5>
                                    <p class="text-muted mb-0 small">Notas Arquivadas</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-soft border-info">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-calendar3 text-info" style="font-size: 2rem;"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5 class="mb-0">{{ $notasAgrupadas->count() }}</h5>
                                    <p class="text-muted mb-0 small">Períodos Afetados</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-soft border-success">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-arrow-counterclockwise text-success" style="font-size: 2rem;"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5 class="mb-0">100%</h5>
                                    <p class="text-muted mb-0 small">Recuperáveis</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if($notasArquivadas->isEmpty())
                {{-- Nenhuma nota arquivada --}}
                <x-empty-state
                    icon="bi-archive"
                    message="Este aluno não possui notas arquivadas"
                    description="Notas arquivadas aparecem aqui quando são removidas por transferências entre turmas."
                />
            @else
                {{-- Tabela de Notas Arquivadas --}}
                @foreach($notasAgrupadas as $periodo => $notas)
                    <div class="card shadow-soft mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">
                                <i class="bi bi-calendar3 me-2"></i>{{ $periodo }}
                                <span class="badge bg-warning ms-2">{{ $notas->count() }} nota(s)</span>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Disciplina</th>
                                            <th>Tipo</th>
                                            <th>Valor</th>
                                            <th>Turma</th>
                                            <th>Data Arquivamento</th>
                                            <th>Arquivado Por</th>
                                            <th>Motivo</th>
                                            <th class="text-end">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($notas as $nota)
                                            <tr>
                                                <td>
                                                    <strong>{{ $nota->disciplina->nome ?? 'N/A' }}</strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">{{ $nota->tipo }}</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-{{ $nota->getBadgeColor() }}">
                                                        {{ number_format($nota->valor, 0) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">{{ $nota->turma->nome ?? 'N/A' }}</small>
                                                </td>
                                                <td>
                                                    <small>{{ $nota->archived_at->format('d/m/Y H:i') }}</small>
                                                </td>
                                                <td>
                                                    <small>{{ $nota->archivedBy->name ?? 'Sistema' }}</small>
                                                </td>
                                                <td>
                                                    <small class="text-muted" style="max-width: 200px; display: inline-block;" title="{{ $nota->archived_reason }}">
                                                        {{ Str::limit($nota->archived_reason, 50) }}
                                                    </small>
                                                </td>
                                                <td class="text-end">
                                                    @can('notas.editar')
                                                        <form action="{{ route('notas.restore', $nota) }}"
                                                              method="POST"
                                                              class="d-inline form-restore-nota"
                                                              data-disciplina="{{ $nota->disciplina->nome ?? 'N/A' }}"
                                                              data-tipo="{{ $nota->tipo }}"
                                                              data-valor="{{ number_format($nota->valor, 0) }}">
                                                            @csrf
                                                            <button type="submit"
                                                                    class="btn btn-sm btn-success"
                                                                    title="Restaurar nota">
                                                                <i class="bi bi-arrow-counterclockwise"></i>
                                                            </button>
                                                        </form>
                                                    @endcan

                                                    @if(auth()->user()->hasRole('admin_full'))
                                                        <form action="{{ route('notas.force-delete', $nota) }}"
                                                              method="POST"
                                                              class="d-inline form-permanent-delete"
                                                              data-disciplina="{{ $nota->disciplina->nome ?? 'N/A' }}"
                                                              data-tipo="{{ $nota->tipo }}"
                                                              data-valor="{{ number_format($nota->valor, 0) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                    class="btn btn-sm btn-danger"
                                                                    title="Eliminar permanentemente">
                                                                <i class="bi bi-trash-fill"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- Informações de Segurança --}}
                <div class="card shadow-soft border-info">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-info-circle me-2"></i>Sobre Notas Arquivadas
                        </h6>
                        <ul class="mb-0">
                            <li>Notas arquivadas são <strong>reversíveis</strong> - podem ser restauradas por administradores</li>
                            <li>Todas as ações são <strong>registadas na auditoria</strong> do sistema</li>
                            <li>Apenas <strong>Admin Full</strong> pode eliminar permanentemente notas arquivadas</li>
                            <li>Restaurar uma nota a torna disponível novamente nas pautas do aluno</li>
                        </ul>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handler para restaurar nota
            const formsRestoreNota = document.querySelectorAll('.form-restore-nota');

            formsRestoreNota.forEach(form => {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    const disciplina = this.dataset.disciplina;
                    const tipo = this.dataset.tipo;
                    const valor = this.dataset.valor;

                    const confirmed = await confirmAction({
                        title: 'Restaurar Nota Arquivada',
                        html: `
                            <p>Deseja restaurar esta nota?</p>
                            <div class="text-start mt-3">
                                <strong>Disciplina:</strong> ${disciplina}<br>
                                <strong>Tipo:</strong> ${tipo}<br>
                                <strong>Valor:</strong> ${valor}
                            </div>
                        `,
                        confirmButtonText: 'Sim, restaurar',
                        cancelButtonText: 'Cancelar',
                        icon: 'question'
                    });

                    if (confirmed) {
                        this.submit();
                    }
                });
            });

            // Handler CRÍTICO para delete permanente
            const formsPermanentDelete = document.querySelectorAll('.form-permanent-delete');

            formsPermanentDelete.forEach(form => {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    const disciplina = this.dataset.disciplina;
                    const tipo = this.dataset.tipo;
                    const valor = this.dataset.valor;

                    const confirmed = await confirmAction({
                        title: '⚠️ ATENÇÃO: Ação IRREVERSÍVEL!',
                        html: `
                            <div class="alert alert-danger text-start mb-3">
                                <h6 class="alert-heading">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    Esta ação é PERMANENTE e NÃO PODE SER DESFEITA!
                                </h6>
                                <ul class="mb-0 mt-2">
                                    <li>A nota será <strong>eliminada permanentemente</strong> do banco de dados</li>
                                    <li><strong>Não será possível recuperá-la</strong> posteriormente</li>
                                    <li>Esta ação será <strong>registada na auditoria</strong></li>
                                </ul>
                            </div>
                            <div class="text-start">
                                <strong>Disciplina:</strong> ${disciplina}<br>
                                <strong>Tipo:</strong> ${tipo}<br>
                                <strong>Valor:</strong> ${valor}
                            </div>
                            <p class="mt-3 mb-0"><strong>Tem CERTEZA ABSOLUTA que deseja eliminar permanentemente?</strong></p>
                        `,
                        confirmButtonText: 'Sim, eliminar permanentemente',
                        cancelButtonText: 'Cancelar',
                        icon: 'warning',
                        dangerMode: true
                    });

                    if (confirmed) {
                        this.submit();
                    }
                });
            });
        });
    </script>
    @endpush
</x-app-layout>
