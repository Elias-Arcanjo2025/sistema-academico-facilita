<?php

namespace App\Services\OCR;

use App\Services\AI\GeminiService;
use App\Services\AI\OpenRouterService;
use Illuminate\Http\UploadedFile;

class CompositeOCRService implements OCRServiceInterface
{
    public function __construct(
        protected TesseractOCRService $tesseract,
        protected OpenRouterService $openRouterVision,
        protected GeminiService $geminiVision,
    ) {}

    public function extractText(UploadedFile|string $file): OCRResult
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $mime = $file instanceof UploadedFile
            ? ($file->getMimeType() ?? 'image/jpeg')
            : (mime_content_type($path) ?: 'image/jpeg');
        $minConf = config('pedagogico.ocr.confianca_minima', 0.75);

        if (str_starts_with($mime, 'image/')) {
            try {
                $result = $this->tesseract->extractText($path);
                if ($result->confiancaGlobal >= $minConf && $result->texto !== '') {
                    return $result;
                }
            } catch (\Throwable) {
                // fallback vision
            }
        }

        $vision = $this->visionFallback($path, $mime);

        return new OCRResult(
            texto: $vision['texto'],
            confiancaGlobal: $vision['confianca'],
            blocos: [['texto' => $vision['texto'], 'confianca' => $vision['confianca']]],
            provider: $vision['meta']['driver'] ?? 'vision_fallback',
        );
    }

    /**
     * @return array{texto:string, confianca:float, meta:array}
     */
    protected function visionFallback(string $path, string $mime): array
    {
        $driver = config('pedagogico.ai_driver', 'gemini');

        if ($driver === 'gemini' && config('gemini.api_key')) {
            return $this->geminiVision->extractTextFromImage($path, $mime);
        }

        if (config('openrouter.api_key')) {
            return $this->openRouterVision->extractTextFromImage($path, $mime);
        }

        throw new \RuntimeException('Nenhum serviço de visão IA configurado (GEMINI_API_KEY ou OPENROUTER_API_KEY).');
    }
}
