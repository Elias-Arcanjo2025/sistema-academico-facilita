@extends('configuracoes.pagina-publica.layout')

@section('pagina-publica-content')
@php
    $car = $carousel ?? [];
    $larguraPadrao = \App\Support\PaginaPublicaConfig::CAROUSEL_LARGURA_PADRAO;
    $alturaPadrao = \App\Support\PaginaPublicaConfig::CAROUSEL_ALTURA_PADRAO;
@endphp
<div class="content-card">
    <div class="content-card-header">
        <h6 class="content-card-title mb-0"><i class="bi bi-images me-2"></i>Carousel do hero</h6>
    </div>
    <div class="content-card-body">
        <p class="text-muted small mb-3">
            Carregue imagens para o carousel na página pública. Mínimo de
            <strong>{{ \App\Support\PaginaPublicaConfig::CAROUSEL_MINIMO }} imagens</strong> para activar.
            Defina <strong>largura e altura únicas</strong> por slide. A pré-visualização actualiza ao seleccionar o ficheiro.
        </p>

        <form action="{{ route('configuracoes.pagina-publica.carousel.update') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input type="hidden" name="pagina_publica_carousel_activo" value="0">
                        <input class="form-check-input" type="checkbox" name="pagina_publica_carousel_activo" value="1" id="carousel_activo"
                            {{ ($car['pagina_publica_carousel_activo'] ?? '0') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label" for="carousel_activo">Activar carousel na página pública</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Intervalo entre slides (segundos)</label>
                    <input type="number" class="form-control form-control-sm" name="pagina_publica_carousel_intervalo"
                           min="2" max="30" value="{{ $car['pagina_publica_carousel_intervalo'] ?? 5 }}">
                </div>
            </div>

            <div class="row g-4 mb-4">
                @foreach(range(1, \App\Support\PaginaPublicaConfig::CAROUSEL_SLOTS) as $i)
                    @php($slide = $car['slides'][$i] ?? ['imagem' => null, 'url' => null, 'titulo' => '', 'texto' => '', 'largura' => $larguraPadrao, 'altura' => $alturaPadrao])
                    <div class="col-lg-6">
                        <div class="pp-slide-card border rounded-3 p-3 h-100 {{ $i <= \App\Support\PaginaPublicaConfig::CAROUSEL_MINIMO ? 'border-primary border-opacity-25' : '' }}">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold">
                                    Slide {{ $i }}
                                    @if($i <= \App\Support\PaginaPublicaConfig::CAROUSEL_MINIMO)
                                        <span class="badge bg-primary-subtle text-primary ms-1">obrigatório</span>
                                    @endif
                                </span>
                            </div>

                            <div class="pp-slide-preview mb-3 rounded overflow-hidden bg-light position-relative mx-auto"
                                 id="preview-wrap-{{ $i }}"
                                 data-slide="{{ $i }}"
                                 style="width: min(100%, {{ $slide['largura'] }}px); aspect-ratio: {{ $slide['largura'] }} / {{ $slide['altura'] }}; max-height: 320px;">
                                <img id="preview-img-{{ $i }}"
                                     src="{{ $slide['url'] ?? '' }}"
                                     alt="Pré-visualização slide {{ $i }}"
                                     class="pp-slide-preview__img {{ empty($slide['url']) ? 'd-none' : '' }}">
                                <div id="preview-placeholder-{{ $i }}"
                                     class="pp-slide-preview__placeholder {{ !empty($slide['url']) ? 'd-none' : '' }}">
                                    <i class="bi bi-image display-6 text-muted"></i>
                                    <span class="small text-muted mt-2">Pré-visualização</span>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small mb-1">Imagem</label>
                                <input type="file" class="form-control form-control-sm pp-slide-file"
                                       name="carousel_imagem_{{ $i }}" id="carousel_file_{{ $i }}"
                                       accept="image/jpeg,image/png,image/jpg,image/webp" data-slide="{{ $i }}">
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label small mb-1">Largura (px)</label>
                                    <input type="number" class="form-control form-control-sm pp-slide-dim"
                                           name="pagina_publica_carousel_{{ $i }}_largura" id="carousel_largura_{{ $i }}"
                                           data-slide="{{ $i }}" data-dim="largura"
                                           min="200" max="1400" value="{{ $slide['largura'] }}">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Altura (px)</label>
                                    <input type="number" class="form-control form-control-sm pp-slide-dim"
                                           name="pagina_publica_carousel_{{ $i }}_altura" id="carousel_altura_{{ $i }}"
                                           data-slide="{{ $i }}" data-dim="altura"
                                           min="150" max="900" value="{{ $slide['altura'] }}">
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small mb-1">Título (legenda)</label>
                                <input type="text" class="form-control form-control-sm" name="pagina_publica_carousel_{{ $i }}_titulo"
                                       value="{{ $slide['titulo'] }}" maxlength="120">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small mb-1">Texto (legenda)</label>
                                <input type="text" class="form-control form-control-sm" name="pagina_publica_carousel_{{ $i }}_texto"
                                       value="{{ $slide['texto'] }}" maxlength="300">
                            </div>
                            @if(!empty($slide['imagem']))
                                <button type="button" class="btn btn-outline-danger btn-sm w-100"
                                        onclick="if(confirm('Remover imagem do slide {{ $i }}?')) document.getElementById('form-remover-slide-{{ $i }}').submit();">
                                    <i class="bi bi-trash me-1"></i>Remover imagem
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-check-lg me-1"></i>Guardar carousel
            </button>
        </form>

        @foreach(range(1, \App\Support\PaginaPublicaConfig::CAROUSEL_SLOTS) as $i)
            @if(!empty(($car['slides'][$i]['imagem'] ?? null)))
                <form id="form-remover-slide-{{ $i }}" action="{{ route('configuracoes.pagina-publica.carousel.remover-imagem', $i) }}" method="POST" class="d-none">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        @endforeach
    </div>
</div>
@endsection

@push('styles')
<style>
.pp-slide-preview {
    border: 1px dashed var(--border-color, #cbd5e1);
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
}
.pp-slide-preview__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.pp-slide-preview__placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    text-align: center;
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    function updatePreviewSize(slide) {
        var wrap = document.getElementById('preview-wrap-' + slide);
        var largura = document.getElementById('carousel_largura_' + slide);
        var altura = document.getElementById('carousel_altura_' + slide);
        if (!wrap || !largura || !altura) return;

        var w = Math.max(200, Math.min(1400, parseInt(largura.value, 10) || {{ $larguraPadrao }}));
        var h = Math.max(150, Math.min(900, parseInt(altura.value, 10) || {{ $alturaPadrao }}));

        wrap.style.width = 'min(100%, ' + w + 'px)';
        wrap.style.aspectRatio = w + ' / ' + h;
    }

    document.querySelectorAll('.pp-slide-file').forEach(function (input) {
        input.addEventListener('change', function () {
            var slide = input.getAttribute('data-slide');
            var file = input.files && input.files[0];
            var img = document.getElementById('preview-img-' + slide);
            var placeholder = document.getElementById('preview-placeholder-' + slide);
            if (!file || !img) return;

            if (!file.type.match(/^image\//)) return;

            var reader = new FileReader();
            reader.onload = function (e) {
                img.src = e.target.result;
                img.classList.remove('d-none');
                if (placeholder) placeholder.classList.add('d-none');
                updatePreviewSize(slide);
            };
            reader.readAsDataURL(file);
        });
    });

    document.querySelectorAll('.pp-slide-dim').forEach(function (input) {
        input.addEventListener('input', function () {
            updatePreviewSize(input.getAttribute('data-slide'));
        });
    });
})();
</script>
@endpush
