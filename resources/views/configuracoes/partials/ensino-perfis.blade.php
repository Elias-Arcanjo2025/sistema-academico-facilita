{{-- Perfis de ensino da instalação --}}
@php
    $ensinoPerfis = $ensinoPerfis ?? \App\Support\EnsinoPerfil::perfisActivos();
    $ensinoRotulos = $ensinoRotulos ?? \App\Support\EnsinoPerfil::todosRotulos();
    $ensinoFonte = $ensinoFonte ?? \App\Support\EnsinoPerfil::fonte();
    $ensinoClasses = $ensinoClasses ?? \App\Support\EnsinoPerfil::classesPermitidas();
@endphp

<div class="content-card mb-3" id="ensino-perfis-section">
    <div class="content-card-header">
        <h6 class="content-card-title mb-0">
            <i class="bi bi-mortarboard me-2"></i>Perfis de ensino
        </h6>
    </div>
    <div class="content-card-body">
        <p class="text-muted small mb-3">
            Define quais níveis a escola ofereceira. As classes fora do perfil ficam inactivas e deixam de aparecer ao criar turmas.
            @if($ensinoFonte === 'bd')
                <span class="badge text-bg-success">Guardado nas Configurações</span>
            @else
                <span class="badge text-bg-secondary">A usar .env (ENSINO_PERFIS)</span>
            @endif
        </p>

        <form action="{{ route('configuracoes.update-ensino') }}" method="POST">
            @csrf

            <div class="row g-3 mb-3">
                @foreach($ensinoRotulos as $chave => $rotulo)
                    <div class="col-md-4">
                        <div class="form-check border rounded p-3 h-100 {{ in_array($chave, $ensinoPerfis, true) ? 'border-primary bg-primary bg-opacity-10' : '' }}">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="perfis[]"
                                value="{{ $chave }}"
                                id="ensino_perfil_{{ $chave }}"
                                @checked(in_array($chave, $ensinoPerfis, true))
                            >
                            <label class="form-check-label fw-semibold" for="ensino_perfil_{{ $chave }}">
                                {{ $rotulo }}
                            </label>
                            <div class="small text-muted mt-1">
                                Classes:
                                {{ implode(', ', array_map(fn ($n) => $n.'ª', config('ensino.mapa.'.$chave, []))) }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @error('perfis')
                <div class="alert alert-danger py-2 small">{{ $message }}</div>
            @enderror

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Activas agora:
                    <strong>{{ $ensinoClasses === [] ? '—' : implode(', ', array_map(fn ($n) => $n.'ª', $ensinoClasses)) }}</strong>
                </small>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-circle me-1"></i>Guardar e sincronizar classes
                </button>
            </div>
        </form>
    </div>
</div>
