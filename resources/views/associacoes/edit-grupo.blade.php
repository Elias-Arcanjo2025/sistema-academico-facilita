<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-pencil me-2 text-primary"></i>Editar Grupo de Associações
            </h2>
            <div class="d-flex gap-2">
                @can('delete', $associacoes->first())
                    <form action="{{ route('associacoes.delete-grupo') }}" method="POST" class="d-inline"
                          id="deleteGrupoForm"
                          onsubmit="event.preventDefault(); handleDeleteGrupo(this);">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="professor_id" value="{{ $professor->id }}">
                        <input type="hidden" name="disciplina_id" value="{{ $disciplina->id }}">
                        <input type="hidden" name="ano_lectivo" value="{{ $anoLectivo }}">
                        <input type="hidden" name="ano_lectivo_id" value="{{ $anoLectivoId }}">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>Eliminar Grupo
                        </button>
                    </form>
                @endcan
                <a href="{{ route('associacoes.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <!-- Info Card -->
                    <x-info-card type="info" title="Substituir professor ou ajustar turmas" class="mb-4">
                        <strong>Quando um professor sai:</strong>
                        <ol class="mb-2 mt-2">
                            <li>Desactive as turmas deste grupo (switch <em>Inactivo</em>) <strong>ou</strong> remova-as da selecção — se houver notas, ficam desactivadas automaticamente.</li>
                            <li>Vá a <strong>Nova Associação</strong> e associe o novo professor à mesma disciplina/turma.</li>
                        </ol>
                        <span class="text-muted">As notas lançadas pelo professor anterior permanecem no sistema com o nome dele.</span>
                    </x-info-card>

                    <div class="card shadow-soft fade-in">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informações do Grupo (Não Editável)</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-muted">Professor</label>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-2">
                                            {{ strtoupper(substr($professor->name, 0, 1)) }}
                                        </div>
                                        <strong>{{ $professor->name }}</strong>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-muted">Disciplina</label>
                                    <div>
                                        <span class="badge bg-info" style="font-size: 0.9rem;">{{ $disciplina->nome }}</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-muted">Ano Lectivo</label>
                                    <div>
                                        <span class="badge bg-light text-dark border">{{ $anoLectivo }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form de Edição -->
                    <div class="card shadow-soft fade-in mt-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-people me-2"></i>Gestão de Turmas</h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('associacoes.update-grupo') }}" method="POST" id="editForm">
                                @csrf
                                @method('PUT')

                                <input type="hidden" name="professor_id" value="{{ $professor->id }}">
                                <input type="hidden" name="disciplina_id" value="{{ $disciplina->id }}">
                                <input type="hidden" name="ano_lectivo" value="{{ $anoLectivo }}">
                                <input type="hidden" name="ano_lectivo_id" value="{{ $anoLectivoId }}">

                                @if($turmasDisponiveis->isEmpty())
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        Nenhuma turma disponível para esta disciplina no ano lectivo actual.
                                    </div>
                                @else
                                    <div class="mb-3">
                                        <label for="turma_ids" class="form-label">
                                            <i class="bi bi-people me-1"></i>Turmas <span class="text-danger">*</span>
                                            <x-help-icon
                                                type="popover"
                                                title="Selecção de Turmas"
                                                content="Selecione as turmas associadas. Ao remover uma turma com notas, a associação é desactivada (não eliminada) para preservar o histórico."
                                            />
                                        </label>
                                        <select class="form-select select2-multiple @error('turma_ids') is-invalid @enderror"
                                                id="turma_ids" name="turma_ids[]" multiple required>
                                            @foreach($turmasDisponiveis as $turma)
                                                @php
                                                    $associacaoExistente = $associacoes->firstWhere('turma_id', $turma->id);
                                                    $estaSelecionada = $associacaoExistente !== null;
                                                @endphp
                                                <option value="{{ $turma->id }}"
                                                        data-associacao-id="{{ $associacaoExistente->id ?? '' }}"
                                                        data-activo="{{ $associacaoExistente ? ($associacaoExistente->activo ? '1' : '0') : '1' }}"
                                                        data-notas="{{ $notasPorTurma[$turma->id] ?? 0 }}"
                                                        {{ $estaSelecionada ? 'selected' : '' }}>
                                                    {{ $turma->nome }} - {{ $turma->curso->nome }}
                                                    @if(($notasPorTurma[$turma->id] ?? 0) > 0)
                                                        ({{ $notasPorTurma[$turma->id] }} notas)
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('turma_ids')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Container para switches de ativo/inativo -->
                                    <div id="turmas-status-container" class="mb-3">
                                        <label class="form-label fw-bold">
                                            <i class="bi bi-toggle-on me-1"></i>Estado das Associações
                                        </label>
                                        <div id="turmas-status-list" class="border rounded p-3">
                                            <p class="text-muted text-center mb-0">Selecione as turmas acima para definir o estado</p>
                                        </div>
                                    </div>

                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <strong>Instruções:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>Use o switch para activar ou desactivar cada turma</li>
                                            <li>Associações <strong>inactivas</strong> não aparecem no lançamento de notas, mas mantêm o histórico</li>
                                            <li>Ao remover uma turma da lista: sem notas → elimina; com notas → desactiva automaticamente</li>
                                            <li>Para trocar de professor: desactive aqui e crie <strong>Nova Associação</strong> para o novo docente</li>
                                        </ul>
                                    </div>

                                    <hr class="my-4">

                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('associacoes.index') }}" class="btn btn-outline-secondary">
                                            <i class="bi bi-x-lg me-1"></i>Cancelar
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-lg me-1"></i>Guardar Alterações
                                        </button>
                                    </div>
                                @endif
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Handler para confirmação de exclusão do grupo
        async function handleDeleteGrupo(form) {
            const confirmed = await confirmAction({
                title: 'Eliminar Grupo de Associações?',
                html: '<p>Tem a certeza que deseja eliminar <strong>todas as associações deste grupo</strong>?</p><p class="text-danger"><small>Esta ação não pode ser desfeita.</small></p>',
                icon: 'warning',
                dangerMode: true,
                confirmText: '<i class="bi bi-trash"></i> Sim, eliminar grupo'
            });

            if (confirmed) {
                showLoading('Eliminando grupo...');
                form.submit();
            }
        }

        $(document).ready(function() {
            // Initialize Select2
            $('.select2-multiple').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Selecione uma ou mais turmas',
                language: {
                    noResults: function() {
                        return "Nenhum resultado encontrado";
                    },
                    searching: function() {
                        return "A procurar...";
                    }
                }
            });

            // Função para atualizar os switches de estado
            function updateTurmasStatus() {
                const selectedTurmas = $('#turma_ids').select2('data');
                const container = $('#turmas-status-list');

                if (selectedTurmas.length === 0) {
                    container.html('<p class="text-muted text-center mb-0">Selecione as turmas acima para definir o estado</p>');
                    return;
                }

                let html = '';
                selectedTurmas.forEach((turma, index) => {
                    const turmaId = turma.id;
                    const turmaNome = turma.text;
                    const associacaoId = $(turma.element).data('associacao-id') || '';
                    const activo = $(turma.element).data('activo') === '1' || $(turma.element).data('activo') === 1;

                    html += `
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                            <div>
                                <strong>${turmaNome}</strong>
                                <input type="hidden" name="turmas[${index}][turma_id]" value="${turmaId}">
                                <input type="hidden" name="turmas[${index}][associacao_id]" value="${associacaoId}">
                            </div>
                            <div class="form-check form-switch">
                                <input type="hidden" name="turmas[${index}][activo]" value="0">
                                <input type="checkbox"
                                       class="form-check-input"
                                       name="turmas[${index}][activo]"
                                       value="1"
                                       id="activo_${turmaId}"
                                       ${activo ? 'checked' : ''}
                                       style="width: 3em; height: 1.5em;">
                                <label class="form-check-label ms-2" for="activo_${turmaId}">
                                    <span class="badge ${activo ? 'bg-success' : 'bg-secondary'}">
                                        ${activo ? 'Activo' : 'Inactivo'}
                                    </span>
                                </label>
                            </div>
                        </div>
                    `;
                });

                container.html(html);

                // Adicionar event listener para atualizar badge ao mudar switch
                container.find('input[type="checkbox"]').on('change', function() {
                    const badge = $(this).siblings('label').find('.badge');
                    if ($(this).is(':checked')) {
                        badge.removeClass('bg-secondary').addClass('bg-success').text('Activo');
                    } else {
                        badge.removeClass('bg-success').addClass('bg-secondary').text('Inactivo');
                    }
                });
            }

            // Atualizar switches quando turmas são selecionadas/deselecionadas
            $('#turma_ids').on('change', updateTurmasStatus);

            // Inicializar na carga da página
            updateTurmasStatus();
        });
    </script>
    @endpush
</x-app-layout>
