@php
    use App\Support\PaginaPublicaConfig;

    $slides = $slides ?? (($paginaPublica['carousel_activo'] ?? false) ? ($paginaPublica['carousel_slides'] ?? []) : []);
    $mostrarCarousel = ($mostrarCarousel ?? null) ?? count($slides) >= PaginaPublicaConfig::CAROUSEL_MINIMO;
    $intervalo = $intervalo ?? (($paginaPublica['carousel_intervalo'] ?? 5) * 1000);
@endphp

@if($mostrarCarousel)
<div id="heroCarousel" class="carousel slide carousel-fade hero-carousel h-100"
     data-bs-ride="carousel"
     data-bs-interval="{{ $intervalo }}">
    @if(count($slides) > 1)
    <div class="carousel-indicators hero-carousel__indicators">
        @foreach($slides as $idx => $slide)
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="{{ $idx }}"
                class="{{ $idx === 0 ? 'active' : '' }}" aria-label="Slide {{ $idx + 1 }}"></button>
        @endforeach
    </div>
    @endif

    <div class="carousel-inner hero-carousel__inner overflow-hidden">
        @foreach($slides as $idx => $slide)
            <div class="carousel-item {{ $idx === 0 ? 'active' : '' }} h-100">
                <div class="hero-carousel__frame">
                    @if(!empty($slide['url']))
                    <img src="{{ $slide['url'] }}"
                         class="hero-carousel__img d-block"
                         alt="{{ $slide['titulo'] ?: 'Slide '.($idx + 1) }}"
                         width="{{ $slide['largura'] }}"
                         height="{{ $slide['altura'] }}"
                         loading="{{ $idx === 0 ? 'eager' : 'lazy' }}">
                    @endif
                </div>
                @if($slide['titulo'] || $slide['texto'])
                <div class="carousel-caption hero-carousel__caption">
                    @if($slide['titulo'])<h5 class="mb-1">{{ $slide['titulo'] }}</h5>@endif
                    @if($slide['texto'])<p class="mb-0">{{ $slide['texto'] }}</p>@endif
                </div>
                @endif
            </div>
        @endforeach
    </div>

    @if(count($slides) > 1)
    <button class="carousel-control-prev hero-carousel__control" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Anterior</span>
    </button>
    <button class="carousel-control-next hero-carousel__control" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Seguinte</span>
    </button>
    @endif
</div>
@endif
