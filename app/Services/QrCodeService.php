<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Gera QR codes embutidos (data URI) para PDFs DomPDF sem remote images.
 */
class QrCodeService
{
    public function dataUri(string $conteudo, int $size = 140): string
    {
        $result = Builder::create()
            ->writer(new PngWriter)
            ->writerOptions([])
            ->data($conteudo)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->size($size)
            ->margin(4)
            ->build();

        return $result->getDataUri();
    }
}
