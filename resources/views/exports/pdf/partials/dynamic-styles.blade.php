{{--
    Estilos dinâmicos — margens, fontes, cores e papel das configurações do sistema.
    Variáveis opcionais: $pdfPageSize, $pdfPageOrientation (por documento).
--}}
@php
    $pdfConfig = $pdfConfig ?? app(\App\Services\ConfiguracaoService::class)->getPdfConfig();
    $pdfPageSize = $pdfPageSize ?? ($pdfConfig['paper']['size'] ?? 'A4');
    $pdfPageOrientation = $pdfPageOrientation ?? ($pdfConfig['paper']['orientation'] ?? 'portrait');
@endphp

<style>
    :root {
        --pdf-primary: {{ $pdfConfig['colors']['primary'] }};
        --pdf-table-header: {{ $pdfConfig['colors']['table_header'] }};
        --pdf-font-body: {{ $pdfConfig['fonts']['sizes']['body'] }}px;
        --pdf-font-header: {{ $pdfConfig['fonts']['sizes']['header'] }}px;
        --pdf-font-table: {{ $pdfConfig['fonts']['sizes']['table'] }}px;
        --pdf-logo-width: {{ $pdfConfig['logo']['width'] }}px;
    }

    @page {
        size: {{ $pdfPageSize }} {{ $pdfPageOrientation }};
        margin: {{ $pdfConfig['margins']['top'] }}mm
                {{ $pdfConfig['margins']['right'] }}mm
                {{ $pdfConfig['margins']['bottom'] }}mm
                {{ $pdfConfig['margins']['left'] }}mm;
    }

    body {
        font-family: '{{ $pdfConfig['fonts']['family'] }}', DejaVu Sans, Arial, sans-serif;
        font-size: var(--pdf-font-body);
    }

    .instituicao-nome,
    .instituicao,
    .titulo-documento h1,
    .info-aluno-box,
    .info-label,
    .pdf-accent {
        color: var(--pdf-primary);
    }

    .top-bar,
    .pdf-accent-border {
        border-color: var(--pdf-primary);
    }

    .titulo-documento h1,
    .instituicao-nome {
        font-size: var(--pdf-font-header);
    }

    .data-table tbody td,
    .pauta-table tbody td {
        font-size: var(--pdf-font-table);
    }

    .data-table thead th,
    .pauta-table thead th,
    .pdf-table-header {
        background: var(--pdf-table-header);
    }

    .header-logo img,
    .logo-instituicao,
    .logo-wrap img {
        max-width: var(--pdf-logo-width);
        max-height: var(--pdf-logo-width);
    }
</style>
