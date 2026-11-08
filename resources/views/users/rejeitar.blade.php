<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Rejeitar Conta</h2>
            <a href="{{ route('users.pendentes') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="bi bi-x-circle"></i> Rejeitar Conta</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                Esta a prestes a rejeitar a conta de <strong>{{ $user->name }}</strong>.
                                O utilizador sera notificado com o motivo da rejeicao.
                            </div>

                            <dl class="row mb-4">
                                <dt class="col-sm-4">Nome</dt>
                                <dd class="col-sm-8">{{ $user->name }}</dd>

                                <dt class="col-sm-4">Email</dt>
                                <dd class="col-sm-8">{{ $user->email }}</dd>

                                <dt class="col-sm-4">Registado em</dt>
                                <dd class="col-sm-8">{{ $user->created_at->format('d/m/Y H:i') }}</dd>
                            </dl>

                            <form action="{{ route('users.rejeitar', $user) }}" method="POST">
                                @csrf

                                <div class="mb-3">
                                    <label for="motivo_rejeicao" class="form-label">Motivo da Rejeicao <span class="text-danger">*</span></label>
                                    <textarea class="form-control @error('motivo_rejeicao') is-invalid @enderror"
                                        id="motivo_rejeicao"
                                        name="motivo_rejeicao"
                                        rows="4"
                                        required
                                        minlength="10"
                                        maxlength="500"
                                        placeholder="Explique o motivo da rejeicao (minimo 10 caracteres)...">{{ old('motivo_rejeicao') }}</textarea>
                                    @error('motivo_rejeicao')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">
                                        <span id="charCount">0</span>/500 caracteres
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-danger" onclick="event.preventDefault(); handleRejeitarConta(this);">
                                        <i class="bi bi-x-lg"></i> Confirmar Rejeicao
                                    </button>
                                    <a href="{{ route('users.pendentes') }}" class="btn btn-outline-secondary">
                                        Cancelar
                                    </a>
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
        // Handler para confirmação de rejeição de conta
        async function handleRejeitarConta(button) {
            const confirmed = await confirmAction({
                title: 'Rejeitar Conta de Utilizador?',
                html: '<p>Tem a certeza que deseja <strong>rejeitar</strong> esta conta?</p><p class="text-danger"><small>Esta ação não pode ser revertida.</small></p>',
                icon: 'warning',
                dangerMode: true,
                confirmText: '<i class="bi bi-x-lg"></i> Sim, rejeitar conta'
            });

            if (confirmed) {
                showLoading('Rejeitando conta...');
                button.closest('form').submit();
            }
        }

        const textarea = document.getElementById('motivo_rejeicao');
        const charCount = document.getElementById('charCount');

        textarea.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });
    </script>
    @endpush
</x-app-layout>
