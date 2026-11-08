<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-arrow-left-right me-2"></i>Transferir Aluno de Turma
            </h2>
            <a href="{{ route('alunos.show', $aluno) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-arrow-left-right" variant="warning" title="Transferência de Aluno" id="tip-alunos-transfer">
                Transfira um aluno para outra turma ou curso. O histórico de notas é preservado. Verifique se a turma de destino tem vagas disponíveis antes de prosseguir.
            </x-tip-card>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    {{-- 🔒 Alerta de Modo Consulta --}}
                    @if(\App\Models\Configuracao::isModoConsultaById($aluno->turma->ano_lectivo_id))
                        @if(auth()->user()->hasRole('admin_full'))
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <h5 class="alert-heading">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    ⚠️ ATENÇÃO: Ano Lectivo em Modo Consulta
                                </h5>
                                <p class="mb-0">
                                    Este ano lectivo (<strong>{{ $aluno->turma->ano_lectivo }}</strong>) está em <strong>modo consulta</strong> (bloqueado para edição).
                                    Como <strong>Admin Full</strong>, pode prosseguir, mas esta ação será <strong>registada na auditoria</strong>.
                                </p>
                                <hr>
                                <p class="mb-0 small">
                                    <i class="bi bi-shield-fill-exclamation me-1"></i>
                                    Certifique-se de que esta modificação é realmente necessária antes de continuar.
                                </p>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @else
                            <div class="alert alert-danger" role="alert">
                                <h5 class="alert-heading">
                                    <i class="bi bi-lock-fill me-2"></i>
                                    🔒 Ano Lectivo Bloqueado
                                </h5>
                                <p class="mb-0">
                                    Este ano lectivo (<strong>{{ $aluno->turma->ano_lectivo }}</strong>) está em <strong>modo consulta</strong>.
                                    Não é possível transferir alunos em anos bloqueados para preservar dados históricos.
                                </p>
                                <hr>
                                <p class="mb-0 small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Apenas administradores full podem modificar dados de anos em consulta.
                                </p>
                            </div>

                            {{-- Ocultar formulário para não-admin --}}
                            @php
                                $ocultarFormulario = true;
                            @endphp
                        @endif
                    @endif

                    <!-- Info Card do Aluno -->
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
                                    <p class="mb-1"><strong>Ano Lectivo:</strong> <span class="badge bg-light text-dark border">{{ $aluno->turma->ano_lectivo }}</span></p>
                                </div>
                            </div>

                            @if($notasCount > 0)
                                <div class="alert alert-warning mt-3 mb-0">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Atenção:</strong> Este aluno possui <strong>{{ $notasCount }} nota(s)</strong> lançada(s).
                                    Poderá escolher se deseja mantê-las ou removê-las após a transferência.
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Formulário de Transferência -->
                    <div class="card shadow-soft">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Dados da Transferência</h5>
                        </div>
                        <div class="card-body">
                            <!-- Mostrar erros de validação gerais -->
                            @if ($errors->any())
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Erro ao processar transferência:</strong>
                                    <ul class="mb-0 mt-2">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            @if(isset($ocultarFormulario) && $ocultarFormulario)
                                {{-- Formulário bloqueado por modo consulta --}}
                                <div class="text-center py-4">
                                    <i class="bi bi-lock-fill text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">Formulário bloqueado</p>
                                </div>
                            @elseif($turmasDisponiveis->isEmpty())
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    Não há outras turmas disponíveis no mesmo ano lectivo (<strong>{{ $aluno->turma->ano_lectivo }}</strong>) para transferência.
                                </div>
                            @else
                                <form id="form-transferencia" action="{{ route('alunos.transfer', $aluno) }}" method="POST">
                                    @csrf
                                    @method('PUT')

                                    <div class="mb-3">
                                        <label for="nova_turma_id" class="form-label">
                                            <i class="bi bi-people me-1"></i>Turma de Destino <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select @error('nova_turma_id') is-invalid @enderror"
                                                id="nova_turma_id"
                                                name="nova_turma_id"
                                                data-curso-atual="{{ $aluno->turma->curso_id }}">
                                            <option value="">Selecione a turma de destino</option>
                                            @php
                                                $turmasPorCurso = $turmasDisponiveis->groupBy('curso_id');
                                            @endphp
                                            @foreach($turmasPorCurso as $cursoId => $turmas)
                                                @php
                                                    $curso = $turmas->first()->curso;
                                                    $isCursoAtual = $cursoId == $aluno->turma->curso_id;
                                                @endphp
                                                <optgroup label="{{ $curso->nome }}{{ $isCursoAtual ? ' (ATUAL)' : '' }}">
                                                    @foreach($turmas as $turma)
                                                        <option value="{{ $turma->id }}"
                                                                data-curso-id="{{ $turma->curso_id }}"
                                                                data-curso-nome="{{ $turma->curso->nome }}"
                                                                {{ old('nova_turma_id') == $turma->id ? 'selected' : '' }}>
                                                            {{ $turma->nome }}
                                                            @if($turma->classe)
                                                                ({{ $turma->classe->nome }})
                                                            @endif
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                        @error('nova_turma_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror

                                        {{-- Alerta dinâmico de troca de curso --}}
                                        <div id="alerta-troca-curso" class="alert alert-danger mt-2 d-none" role="alert">
                                            <h6 class="alert-heading">
                                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                                ⚠️ Transferência Entre Cursos Diferentes!
                                            </h6>
                                            <p class="mb-2 small">
                                                Está a tentar transferir de <strong id="curso-origem"></strong> para <strong id="curso-destino"></strong>.
                                                Isso criará inconsistências pois as disciplinas dos cursos são diferentes.
                                            </p>
                                            @if(auth()->user()->podeTransferirEntreCursos())
                                                <hr>
                                                <p class="mb-2 small">
                                                    <i class="bi bi-shield-fill-check me-1"></i>
                                                    Pode forçar esta transferência, mas
                                                    <strong class="text-decoration-underline">todas as notas serão arquivadas automaticamente</strong>.
                                                </p>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="confirmar_troca_curso" name="confirmar_troca_curso" value="1">
                                                    <label class="form-check-label small" for="confirmar_troca_curso">
                                                        <strong>Confirmo que desejo forçar esta transferência entre cursos</strong>
                                                    </label>
                                                </div>
                                            @else
                                                <hr>
                                                <p class="mb-0 small text-danger">
                                                    <i class="bi bi-x-circle me-1"></i>
                                                    Não tem permissão para transferir entre cursos diferentes.
                                                    Por favor, seleccione uma turma do mesmo curso.
                                                </p>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="motivo" class="form-label">
                                            <i class="bi bi-chat-text me-1"></i>Motivo da Transferência <span class="text-danger">*</span>
                                        </label>
                                        <textarea class="form-control @error('motivo') is-invalid @enderror"
                                                  id="motivo"
                                                  name="motivo"
                                                  rows="3"
                                                  placeholder="Descreva o motivo da transferência (ex: Erro de registo, Pedido dos pais, etc.)">{{ old('motivo') }}</textarea>
                                        @error('motivo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="text-muted">Máximo de 500 caracteres</small>
                                    </div>

                                    @if($notasCount > 0)
                                        <div class="mb-3">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h6 class="card-title">
                                                        <i class="bi bi-clipboard-check me-2"></i>Gestão de Notas ({{ $notasCount }} nota(s))
                                                    </h6>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input"
                                                               type="checkbox"
                                                               id="manter_notas"
                                                               name="manter_notas"
                                                               value="1"
                                                               {{ old('manter_notas', true) ? 'checked' : '' }}
                                                               style="width: 3em; height: 1.5em;">
                                                        <label class="form-check-label ms-2" for="manter_notas">
                                                            <strong>Manter as notas após a transferência</strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                Se desmarcado, todas as {{ $notasCount }} nota(s) serão removidas permanentemente.
                                                            </small>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <strong>O que acontecerá:</strong>
                                        <ol class="mb-0 mt-2">
                                            <li>O aluno será movido da turma <strong>{{ $aluno->turma->nome }}</strong> para a turma selecionada</li>
                                            <li>Os números de ordem serão recalculados em ambas as turmas</li>
                                            @if($notasCount > 0)
                                                <li>As notas serão <span class="fw-bold" id="nota-action">mantidas</span> conforme sua escolha</li>
                                            @endif
                                            <li>Esta operação ficará registada no histórico de auditoria</li>
                                        </ol>
                                    </div>

                                    <hr class="my-4">

                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('alunos.show', $aluno) }}" class="btn btn-outline-secondary">
                                            <i class="bi bi-x-lg me-1"></i>Cancelar
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-lg me-1"></i>Confirmar Transferência
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectTurma = document.getElementById('nova_turma_id');
            const alertaTrocaCurso = document.getElementById('alerta-troca-curso');
            const btnSubmit = document.querySelector('button[type="submit"]');
            const manterNotasCheckbox = document.getElementById('manter_notas');
            const notaAction = document.getElementById('nota-action');
            const form = document.getElementById('form-transferencia');

            // 🔒 VALIDAÇÃO DINÂMICA: Detectar mudança de curso
            if (selectTurma && alertaTrocaCurso) {
                const cursoAtualId = selectTurma.dataset.cursoAtual;
                const cursoAtualNome = '{{ $aluno->turma->curso->nome }}';

                selectTurma.addEventListener('change', function() {
                    const optionSelecionada = this.options[this.selectedIndex];

                    if (!optionSelecionada || !optionSelecionada.value) {
                        alertaTrocaCurso.classList.add('d-none');
                        if (btnSubmit) btnSubmit.disabled = false;
                        return;
                    }

                    const cursoDestinoId = optionSelecionada.dataset.cursoId;
                    const cursoDestinoNome = optionSelecionada.dataset.cursoNome;

                    // Verificar se é curso diferente
                    if (cursoDestinoId !== cursoAtualId) {
                        // Mostrar alerta
                        alertaTrocaCurso.classList.remove('d-none');
                        document.getElementById('curso-origem').textContent = cursoAtualNome;
                        document.getElementById('curso-destino').textContent = cursoDestinoNome;

                        @if(!auth()->user()->hasRole('admin_full'))
                            // Não-admin: bloquear submit
                            if (btnSubmit) {
                                btnSubmit.disabled = true;
                            }
                        @else
                            // Admin_full: desabilitar checkbox "manter notas" e desmarcá-lo
                            if (manterNotasCheckbox) {
                                manterNotasCheckbox.checked = false;
                                manterNotasCheckbox.disabled = true;
                                if (notaAction) notaAction.textContent = 'arquivadas (obrigatório)';
                            }
                        @endif
                    } else {
                        // Mesmo curso: ocultar alerta
                        alertaTrocaCurso.classList.add('d-none');
                        if (btnSubmit) btnSubmit.disabled = false;

                        // Reabilitar checkbox
                        if (manterNotasCheckbox) {
                            manterNotasCheckbox.disabled = false;
                            manterNotasCheckbox.checked = true;
                            if (notaAction) notaAction.textContent = 'mantidas';
                        }
                    }
                });
            }

            // Atualizar texto dinâmico baseado no checkbox
            if (manterNotasCheckbox && notaAction) {
                manterNotasCheckbox.addEventListener('change', function() {
                    const action = this.checked ? 'mantidas' : 'arquivadas';
                    notaAction.textContent = action;
                });
            }

            // Validação final no submit
            if (form) {
                form.addEventListener('submit', function(e) {
                    const optionSelecionada = selectTurma.options[selectTurma.selectedIndex];
                    const cursoAtualId = selectTurma.dataset.cursoAtual;
                    const cursoDestinoId = optionSelecionada?.dataset.cursoId;

                    // Se for troca de curso e não for admin_full, bloquear
                    @if(!auth()->user()->hasRole('admin_full'))
                        if (cursoDestinoId && cursoDestinoId !== cursoAtualId) {
                            e.preventDefault();
                            showNotification('error', 'Não pode transferir alunos entre cursos diferentes.', 'Transferência bloqueada');
                            return false;
                        }
                    @else
                        // Admin_full: verificar confirmação
                        if (cursoDestinoId && cursoDestinoId !== cursoAtualId) {
                            const checkboxConfirmacao = document.getElementById('confirmar_troca_curso');
                            if (!checkboxConfirmacao || !checkboxConfirmacao.checked) {
                                e.preventDefault();
                                showNotification('warning', 'Por favor, marque a confirmação de transferência entre cursos diferentes.', 'Atenção');
                                return false;
                            }
                        }
                    @endif
                });
            }
        });
    </script>
    @endpush
</x-app-layout>
