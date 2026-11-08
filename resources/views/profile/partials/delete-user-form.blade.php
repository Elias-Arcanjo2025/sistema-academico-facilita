<section>
    <header class="mb-4">
        <p class="text-muted mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Uma vez eliminada a sua conta, todos os recursos e dados serão permanentemente eliminados. Antes de eliminar a conta, faça download de quaisquer dados ou informações que deseje manter.
        </p>
    </header>

    <div class="alert alert-danger">
        <h6 class="alert-heading">
            <i class="bi bi-exclamation-octagon me-2"></i>Atenção: Esta acção é irreversível!
        </h6>
        <p class="mb-0 small">
            Ao eliminar a sua conta, todos os seus dados, incluindo notas, associações e histórico, serão permanentemente removidos do sistema.
        </p>
    </div>

    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmUserDeletionModal">
        <i class="bi bi-trash me-1"></i>Eliminar Conta
    </button>
</section>

<!-- Modal de Confirmação -->
<div class="modal fade" id="confirmUserDeletionModal" tabindex="-1" aria-labelledby="confirmUserDeletionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="confirmUserDeletionModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Tem a certeza?
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <p class="text-danger fw-bold">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        Tem a certeza que deseja eliminar a sua conta?
                    </p>

                    <p class="text-muted small">
                        Uma vez eliminada, todos os recursos e dados associados à conta serão permanentemente removidos. Por favor, introduza a sua password para confirmar que deseja eliminar permanentemente a conta.
                    </p>

                    <div class="mt-3">
                        <label for="password" class="form-label fw-bold">
                            Password <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="bi bi-lock-fill text-danger"></i>
                            </span>
                            <input type="password"
                                   class="form-control @error('password', 'userDeletion') is-invalid @enderror"
                                   id="password"
                                   name="password"
                                   placeholder="Introduza a sua password"
                                   required>
                            @error('password', 'userDeletion')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash-fill me-1"></i>Eliminar Conta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($errors->userDeletion->isNotEmpty())
<script>
    // Reabrir modal se houver erros de validação
    document.addEventListener('DOMContentLoaded', function() {
        var modal = new bootstrap.Modal(document.getElementById('confirmUserDeletionModal'));
        modal.show();
    });
</script>
@endif
