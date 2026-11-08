<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-arrow-right-circle me-2 text-primary"></i>Transição de Alunos
            </h2>
            <a href="{{ route('alunos.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Alunos', 'url' => route('alunos.index')],
                ['label' => 'Transição de Alunos']
            ]" />

            <x-tip-card icon="bi-arrow-right-circle" variant="warning" title="Transição de Alunos" id="tip-alunos-transicao">
                Processe a transição de alunos entre anos lectivos. Os alunos aprovados são promovidos para a classe seguinte, e os reprovados permanecem na mesma classe. Verifique as situações académicas antes de iniciar.
            </x-tip-card>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <!-- Alerta de Aviso -->
                    <div class="alert alert-info alert-modern mb-4 fade-in">
                        <i class="bi bi-info-circle-fill"></i>
                        <div>
                            <strong>Transição de Alunos Entre Anos Lectivos</strong><br>
                            <small>Esta funcionalidade permite transitar alunos aprovados (ou reprovados) de um ano lectivo para outro.
                            Os alunos serão <strong>duplicados</strong> - o registo original permanecerá intacto no ano de origem, preservando o histórico académico.</small>
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
                                <i class="bi bi-1-circle me-2"></i>Passo 1: Configuração da Transição
                            </h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('alunos.transicao.preview') }}" method="POST">
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
                                                {{ $ano }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('ano_origem')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">
                                        <i class="bi bi-info-circle me-1"></i>Ano lectivo actual - de onde os alunos serão transitados
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
                                        @forelse($anosDestino as $ano)
                                            <option value="{{ $ano }}" {{ old('ano_destino') == $ano ? 'selected' : '' }}>
                                                {{ $ano }}
                                            </option>
                                        @empty
                                            <option value="" disabled>Nenhum ano disponível</option>
                                        @endforelse
                                    </select>
                                    @error('ano_destino')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">
                                        <i class="bi bi-info-circle me-1"></i>Ano lectivo novo ou actual - para onde os alunos serão transitados
                                    </div>
                                    @if($anosDestino->isEmpty())
                                        <div class="alert alert-warning mt-2 mb-0">
                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                            <small>Nenhum ano lectivo de destino encontrado. Por favor, certifique-se de que existe um ano lectivo com estado "Actual" ou "Novo".</small>
                                        </div>
                                    @endif
                                </div>

                                <hr class="my-4">

                                <!-- Opções de Transição -->
                                <h6 class="mb-3">
                                    <i class="bi bi-gear me-1"></i>Opções de Transição
                                </h6>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               id="transitar_reprovados"
                                               name="transitar_reprovados"
                                               value="1"
                                               {{ old('transitar_reprovados') ? 'checked' : '' }}
                                               style="width: 3em; height: 1.5em;">
                                        <label class="form-check-label ms-2" for="transitar_reprovados">
                                            <strong>Transitar também alunos reprovados</strong>
                                            <br><small class="text-muted">Alunos reprovados repetirão a mesma classe no ano de destino</small>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               id="selecao_individual"
                                               name="selecao_individual"
                                               value="1"
                                               {{ old('selecao_individual') ? 'checked' : '' }}
                                               style="width: 3em; height: 1.5em;">
                                        <label class="form-check-label ms-2" for="selecao_individual">
                                            <strong>Permitir seleção individual de alunos</strong>
                                            <br><small class="text-muted">No próximo passo, poderá escolher manualmente quais alunos transitar</small>
                                        </label>
                                    </div>
                                </div>

                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <div>
                                        <strong>Importante:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>Por padrão, <strong>apenas alunos aprovados</strong> serão transitados</li>
                                            <li>Alunos com situação "pendente" (sem notas finais) <strong>não</strong> serão transitados automaticamente</li>
                                            <li>O mesmo <code>número de processo</code> será mantido (identificador único do aluno)</li>
                                            <li>As turmas de destino devem existir previamente no ano de destino</li>
                                        </ul>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('alunos.index') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-lg me-1"></i>Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-eye me-1"></i>Continuar para Pré-visualização
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
