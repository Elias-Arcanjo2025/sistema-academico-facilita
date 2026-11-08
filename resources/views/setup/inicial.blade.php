<x-guest-layout>
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
             style="width:56px;height:56px;background:linear-gradient(135deg,#4f46e5,#06b6d4);color:#fff;">
            <i class="bi bi-mortarboard fs-4"></i>
        </div>
        <h5 class="fw-semibold text-dark mb-1">Configuração inicial</h5>
        <p class="text-muted small mb-0">
            Passo 1 de 1 — Defina os níveis de ensino desta escola.<br>
            Isto determina as classes disponíveis e, mais tarde, o modelo de avaliação.
        </p>
    </div>

    <div class="alert alert-info py-2 px-3 small" role="note">
        <i class="bi bi-info-circle me-1"></i>
        Escolha apenas o que a escola ofereceira. Pode alterar depois em Configurações.
    </div>

    <form method="POST" action="{{ route('setup.inicial.guardar') }}">
        @csrf

        <div class="vstack gap-2 mb-3">
            @foreach($rotulos as $chave => $rotulo)
                @php $checked = in_array($chave, old('perfis', $perfis), true); @endphp
                <label class="border rounded-3 p-3 d-flex gap-3 align-items-start"
                       style="cursor:pointer; {{ $checked ? 'border-color:#4f46e5;background:rgba(79,70,229,.06);' : '' }}">
                    <input type="checkbox"
                           class="form-check-input mt-1"
                           name="perfis[]"
                           value="{{ $chave }}"
                           @checked($checked)>
                    <span>
                        <span class="fw-semibold d-block">{{ $rotulo }}</span>
                        <span class="small text-muted">
                            Classes:
                            {{ implode(', ', array_map(fn ($n) => $n.'ª', $mapa[$chave] ?? [])) }}
                        </span>
                    </span>
                </label>
            @endforeach
        </div>

        @error('perfis')
            <div class="alert alert-danger py-2 small">{{ $message }}</div>
        @enderror

        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg me-2"></i>Guardar e continuar
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center mt-3">
        @csrf
        <button type="submit" class="btn btn-link btn-sm text-muted">Terminar sessão</button>
    </form>
</x-guest-layout>
