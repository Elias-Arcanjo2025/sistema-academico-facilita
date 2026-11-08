<?php

namespace App\Services\OCR;

use Illuminate\Http\UploadedFile;

interface OCRServiceInterface
{
    public function extractText(UploadedFile|string $file): OCRResult;
}
