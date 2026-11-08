<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Editar Metadata do Ficheiro</h2>
            <a href="{{ route('pauta-arquivos.show', $pautaArquivo) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h6 class="alert-heading">Erro ao actualizar:</h6>
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row">
                <!-- Formulário de Edição -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-pencil"></i> Editar Informações
                            </h5>
                        </div>
                        <div class="card-body">
                            <x-tip-card icon="bi-pencil" variant="info" title="Editar Metadata do Ficheiro" id="tip-pautaarquivos-edit" compact>
                                Actualize as informações do ficheiro. O ficheiro original não é alterado — apenas os metadados (tipo, descrição, estado).
                            </x-tip-card>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <strong>Nota:</strong> Apenas a metadata pode ser editada. Para alterar o ficheiro ou informações principais, faça upload de uma nova versão.
                            </div>

                            <form action="{{ route('pauta-arquivos.update', $pautaArquivo) }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="row g-3">
                                    <!-- Tipo de Pauta -->
                                    <div class="col-md-6">
                                        <label for="tipo_pauta" class="form-label">
                                            Tipo de Pauta <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select @error('tipo_pauta') is-invalid @enderror"
                                                id="tipo_pauta"
                                                name="tipo_pauta"
                                                required>
                                            @foreach(\App\Models\PautaArquivo::getTiposPauta() as $key => $label)
                                                <option value="{{ $key }}" {{ old('tipo_pauta', $pautaArquivo->tipo_pauta) == $key ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('tipo_pauta')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Status Activo -->
                                    <div class="col-md-6">
                                        <label class="form-label">Status</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   role="switch"
                                                   id="activo"
                                                   name="activo"
                                                   value="1"
                                                   {{ old('activo', $pautaArquivo->activo) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="activo">
                                                Ficheiro activo
                                            </label>
                                        </div>
                                        <small class="text-muted">Desmarque para desactivar o ficheiro</small>
                                    </div>

                                    <!-- Descrição -->
                                    <div class="col-12">
                                        <label for="descricao" class="form-label">
                                            Descrição <span class="text-muted">(Opcional)</span>
                                        </label>
                                        <textarea class="form-control @error('descricao') is-invalid @enderror"
                                                  id="descricao"
                                                  name="descricao"
                                                  rows="4"
                                                  maxlength="1000"
                                                  placeholder="Observações sobre este ficheiro...">{{ old('descricao', $pautaArquivo->descricao) }}</textarea>
                                        @error('descricao')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="text-muted">Máximo 1000 caracteres</small>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg"></i> Guardar Alterações
                                    </button>
                                    <a href="{{ route('pauta-arquivos.show', $pautaArquivo) }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-lg"></i> Cancelar
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Informações do Arquivo (Read-Only) -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-file-earmark-excel"></i> Informações do Ficheiro
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted">Ficheiro</label>
                                <div class="small">{{ $pautaArquivo->nome_original }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted">Curso</label>
                                <div>{{ $pautaArquivo->curso->nome }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted">Turma</label>
                                <div>
                                    {{ $pautaArquivo->turma ? $pautaArquivo->turma->nome : '-' }}
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted">Ano Lectivo</label>
                                <div>{{ $pautaArquivo->ano_lectivo }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted">Trimestre</label>
                                <div>{{ $pautaArquivo->nome_trimestre }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted">Disciplina</label>
                                <div>
                                    {{ $pautaArquivo->disciplina ? $pautaArquivo->disciplina->nome : 'Turma Completa' }}
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted">Versão</label>
                                <div>
                                    <span class="badge bg-secondary">{{ $pautaArquivo->versao_formatada }}</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted">Tamanho</label>
                                <div>{{ $pautaArquivo->tamanho_formatado }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted">Enviado por</label>
                                <div>{{ $pautaArquivo->uploadedBy->name }}</div>
                            </div>

                            <div>
                                <label class="form-label fw-bold text-muted">Data de Envio</label>
                                <div>{{ $pautaArquivo->created_at->format('d/m/Y H:i') }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Atenção:</strong> Para alterar o ficheiro ou informações principais (curso, turma, ano, etc.), faça upload de uma nova versão através do formulário de envio.
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
