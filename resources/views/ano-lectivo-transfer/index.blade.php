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
            <x-tip-card icon="bi-arrow-left-right" variant="warning" title="Transferir Estrutura de Ano Lectivo" id="tip-anolectivo-transfer">
                Copie a estrutura académica (cursos, turmas, disciplinas, associações) de um ano lectivo para outro. Esta operação não copia notas nem alunos — apenas a estrutura organizacional.
            </x-tip-card>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <!-- Alerta de Aviso -->
                    <div class="alert alert-warning alert-modern mb-4 fade-in">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            <strong>Atenção!</strong><br>
                            <small>Esta operação irá duplicar turmas e atribuições (professores, directores, coordenadores) de um ano lectivo para outro.
                            Os dados originais permanecerão intactos. <strong>Alunos NÃO serão transferidos</strong> - as turmas novas ficarão vazias.</small>
                        </div>
                    </div>

                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Erros de validação:</strong>
                            <ul class="mb-0 mt-2">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <div class="card shadow-soft fade-in">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-1-circle me-2"></i>Passo 1: Configuração da Transferência
                            </h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('ano-lectivo-transfer.preview') }}" method="POST">
                                @csrf

                                <!-- Ano de Origem -->
                                <div class="mb-4">
                                    <label for="ano_origem" class="form-label">
                                        <i class="bi bi-calendar-check me-1"></i>Ano Lectivo de Origem <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select @error('ano_origem') is-invalid @enderror"
                                            id="ano_origem"
                                            name="ano_origem"
                                            required>
                                        <option value="">Selecione o ano de origem</option>
                                        @foreach($anosDisponiveis as $ano)
                                            <option value="{{ $ano }}" {{ old('ano_origem', $anoActual) == $ano ? 'selected' : '' }}>
                                                {{ $ano }}{{ $ano == $anoActual ? ' (Actual)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('ano_origem')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">
                                        <i class="bi bi-info-circle me-1"></i>Ano lectivo do qual serão copiados os dados
                                    </div>
                                </div>

                                <!-- Ano de Destino -->
                                <div class="mb-4">
                                    <label for="ano_destino" class="form-label">
                                        <i class="bi bi-calendar-plus me-1"></i>Ano Lectivo de Destino <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select @error('ano_destino') is-invalid @enderror"
                                            id="ano_destino"
                                            name="ano_destino"
                                            required>
                                        <option value="">Selecione o ano de destino</option>
                                        @forelse($anosNovos as $ano)
                                            <option value="{{ $ano }}" {{ old('ano_destino') == $ano ? 'selected' : '' }}>
                                                {{ $ano }}
                                            </option>
                                        @empty
                                            <option value="" disabled>Nenhum ano novo cadastrado</option>
                                        @endforelse
                                    </select>
                                    @error('ano_destino')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">
                                        <i class="bi bi-info-circle me-1"></i>Selecione um ano lectivo novo (cadastrado previamente)
                                    </div>
                                    @if($anosNovos->isEmpty())
                                        <div class="alert alert-warning mt-2 mb-0">
                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                            <small>Nenhum ano lectivo novo encontrado. Por favor, cadastre um novo ano lectivo nas configurações antes de fazer a transferência.</small>
                                        </div>
                                    @endif
                                </div>

                                <hr class="my-4">

                                <!-- Opções de Transferência -->
                                <h6 class="mb-3">
                                    <i class="bi bi-gear me-1"></i>O que transferir?
                                </h6>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               id="transferir_associacoes"
                                               name="transferir_associacoes"
                                               value="1"
                                               checked
                                               style="width: 3em; height: 1.5em;">
                                        <label class="form-check-label ms-2" for="transferir_associacoes">
                                            <strong>Associações Professor-Disciplina-Turma</strong>
                                            <br><small class="text-muted">Manter os mesmos professores leccionando as mesmas disciplinas</small>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               id="transferir_directores"
                                               name="transferir_directores"
                                               value="1"
                                               checked
                                               style="width: 3em; height: 1.5em;">
                                        <label class="form-check-label ms-2" for="transferir_directores">
                                            <strong>Directores de Turma</strong>
                                            <br><small class="text-muted">Manter os mesmos directores de turma</small>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               id="transferir_coordenadores"
                                               name="transferir_coordenadores"
                                               value="1"
                                               checked
                                               style="width: 3em; height: 1.5em;">
                                        <label class="form-check-label ms-2" for="transferir_coordenadores">
                                            <strong>Coordenadores de Curso</strong>
                                            <br><small class="text-muted">Manter os mesmos coordenadores de curso</small>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               id="transitar_alunos"
                                               name="transitar_alunos"
                                               value="1"
                                               style="width: 3em; height: 1.5em;">
                                        <label class="form-check-label ms-2" for="transitar_alunos">
                                            <strong>Transitar Alunos Automaticamente</strong>
                                            <br><small class="text-muted">Aprovados → classe seguinte (se existirem turmas no ano destino). Reprovados → mesma classe (turmas copiadas).</small>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               id="transitar_reprovados"
                                               name="transitar_reprovados"
                                               value="1"
                                               style="width: 3em; height: 1.5em;">
                                        <label class="form-check-label ms-2" for="transitar_reprovados">
                                            <strong>Incluir reprovados na transição</strong>
                                            <br><small class="text-muted">Reprovados repetem na mesma classe no ano novo (requer opção anterior activa)</small>
                                        </label>
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    <i class="bi bi-lightbulb-fill me-2"></i>
                                    <strong>Nota:</strong> As turmas serão sempre transferidas (obrigatório).
                                    A promoção (classe+1) exige turmas da classe seguinte já criadas no ano destino.
                                    Para controlo total (selecção individual, mapeamento manual), use a
                                    <a href="{{ route('alunos.transicao.index') }}" class="alert-link">Transição de Alunos</a>.
                                </div>

                                <hr class="my-4">

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('configuracoes.index') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-lg me-1"></i>Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="bi bi-eye me-1"></i>Pré-visualizar Transferência
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
