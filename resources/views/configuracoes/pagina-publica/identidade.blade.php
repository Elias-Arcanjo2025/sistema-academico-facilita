@extends('configuracoes.pagina-publica.layout')

@section('pagina-publica-content')
<div class="content-card">
    <div class="content-card-header">
        <h6 class="content-card-title mb-0"><i class="bi bi-palette me-2"></i>Identidade visual</h6>
    </div>
    <div class="content-card-body">
        <p class="text-muted small mb-3">
            As cores aplicam-se a <strong>todo o sistema</strong> (menu, botões, alertas, dashboard e página inicial), com combinações harmoniosas automáticas.
        </p>

        <form action="{{ route('configuracoes.pagina-publica.identidade.update') }}" method="POST">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Cor principal</label>
                    <div class="input-group input-group-sm">
                        <input type="color" class="form-control form-control-color" name="pagina_publica_cor_primaria" id="pp_cor_primaria"
                               value="{{ $pp['pagina_publica_cor_primaria'] ?? '#4f46e5' }}">
                        <input type="text" class="form-control pp-color-text" value="{{ $pp['pagina_publica_cor_primaria'] ?? '#4f46e5' }}"
                               data-sync-color="pagina_publica_cor_primaria" maxlength="7">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cor principal (escura)</label>
                    <div class="input-group input-group-sm">
                        <input type="color" class="form-control form-control-color" name="pagina_publica_cor_primaria_escura" id="pp_cor_primaria_escura"
                               value="{{ $pp['pagina_publica_cor_primaria_escura'] ?? '#3730a3' }}">
                        <input type="text" class="form-control pp-color-text" value="{{ $pp['pagina_publica_cor_primaria_escura'] ?? '#3730a3' }}"
                               data-sync-color="pagina_publica_cor_primaria_escura" maxlength="7">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cor secundária</label>
                    <div class="input-group input-group-sm">
                        <input type="color" class="form-control form-control-color" name="pagina_publica_cor_secundaria" id="pp_cor_secundaria"
                               value="{{ $pp['pagina_publica_cor_secundaria'] ?? '#06b6d4' }}">
                        <input type="text" class="form-control pp-color-text" value="{{ $pp['pagina_publica_cor_secundaria'] ?? '#06b6d4' }}"
                               data-sync-color="pagina_publica_cor_secundaria" maxlength="7">
                    </div>
                </div>
            </div>

            @include('configuracoes.pagina-publica.partials.preview-cores')

            <button type="submit" class="btn btn-primary btn-sm mt-3">
                <i class="bi bi-check-lg me-1"></i>Guardar cores
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@include('configuracoes.pagina-publica.partials.preview-cores-script')
@endpush
