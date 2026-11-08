{{-- Estilos específicos do modelo de boletim escolhido pela secretaria --}}
@php
    $modelo = $boletim_modelo ?? 'classico';
    $sec = $boletim_cores['secundaria'] ?? ($pdfConfig['colors']['secondary'] ?? '#134e4a');
@endphp
<style>
    :root {
        --boletim-secondary: {{ $sec }};
    }

    @if($modelo === 'moderno')
    .top-bar {
        border-bottom: none !important;
        background: var(--pdf-primary);
        color: #fff;
        padding: 10px 10px 8px !important;
        margin: -2mm -2mm 10px -2mm;
        border-radius: 0 0 6px 6px;
    }
    .top-bar .instituicao,
    .top-bar .info-label,
    .top-bar .subtitulo { color: #fff !important; }
    .top-bar .info-instituicao { color: rgba(255,255,255,.88) !important; }
    .info-card {
        background: #fff !important;
        border: 1px solid #e2e8f0;
        border-left: 4px solid var(--pdf-primary);
    }
    .data-table thead th {
        background: var(--pdf-primary) !important;
        color: #fff !important;
    }
    .titulo-trimestre, .subtitulo-boletim {
        color: var(--pdf-primary);
        font-weight: 700;
    }
    @endif

    @if($modelo === 'formal')
    .documento {
        border: 2px solid var(--boletim-secondary);
        padding: 8px 10px;
        box-sizing: border-box;
    }
    .documento-inner-frame {
        border: 1px solid var(--pdf-primary);
        padding: 8px;
    }
    .top-bar {
        border-bottom: 3px double var(--boletim-secondary) !important;
    }
    .instituicao {
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--boletim-secondary) !important;
    }
    .data-table thead th {
        background: var(--boletim-secondary) !important;
        color: #fff !important;
        border-color: var(--boletim-secondary) !important;
    }
    .info-label { color: var(--boletim-secondary) !important; }
    .assinaturas td { font-weight: 600; }
    @endif
</style>
