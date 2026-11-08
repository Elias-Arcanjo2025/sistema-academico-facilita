<?php

namespace App\Services\OCR;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class TesseractOCRService implements OCRServiceInterface
{
    public function extractText(UploadedFile|string $file): OCRResult
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        if (! class_exists(\thiagoalessio\TesseractOCR\TesseractOCR::class)) {
            return new OCRResult(
                texto: '',
                confiancaGlobal: 0.0,
                provider: 'tesseract',
            );
        }

        try {
            $ocr = new \thiagoalessio\TesseractOCR\TesseractOCR($path);
            $lang = config('pedagogico.ocr.tesseract_lang', 'por');
            $ocr->lang($lang);

            $tessPath = config('pedagogico.ocr.tesseract_path');
            if ($tessPath) {
                $ocr->executable($tessPath);
            }

            $texto = trim($ocr->run());
            $confianca = $texto !== '' ? 0.8 : 0.0;

            return new OCRResult(
                texto: $texto,
                confiancaGlobal: $confianca,
                blocos: [['texto' => $texto, 'confianca' => $confianca]],
                provider: 'tesseract',
            );
        } catch (\Throwable $e) {
            throw new RuntimeException('OCR Tesseract falhou: '.$e->getMessage(), 0, $e);
        }
    }
}
