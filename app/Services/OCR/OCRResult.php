<?php

namespace App\Services\OCR;

class OCRResult
{
    /**
     * @param  array<int, array{texto:string, confianca:float}>  $blocos
     */
    public function __construct(
        public readonly string $texto,
        public readonly float $confiancaGlobal,
        public readonly array $blocos = [],
        public readonly string $provider = 'tesseract',
    ) {}
}
