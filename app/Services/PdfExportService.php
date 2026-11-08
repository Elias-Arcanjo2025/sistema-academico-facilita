<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;

class PdfExportService
{
    public function __construct(
        protected ConfiguracaoService $configuracaoService,
    ) {}

    public function config(): array
    {
        return $this->configuracaoService->getPdfConfig();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function loadView(
        string $view,
        array $data = [],
        ?string $orientation = null,
        ?string $size = null,
    ): DomPdfInstance {
        $pdfConfig = $this->config();
        $data['pdfConfig'] = $pdfConfig;

        $sizeKey = strtolower($size ?? $pdfConfig['paper']['size'] ?? 'a4');
        $orientationKey = $orientation ?? $pdfConfig['paper']['orientation'] ?? 'portrait';

        $data['pdfPageSize'] = $sizeKey === 'letter' ? 'Letter' : 'A4';
        $data['pdfPageOrientation'] = $orientationKey;

        $pdf = Pdf::loadView($view, $data);

        return $this->applyDompdfOptions($pdf, $sizeKey, $orientationKey, $pdfConfig);
    }

    public function applyDompdfOptions(
        DomPdfInstance $pdf,
        ?string $size = null,
        ?string $orientation = null,
        ?array $pdfConfig = null,
    ): DomPdfInstance {
        $pdfConfig ??= $this->config();
        $size = strtolower($size ?? $pdfConfig['paper']['size'] ?? 'a4');
        $orientation = $orientation ?? $pdfConfig['paper']['orientation'] ?? 'portrait';

        $pdf->setPaper($size, $orientation);
        $pdf->setOption('defaultFont', $pdfConfig['fonts']['family'] ?? 'DejaVu Sans');

        return $pdf;
    }
}
