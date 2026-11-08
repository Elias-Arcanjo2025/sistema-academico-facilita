<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Alterar Nota</h2>
            <a href="{{ route('notas.show', $nota) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="bi bi-exclamation-triangle"></i>
                                Atenção: Alteração de Nota
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <strong>Esta ação será registada no histórico de auditoria.</strong>
                                <br>
                                É obrigatório fornecer uma justificação para a alteração.
                            </div>

                            <dl class="row mb-4">
                                <dt class="col-sm-4">Aluno</dt>
                                <dd class="col-sm-8">{{ $nota->aluno->nome }}</dd>

                                <dt class="col-sm-4">Disciplina</dt>
                                <dd class="col-sm-8">{{ $nota->disciplina->nome }}</dd>

                                <dt class="col-sm-4">Turma</dt>
                                <dd class="col-sm-8">{{ $nota->turma->nome }}</dd>

                                <dt class="col-sm-4">Tipo</dt>
                                <dd class="col-sm-8">{{ App\Models\Nota::getTipos()[$nota->tipo] ?? $nota->tipo }}</dd>

                                <dt class="col-sm-4">Trimestre</dt>
                                <dd class="col-sm-8">{{ $nota->trimestre ? $nota->trimestre . '.º Trimestre' : '-' }}</dd>

                                <dt class="col-sm-4">Nota Atual</dt>
                                <dd class="col-sm-8">
                                    <span class="badge {{ $nota->isPositiva() ? 'bg-success' : 'bg-danger' }} fs-5">
                                        {{ number_format($nota->valor, 0) }}
                                    </span>
                                </dd>
                            </dl>

                            <x-tip-card icon="bi-pencil" variant="warning" title="Editar Nota" id="tip-notas-edit" compact>
                                Altere os valores de MAC, PP ou PT. A média trimestral (MT) será recalculada automaticamente. Todas as alterações ficam registadas na auditoria do sistema.
                            </x-tip-card>

                            <form action="{{ route('notas.update', $nota) }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="mb-3">
                                    <label for="valor" class="form-label">Nova Nota <span class="text-danger">*</span></label>
                                    <input type="number"
                                        class="form-control form-control-lg @error('valor') is-invalid @enderror"
                                        id="valor"
                                        name="valor"
                                        value="{{ old('valor', $nota->valor) }}"
                                        min="0"
                                        max="20"
                                        step="0.5"
                                        required>
                                    @error('valor')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="justificacao" class="form-label">Justificação <span class="text-danger">*</span></label>
                                    <textarea class="form-control @error('justificacao') is-invalid @enderror"
                                        id="justificacao"
                                        name="justificacao"
                                        rows="4"
                                        required
                                        minlength="10"
                                        maxlength="500"
                                        placeholder="Explique o motivo da alteração (mínimo 10 caracteres)...">{{ old('justificacao') }}</textarea>
                                    @error('justificacao')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">
                                        <span id="charCount">0</span>/500 caracteres
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="bi bi-check-lg"></i> Confirmar Alteração
                                    </button>
                                    <a href="{{ route('notas.show', $nota) }}" class="btn btn-outline-secondary">Cancelar</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const textarea = document.getElementById('justificacao');
        const charCount = document.getElementById('charCount');

        textarea.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });

        // Inicializar contador
        charCount.textContent = textarea.value.length;
    </script>
    @endpush
</x-app-layout>
