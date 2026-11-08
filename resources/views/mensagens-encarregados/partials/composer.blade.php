<div class="card">
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label" for="corpo">Mensagem</label>
            <textarea name="corpo" id="corpo" rows="4" maxlength="2000" required
                      class="form-control @error('corpo') is-invalid @enderror"
                      placeholder="{{ $placeholder }}">{{ old('corpo') }}</textarea>
            @error('corpo')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('mensagens-encarregados.index') }}" class="btn btn-outline-secondary btn-sm">Cancelar</a>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-send"></i> Iniciar e notificar
            </button>
        </div>
    </div>
</div>
